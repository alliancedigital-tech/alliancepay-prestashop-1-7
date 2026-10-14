<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use DateTimeImmutable;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Class SubscriptionCancelService.
 */
class SubscriptionCancelService
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
     * @param EntityManagerInterface $em
     * @param string $providerOrderId
     * @param int|null $customerId
     * @param int|null $employeeId
     * @return array
     * @throws ConnectionException
     * @throws Throwable
     */
    public function cancel(
        EntityManagerInterface $em,
        string $providerOrderId,
        ?int $customerId = null,
        ?int $employeeId = null
    ): array {
        /** @var AllianceSubscription|null $subscription */
        $subscription = $em->getRepository(AllianceSubscription::class)
            ->findOneBy(['providerOrderId' => $providerOrderId]);

        if ($subscription === null || ($customerId !== null && $subscription->getCustomerId() !== $customerId)) {
            return $this->fail('Subscription not found.');
        }

        if (!in_array($subscription->getStatus(), SubscriptionConfig::SUBSCRIPTION_RUNNING_STATUSES, true)) {
            return $this->fail('Only an active subscription can be canceled.');
        }

        $result = $this->apiClient->cancelSubscriptionOrder([
            'hppOrderId' => $providerOrderId,
            'merchantId' => $this->config->getMerchantId(),
        ]);

        if (!$result->isSuccess()) {
            return $this->fail('The provider could not cancel the subscription: %error%', [
                '%error%' => (string) $result->getError(),
            ]);
        }

        $em->getConnection()->beginTransaction();

        try {
            /** @var AllianceSubscription $locked */
            $locked = $em->find(AllianceSubscription::class, $subscription->getId(), LockMode::PESSIMISTIC_WRITE);

            if (in_array($locked->getStatus(), SubscriptionConfig::SUBSCRIPTION_RUNNING_STATUSES, true)) {
                $locked->setStatus(SubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED)
                    ->setUpdatedAt(new DateTimeImmutable('now'));
            }

            $em->flush();
            $em->getConnection()->commit();
        } catch (Throwable $e) {
            $em->getConnection()->rollBack();

            throw $e;
        }

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_SUBSCRIPTION_CANCELED,
            ['provider_order_id' => $providerOrderId, 'by' => $employeeId !== null ? 'employee' : 'customer'],
            $subscription->getPlanId(),
            null,
            $subscription->getId(),
            $employeeId
        );

        return ['success' => true, 'errors' => []];
    }

    private function fail(string $message, array $params = []): array
    {
        return ['success' => false, 'errors' => [['message' => $message, 'params' => $params]]];
    }
}
