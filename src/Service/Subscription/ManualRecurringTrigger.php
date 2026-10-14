<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Service\RequestIdentification\GenerateRequestIdentification;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class ManualRecurringTrigger.
 */
class ManualRecurringTrigger
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var SubscriptionApiClient
     */
    private $apiClient;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    public function __construct(Config $config, SubscriptionApiClient $apiClient, AuditLogger $auditLogger)
    {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @return array{success: bool, errors: array}
     */
    public function trigger(EntityManagerInterface $em, AllianceScheduledPayment $payment, int $employeeId): array
    {
        if ($payment->getScheduledPaymentId() === null || $payment->getScheduledPaymentId() === '') {
            return ['success' => false, 'errors' => [[
                'message' => 'This payment has no provider scheduled payment id, it cannot be triggered.',
                'params' => [],
            ]]];
        }

        $subscription = $em->find(AllianceSubscription::class, $payment->getSubscriptionId());

        $result = $this->apiClient->triggerRecurringPayment([
            'hppOrderId' => $payment->getProviderOrderId(),
            'scheduledPaymentId' => $payment->getScheduledPaymentId(),
            'merchantId' => $this->config->getMerchantId(),
            'merchantRequestId' => GenerateRequestIdentification::generateRequestId(),
        ]);

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_MANUAL_RECURRING,
            [
                'scheduled_payment_id' => $payment->getScheduledPaymentId(),
                'success' => $result->isSuccess(),
                'error' => $result->getError(),
            ],
            $subscription !== null ? $subscription->getPlanId() : null,
            null,
            $payment->getSubscriptionId(),
            $employeeId
        );

        if (!$result->isSuccess()) {
            return ['success' => false, 'errors' => [[
                'message' => 'The provider rejected the trigger: %error%',
                'params' => ['%error%' => (string) $result->getError()],
            ]]];
        }

        return ['success' => true, 'errors' => []];
    }
}
