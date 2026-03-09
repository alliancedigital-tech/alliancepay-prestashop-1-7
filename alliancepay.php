<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Model\Payment\Order\OrderInformation;
use AlliancePay\Model\Payment\Refund\RefundProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PrestaShop\PrestaShop\Adapter\CoreException;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Alliancepay extends PaymentModule
{

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
        $this->version = '1.0.0';
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
            'displayAdminOrderTabLink'
        ];
        foreach ($hooks as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        try {
            $instalTablesResult = $this->createAllianceOrderTable() && $this->createAllianceRefundTable();
        } catch(PrestaShopDatabaseException $e) {
            $message = 'Alliance create tables error: ' . $e->getMessage();
            PrestaShopLogger::addLog('AlliancePay: ' . $message, 3);

            return false;
        }

        return Configuration::updateValue('ALLIANCE_PAYMENT_NAME', 'AlliancePay')
            && $instalTablesResult;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return (
            parent::uninstall()
            && Configuration::deleteByName('ALLIANCE_PAYMENT_NAME')
        );
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

            /** @var OrderSlip $orderSlip */
            $orderSlip = $order->getOrderSlipsCollection()->getLast();
            $amountToRefund = $orderSlip->total_shipping_tax_incl + $orderSlip->total_products_tax_incl;

            if ($amountToRefund > 0) {
                $refundProcessor->refund($this->context, $entityManager, $amountToRefund, (string) $order->id);
            }
        }
    }

    public function hookDisplayAdminOrderTabLink(array $params)
    {
        return [
            'id'    => 'alliancepay-tab',
            'title' => $this->l('Extra info'),
        ];
    }

    public function hookDisplayAdminOrderTabContent(array $params)
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
