<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Config\Config;
use AlliancePay\Model\Payment\Order\OrderInformation;
use AlliancePay\Model\Payment\Refund\RefundProcessor;
use AlliancePay\Service\Completion\CompletionProcessor;
use AlliancePay\Service\Subscription\Checkout\CartSubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Alliancepay extends PaymentModule
{
    public const SUBSCRIPTION_HOOKS = [
        'displayProductActions',
        'actionFrontControllerInitAfter',
        'actionCartUpdateQuantityBefore',
        'actionObjectProductInCartDeleteAfter',
        'displayCustomerAccount',
        'actionFrontControllerSetMedia',
    ];

    /**
     * Admin tabs of the subscription feature: class name (= _legacy_controller of the route) => [route, English name, Ukrainian name].
     */
    public const SUBSCRIPTION_TABS = [
        'AlliancePayPlans' => ['alliance.admin.subscription.plans', 'Subscription plans', 'Плани підписок'],
        'AlliancePayTemplates' => ['alliance.admin.subscription.templates', 'Plan templates', 'Шаблони планів'],
        'AlliancePaySubscriptions' => ['alliance.admin.subscription.subscriptions', 'Subscriptions', 'Підписки'],
        'AlliancePaySubscriptionAudit' => ['alliance.admin.subscription.audit', 'Subscription audit log', 'Журнал аудиту підписок'],
    ];

    public const SUBSCRIPTION_PARENT_TAB = 'AlliancePaySubscriptionsParent';


    private $currency;

    /**
     * @var string
     */
    private $author_address;

    /**
     * @var string
     */
    private $logger;

    public function __construct()
    {
        $this->name = 'alliancepay';
        $this->tab = 'payments_gateways';
        $this->version = '1.4.0';
        $this->author = 'Alliance Dgtl.';
        $this->bootstrap = true;
        $this->active = true;
        parent::__construct();
        $this->displayName = $this->l('AlliancePay');
        $this->description = $this->l('AlliancePay payment module for Prestashop');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    /**
     * @return bool
     */
    public function install()
    {
        parent::install();
        $hooks = [
            'paymentOptions',
            'paymentReturn',
            'displayHeader',
            'actionOrderSlipAdd',
            'displayAdminOrderTabContent',
            'displayAdminOrderTabLink',
            'hookDisplayAdminOrderTop',
            'actionOrderStatusUpdate',
        ];
        $hooks = array_merge($hooks, self::SUBSCRIPTION_HOOKS);
        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        try {
            $instalTablesResult = $this->createAllianceOrderTable()
                && $this->createAllianceRefundTable()
                && $this->createSubscriptionTables();
        } catch(PrestaShopDatabaseException $e) {
            $message = 'Alliance create tables error: ' . $e->getMessage();
            PrestaShopLogger::addLog('AlliancePay: ' . $message, 3);

            return false;
        }

        return Configuration::updateValue('ALLIANCE_PAYMENT_NAME', 'AlliancePay')
            && $instalTablesResult
            && $this->installSubscriptionTabs();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return (
            $this->uninstallSubscriptionTabs()
            && parent::uninstall()
            && Configuration::deleteByName('ALLIANCE_PAYMENT_NAME')
        );
    }

    /**
     * Registers the subscription hooks (install and upgrade-1.4.0.php).
     *
     * @return bool
     */
    public function registerSubscriptionHooks()
    {
        foreach (self::SUBSCRIPTION_HOOKS as $hook) {
            if (!$this->isRegisteredInHook($hook) && !$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adds the "Subscriptions" admin menu (under Orders) with its pages. Idempotent.
     *
     * @return bool
     */
    public function installSubscriptionTabs()
    {
        $parentId = $this->ensureTab(
            self::SUBSCRIPTION_PARENT_TAB,
            (int) Tab::getIdFromClassName('AdminParentOrders'),
            'Subscriptions',
            'Підписки',
            ''
        );

        if (!$parentId) {
            return false;
        }

        foreach (self::SUBSCRIPTION_TABS as $className => $definition) {
            if (!$this->ensureTab($className, $parentId, $definition[1], $definition[2], $definition[0])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstallSubscriptionTabs()
    {
        $classNames = array_merge(array_keys(self::SUBSCRIPTION_TABS), [self::SUBSCRIPTION_PARENT_TAB]);

        foreach ($classNames as $className) {
            $tabId = (int) Tab::getIdFromClassName($className);

            if ($tabId) {
                $tab = new Tab($tabId);

                if (!$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string $className
     * @param int $parentId
     * @param string $nameEn
     * @param string $nameUk
     * @param string $routeName
     * @return int tab id, 0 on failure
     */
    private function ensureTab($className, $parentId, $nameEn, $nameUk, $routeName)
    {
        $existingId = (int) Tab::getIdFromClassName($className);

        if ($existingId) {
            return $existingId;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $className;
        $tab->route_name = $routeName;
        $tab->module = $this->name;
        $tab->id_parent = (int) $parentId;
        $tab->name = [];

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[$language['id_lang']] = $language['iso_code'] === 'uk' ? $nameUk : $nameEn;
        }

        return $tab->add() ? (int) $tab->id : 0;
    }

    /**
     * Subscription choice on the product page (rendered inside the add-to-cart form).
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayProductActions($params)
    {
        try {
            $service = ServiceLocator::get(CartSubscriptionService::class);
            $em = $this->getService('doctrine.orm.entity_manager');
            $product = $params['product'];
            $productId = (int) (is_array($product) || $product instanceof ArrayAccess ? $product['id_product'] : $product->id);
            $currency = $this->context->currency;
            $plans = $service->getOfferedPlans($em, $productId, $currency ? $currency->iso_code : null);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: subscription options failed: ' . $exception->getMessage(), 3);

            return '';
        }

        if (empty($plans)) {
            return '';
        }

        $plansView = [];

        foreach ($plans as $plan) {
            $plansView[] = [
                'id' => $plan->getId(),
                'name' => $plan->getName(),
                'stages' => $this->presentRules($plan->getRules()),
            ];
        }

        $customer = $this->context->customer;
        $this->context->smarty->assign([
            'alliance_plans' => $plansView,
            'alliance_is_logged' => Validate::isLoadedObject($customer) && $customer->isLogged() && !$customer->isGuest(),
            'alliance_login_url' => $this->context->link->getPageLink('authentication', true),
            'alliance_field_type' => CartSubscriptionService::REQUEST_PURCHASE_TYPE,
            'alliance_field_plan' => CartSubscriptionService::REQUEST_PLAN_ID,
        ]);

        return $this->fetch('module:alliancepay/views/templates/front/subscription/product_actions.tpl');
    }

    /**
     * First line of the cart guard, for add-to-cart / cart-update requests: answers with the subscription
     * error only, so the customer does not also get PrestaShop's generic "maximum quantity" message.
     *
     * @param array $params
     * @return void
     */
    public function hookActionFrontControllerInitAfter($params)
    {
        $controller = $params['controller'];

        if (!($controller instanceof CartController) || (!Tools::getIsset('add') && !Tools::getIsset('update'))) {
            return;
        }

        try {
            if (!ServiceLocator::get(Config::class)->isSubscriptionsEnabled()) {
                return;
            }

            $service = ServiceLocator::get(CartSubscriptionService::class);
            $customer = $this->context->customer;
            $productId = (int) Tools::getValue('id_product');
            $attributeId = (int) Tools::getValue('id_product_attribute');

            if (Tools::getIsset('group') && $productId) {
                $attributeId = (int) Product::getIdProductAttributeByIdAttributes($productId, Tools::getValue('group'));
            }

            $errors = $service->guardCartChange(
                $this->getService('doctrine.orm.entity_manager'),
                $this->context->cart,
                $productId,
                $attributeId,
                abs((int) Tools::getValue('qty', 1)),
                (string) Tools::getValue('op', 'up'),
                Validate::isLoadedObject($customer) && $customer->isLogged() && !$customer->isGuest(),
                Tools::getValue(CartSubscriptionService::REQUEST_PURCHASE_TYPE) ?: null,
                (int) Tools::getValue(CartSubscriptionService::REQUEST_PLAN_ID),
                false
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: cart pre-check failed: ' . $exception->getMessage(), 3);

            return;
        }

        if (empty($errors)) {
            return;
        }

        $messages = $service->translateErrors($errors);

        if (Tools::getValue('ajax')) {
            header('Content-Type: application/json');
            die(json_encode(['hasError' => true, 'errors' => $messages]));
        }

        $this->context->cookie->alliance_error = implode(' ', $messages);
        $this->context->cookie->write();
        Tools::redirect($this->context->link->getPageLink('cart', true, null, ['action' => 'show']));
    }

    /**
     * Server-side guard: no guest subscription, no mixed cart, no two subscriptions.
     * A refused change is turned into a no-op (available_for_order=false) plus a visible error.
     *
     * @param array $params
     * @return void
     */
    public function hookActionCartUpdateQuantityBefore($params)
    {
        try {
            $config = ServiceLocator::get(Config::class);

            if (!$config->isSubscriptionsEnabled()) {
                return;
            }

            $service = ServiceLocator::get(CartSubscriptionService::class);
            $em = $this->getService('doctrine.orm.entity_manager');
            $customer = $this->context->customer;

            $errors = $service->guardCartChange(
                $em,
                $params['cart'],
                (int) $params['product']->id,
                (int) $params['id_product_attribute'],
                (int) $params['quantity'],
                (string) $params['operator'],
                Validate::isLoadedObject($customer) && $customer->isLogged() && !$customer->isGuest(),
                Tools::getValue(CartSubscriptionService::REQUEST_PURCHASE_TYPE) ?: null,
                (int) Tools::getValue(CartSubscriptionService::REQUEST_PLAN_ID)
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: cart guard failed: ' . $exception->getMessage(), 3);

            return;
        }

        if (empty($errors)) {
            return;
        }

        if ($this->context->controller && property_exists($this->context->controller, 'errors')) {
            foreach ($service->translateErrors($errors) as $message) {
                $this->context->controller->errors[] = $message;
            }
        }

        $params['product']->available_for_order = false;
    }

    /**
     * @param array $params
     * @return void
     */
    public function hookActionObjectProductInCartDeleteAfter($params)
    {
        try {
            $service = ServiceLocator::get(CartSubscriptionService::class);
            $service->removeIntent(
                $this->getService('doctrine.orm.entity_manager'),
                (int) $params['id_cart'],
                (int) $params['id_product'],
                (int) $params['id_product_attribute']
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: cart intent cleanup failed: ' . $exception->getMessage(), 3);
        }
    }

    /**
     * @return string
     */
    public function hookDisplayCustomerAccount()
    {
        try {
            if (!ServiceLocator::get(Config::class)->isSubscriptionsEnabled()) {
                return '';
            }
        } catch (Throwable $exception) {
            return '';
        }

        $this->context->smarty->assign([
            'alliance_subscriptions_url' => $this->context->link->getModuleLink($this->name, 'subscriptions', [], true),
        ]);

        return $this->fetch('module:alliancepay/views/templates/front/subscription/account_link.tpl');
    }

    /**
     * @return void
     */
    public function hookActionFrontControllerSetMedia()
    {
        if ($this->context->controller && $this->context->controller->php_self === 'product') {
            $this->context->controller->registerJavascript(
                'alliancepay-subscription',
                'modules/' . $this->name . '/views/js/subscription.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }
    }

    /**
     * @param array $rules
     * @return array
     * @throws LocalizationException
     */
    private function presentRules(array $rules)
    {
        $stages = [];

        foreach ($rules as $rule) {
            $from = (int) $rule['paymentNumberFrom'];
            $to = (int) $rule['paymentNumberTo'];
            $stages[] = [
                'payments' => $from === $to ? (string) $from : $from . '–' . $to,
                'amount' => Tools::displayPrice(((int) $rule['coinAmount']) / 100),
                'is_free' => (int) $rule['coinAmount'] === 0,
                'interval' => (int) $rule['intervalValue'],
                'unit' => strtolower((string) $rule['intervalUnit']),
            ];
        }

        return $stages;
    }

    /**
     * @param $params
     * @return array|PaymentOption[]
     * @throws Exception
     */
    public function hookPaymentOptions($params)
    {

        if (!\Alliancepay::isEnabled($this->name)) {
            return [];
        }

        $paymentOption = new PaymentOption();
        $context = Context::getContext();
        $actionUrl = $context->link->getModuleLink(
            $this->name,
            'payment',
            [],
            true
        );

        $paymentOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));
        $paymentOption->setModuleName('alliancepay');
        $paymentOption->setCallToActionText($this->l('AlliancePay'));
        $paymentOption->setAction($actionUrl);
        $paymentOption->setAdditionalInformation($this->fetch(
                'module:alliancepay/views/templates/front/hook/payment.tpl',
            ));

        return [$paymentOption];
    }

    /**
     * @param $params
     * @return string
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active
            || (empty($params['order']) && !($params['order'] instanceof Order))
        ) {
            return '';
        }

        $order = $params['order'];

        $isPaid = $order->getCurrentState() == Configuration::get('PS_OS_PAYMENT');

        return $this->fetch(
            'module:alliancepay/views/templates/hook/payment_return.tpl',
            [
                'order' => $order,
                'isPaid' => $isPaid,
            ]
        );
    }

    /**
     * @param $params
     * @return void
     * @throws Exception
     */
    public function hookActionOrderSlipAdd($params)
    {
        $refundProcessor = $this->initRefundProcessor();
        $entityManager = $this->context->container->get('doctrine.orm.entity_manager');

        if ($refundProcessor && $entityManager) {
            /** @var Order $order */
            $order = $params['order'];

            if ($order->module !== $this->name) {
                return;
            }

            /** @var \AlliancePay\Entity\AllianceOrder|null $allianceOrder */
            $allianceOrder = $entityManager
                ->getRepository(\AlliancePay\Entity\AllianceOrder::class)
                ->findByOrderId((string) $order->id);

            if ($allianceOrder !== null && !$refundProcessor->assertRefundAllowed($allianceOrder)) {
                $this->context->container->get('session')
                    ->getFlashBag()
                    ->add('warning', $this->l('Refund via bank Alliance is not available for this order (A2A).'));

                return;
            }

            /** @var OrderSlip $orderSlip */
            $orderSlip = $order->getOrderSlipsCollection()->getLast();
            $amountToRefund = $orderSlip->total_shipping_tax_incl + $orderSlip->total_products_tax_incl;

            if ($amountToRefund > 0) {
                $refundProcessor->refund($this->context, $entityManager, $amountToRefund, (string) $order->id);
            }
        }
    }

    /**
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderTop(array $params): string
    {
        $orderId = (int) $params['id_order'];
        $entityManager = $this->context->container->get('doctrine.orm.entity_manager');

        /** @var \AlliancePay\Entity\AllianceOrder|null $allianceOrder */
        $allianceOrder = $entityManager
            ->getRepository(\AlliancePay\Entity\AllianceOrder::class)
            ->findByOrderId((string) $orderId);

        if ($allianceOrder === null
            || $allianceOrder->getHppPayType() !== Config::HPP_PAY_TYPE_A2A
            || $allianceOrder->getTransactionType() !== Config::TRANSACTION_TYPE_A2A
        ) {
            return '';
        }

        $twig = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('twig');

        return $twig->render('@Modules/alliancepay/views/templates/admin/order/refund_warning.twig');
    }

    public function hookDisplayAdminOrderTabLink(array $params): string
    {
        return '<li class="nav-item">'
            . '<a class="nav-link" id="alliancepay-tab-link" data-toggle="tab"'
            . ' href="#alliancepay-tab" role="tab">'
            . $this->l('Extra info')
            . '</a>'
            . '</li>';
    }

    public function hookDisplayAdminOrderTabContent(array $params): string
    {
        $orderId = (int) $params['id_order'];
        $operations = [];
        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
        $twig = $container->get('twig');
        $router = $container->get('router');
        $orderInfo = $this->initOrderInformation();
        $entityManager = $this->context->container->get('doctrine.orm.entity_manager');

        if ($orderInfo instanceof OrderInformation
            && $entityManager instanceof EntityManagerInterface
        ) {
            $operations = $orderInfo->getOrderOperationsInfo($orderId, $entityManager);
        }

        return $twig->render(
            '@Modules/alliancepay/views/templates/admin/order/order_transactions.twig',
            [
                'order_id' => $orderId,
                'operations' => $operations,
                'sync_url' => $router->generate(
                    'alliance.order.sync',
                    ['orderId' => $orderId]
                ),
            ]
        );
    }

    /**
     * @return void
     */
    public function hookDisplayHeader()
    {
        if (!empty($this->context->cookie->alliance_error)) {
            $this->context->controller->errors[] =
                $this->context->cookie->alliance_error;
            unset($this->context->cookie->alliance_error);
            $this->context->cookie->write();
        }
    }

    /**
     * @param array $params
     * @return void
     * @throws CoreException
     */
    public function hookActionOrderStatusUpdate(array $params): void
    {
        $newStatusId = (int) $params['newOrderStatus']->id;

        $config = ServiceLocator::get(Config::class);
        $completionStatusId = (int) $config->getCompletionOrderState();

        if (!$completionStatusId || $newStatusId !== $completionStatusId) {
            return;
        }

        $psOrder = new \Order((int) $params['id_order']);

        if ($psOrder->module !== $this->name) {
            return;
        }

        $entityManager = $this->getService('doctrine.orm.entity_manager');

        if (!$entityManager instanceof EntityManagerInterface) {
            return;
        }

        try {
            $completionProcessor = ServiceLocator::get(CompletionProcessor::class);
            $completionProcessor->processCompletion($entityManager, $psOrder);
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('AlliancePay Completion error: ' . $e->getMessage(), 3);
        }
    }

    /**
     * @return void
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AlliancePaySettingsController'));
    }

    /**
     * @param $serviceName
     * @return false|object
     * @throws Exception
     */
    public function getService($serviceName)
    {
        return $this->get($serviceName);
    }

    /**
     * @return true
     */
    public function isPaymentModule()
    {
        return true;
    }

    /**
     * Creates the subscription tables (also used by upgrade-1.4.0.php).
     *
     * @return bool
     */
    public function createSubscriptionTables()
    {
        if (!class_exists('\AlliancePay\Install\SubscriptionSchema')) {
            require_once __DIR__ . '/src/Install/SubscriptionSchema.php';
        }

        foreach (\AlliancePay\Install\SubscriptionSchema::getCreateStatements(_DB_PREFIX_) as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    private function createAllianceOrderTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'alliance_order'
            . ' (entity_id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL,'
            . ' merchant_request_id VARCHAR(255) NOT NULL,'
            . ' hpp_order_id VARCHAR(255) NOT NULL,'
            . ' merchant_id VARCHAR(255) NOT NULL,'
            . ' coin_amount INT NOT NULL,'
            . ' hpp_pay_type VARCHAR(50) NOT NULL,'
            . ' order_status VARCHAR(50) NOT NULL,'
            . ' payment_methods LONGTEXT NOT NULL COMMENT \'(DC2Type: json)\','
            . ' create_date DATETIME NOT NULL,'
            . ' updated_at DATETIME NOT NULL,'
            . ' operation_id VARCHAR(255) NOT NULL,'
            . ' ecom_order_id VARCHAR(255) NOT NULL,'
            . ' is_callback_returned TINYINT(1) NOT NULL,'
            . ' callback_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\','
            . ' expired_order_date DATETIME NOT NULL,'
            . ' transaction_type SMALLINT DEFAULT NULL,'
            . ' original_authorized_amount INT DEFAULT NULL,'
            . ' currency_code SMALLINT(3) DEFAULT NULL,'
            . ' conversion_rate DECIMAL(20,8) DEFAULT NULL,'
            . ' INDEX ALLIANCE_CHECKOUT_INTEGRATION_ORDER_MERCHANT_REQUEST_ID (merchant_request_id),'
            . ' INDEX ALLIANCE_CHECKOUT_INTEGRATION_ORDER_HPP_ORDER_ID (hpp_order_id),'
            . ' INDEX ALLIANCE_CHECKOUT_INTEGRATION_ORDER_MERCHANT_ID (merchant_id),'
            . ' INDEX ALLIANCE_CHECKOUT_INTEGRATION_ORDER_ORDER_ID (order_id),'
            . ' PRIMARY KEY(entity_id)) DEFAULT CHARACTER SET utf8mb4'
            . ' COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * @return bool
     */
    private function createAllianceRefundTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'alliance_refund_order '
            . ' (refund_id INT AUTO_INCREMENT NOT NULL,'
            . ' order_id INT NOT NULL,'
            . ' type VARCHAR(255) NOT NULL,'
            . ' rrn VARCHAR(255) NOT NULL,'
            . ' purpose VARCHAR(255) NULL,'
            . ' comment VARCHAR(255) NULL,'
            . ' coin_amount INT NOT NULL,'
            . ' merchant_id VARCHAR(255) NOT NULL,'
            . ' operation_id VARCHAR(255) NOT NULL,'
            . ' ecom_operation_id VARCHAR(255) NOT NULL,'
            . ' merchant_name VARCHAR(255) NULL,'
            . ' approval_code VARCHAR(255) NULL,'
            . ' status VARCHAR(255) NOT NULL,'
            . ' transaction_type INT NOT NULL,'
            . ' merchant_request_id VARCHAR(255) NOT NULL,'
            . ' transaction_currency VARCHAR(255) NOT NULL,'
            . ' merchant_commission INT NULL,'
            . ' creation_date_time DATETIME NOT NULL,'
            . ' modification_date_time DATETIME NOT NULL,'
            . ' action_code VARCHAR(255) NULL,'
            . ' response_code VARCHAR(255) NULL,'
            . ' description VARCHAR(255) NULL,'
            . ' processing_merchant_id VARCHAR(255) NOT NULL,'
            . ' processing_terminal_id VARCHAR(255) NOT NULL,'
            . ' transaction_response_info LONGTEXT NULL COMMENT \'(DC2Type:json)\','
            . ' payment_system VARCHAR(255) NULL,'
            . ' product_type VARCHAR(255) NOT NULL,'
            . ' notification_url VARCHAR(255) NOT NULL,'
            . ' payment_service_type VARCHAR(255) NULL,'
            . ' notification_encryption VARCHAR(255) NOT NULL,'
            . ' original_operation_id VARCHAR(255) NOT NULL,'
            . ' original_coin_amount INT NOT NULL,'
            . ' original_ecom_operation_id VARCHAR(255) NOT NULL,'
            . ' rrn_original VARCHAR(255) NOT NULL,'
            . ' INDEX ALLIANCE_INTEGRATION_ORDER_REFUND_MERCHANT_REQUEST_ID (merchant_request_id),'
            . ' INDEX ALLIANCE_INTEGRATION_ORDER_REFUND_MERCHANT_ID (merchant_id),'
            . ' PRIMARY KEY(refund_id)) DEFAULT CHARACTER SET utf8mb4'
            . ' COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * @return mixed|object|null
     */
    private function initRefundProcessor()
    {
        if (class_exists('\AlliancePay\Model\Payment\Refund\RefundProcessor')) {
            try {
                $refundProcessor = ServiceLocator::get(
                    RefundProcessor::class
                );

                return $refundProcessor;
            } catch (CoreException $exception) {
                $message = 'Create RefundProcessor instance error: ' . $exception->getMessage();
                PrestaShopLogger::addLog('AlliancePay: ' . $message, 3);

                return null;
            }
        }

        return null;
    }

    /**
     * @return mixed|object|null
     */
    private function initOrderInformation()
    {
        if (class_exists('\AlliancePay\Model\Payment\Order\OrderInformation')) {
            try {
                $orderInfo = ServiceLocator::get(
                    OrderInformation::class
                );

                return $orderInfo;
            } catch (CoreException $exception) {
                $message = 'Create OrderInformation instance error: ' . $exception->getMessage();
                PrestaShopLogger::addLog('AlliancePay: ' . $message, 3);

                return null;
            }
        }

        return null;
    }
}
