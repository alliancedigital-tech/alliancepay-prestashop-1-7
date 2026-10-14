<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Repository\ScheduledPaymentRepository;
use AlliancePay\Service\Subscription\SubscriptionCancelService;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

/**
 * Customer account page: own subscriptions and cancellation.
 */
class AlliancePaySubscriptionsModuleFrontController extends ModuleFrontController
{
    public $auth = true;

    public $authRedirection = 'my-account';

    private const TRANSLATION_DOMAIN = 'Modules.Alliancepay.Shop';

    /**
     * @return void
     */
    public function postProcess()
    {
        parent::postProcess();

        if (!Tools::isSubmit('alliance_cancel_subscription')) {
            return;
        }

        if (Tools::getValue('token') !== Tools::getToken(false)) {
            $this->errors[] = $this->trans('Invalid request, please try again.', [], self::TRANSLATION_DOMAIN);

            return;
        }

        try {
            $cancelService = ServiceLocator::get(SubscriptionCancelService::class);
            $result = $cancelService->cancel(
                $this->container->get('doctrine.orm.entity_manager'),
                (string) Tools::getValue('provider_order_id'),
                (int) $this->context->customer->id
            );
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: subscription cancel failed: ' . $exception->getMessage(), 3);
            $this->errors[] = $this->trans('The subscription could not be canceled. Please try again later.', [], self::TRANSLATION_DOMAIN);

            return;
        }

        if ($result['success']) {
            $this->success[] = $this->trans('Your subscription has been canceled.', [], self::TRANSLATION_DOMAIN);

            return;
        }

        foreach ($result['errors'] as $error) {
            $this->errors[] = $this->trans($error['message'], $error['params'], self::TRANSLATION_DOMAIN);
        }
    }

    /**
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        $em = $this->container->get('doctrine.orm.entity_manager');
        $rows = [];

        try {
            if (ServiceLocator::get(Config::class)->isSubscriptionsEnabled()) {
                $rows = $this->buildRows($em, (int) $this->context->customer->id);
            }
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: subscriptions page failed: ' . $exception->getMessage(), 3);
        }

        $this->context->smarty->assign([
            'alliance_subscriptions' => $rows,
            'alliance_subscriptions_url' => $this->context->link->getModuleLink($this->module->name, 'subscriptions', [], true),
            'alliance_token' => Tools::getToken(false),
        ]);

        $this->setTemplate('module:alliancepay/views/templates/front/subscription/list.tpl');
    }

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param int $customerId
     * @return array
     */
    private function buildRows($em, int $customerId): array
    {
        $rows = [];
        /** @var ScheduledPaymentRepository $paymentRepository */
        $paymentRepository = $em->getRepository(AllianceScheduledPayment::class);

        /** @var AllianceSubscription $subscription */
        foreach ($em->getRepository(AllianceSubscription::class)->findByCustomer($customerId) as $subscription) {
            $plan = $em->find(AllianceSubscriptionPlan::class, $subscription->getPlanId());
            $nextPayment = null;

            foreach ($paymentRepository->findBySubscription((int) $subscription->getId()) as $payment) {
                if ($payment->getStatus() === SubscriptionConfig::PAYMENT_STATUS_WAITING && $payment->getScheduledDate() !== null) {
                    $date = $payment->getScheduledDate()->format('Y-m-d');

                    if ($nextPayment === null || $date < $nextPayment) {
                        $nextPayment = $date;
                    }
                }
            }

            $rows[] = [
                'plan_name' => $plan !== null ? $plan->getName() : '',
                'order_id' => $subscription->getOrderId(),
                'status_label' => $this->statusLabel($subscription->getStatus()),
                'next_payment' => $nextPayment,
                'can_cancel' => $subscription->getStatus() === SubscriptionConfig::SUBSCRIPTION_STATUS_ACTIVE,
                'provider_order_id' => $subscription->getProviderOrderId(),
            ];
        }

        return $rows;
    }

    /**
     * @param string $status
     * @return string
     */
    private function statusLabel(string $status): string
    {
        $labels = [
            SubscriptionConfig::SUBSCRIPTION_STATUS_PENDING => $this->trans('Awaiting payment', [], self::TRANSLATION_DOMAIN),
            SubscriptionConfig::SUBSCRIPTION_STATUS_ACTIVE => $this->trans('Active', [], self::TRANSLATION_DOMAIN),
            SubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED => $this->trans('Canceled', [], self::TRANSLATION_DOMAIN),
            SubscriptionConfig::SUBSCRIPTION_STATUS_COMPLETED => $this->trans('Completed', [], self::TRANSLATION_DOMAIN),
            SubscriptionConfig::SUBSCRIPTION_STATUS_FAILED => $this->trans('Payment failed', [], self::TRANSLATION_DOMAIN),
        ];

        return $labels[$status] ?? $status;
    }
}
