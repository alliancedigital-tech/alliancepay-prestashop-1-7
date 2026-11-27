<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lists the successful operations of an order that can be fiscalized: PURCHASE (also the recurring payments of
 * a subscription) and REFUND.
 */
class FiscalOperationProvider
{
    /**
     * @return array[] each: operationId, type (PURCHASE|REFUND), coinAmount (bank currency, kopecks)
     */
    public function getOperations(EntityManagerInterface $em, int $orderId): array
    {
        $operations = [];

        /** @var AllianceOrder|null $allianceOrder */
        $allianceOrder = $em->getRepository(AllianceOrder::class)->findByOrderId((string) $orderId);

        if ($allianceOrder !== null) {
            foreach ($allianceOrder->getCallbackData()['operations'] ?? [] as $operation) {
                $type = $operation['type'] ?? null;

                if (!in_array($type, [Config::OPERATION_TYPE_PURCHASE, 'REFUND'], true)
                    || ($operation['status'] ?? null) !== Config::SUCCESS_ORDER_STATUS
                    || empty($operation['operationId'])
                    || !isset($operation['coinAmount'])
                ) {
                    continue;
                }

                $operations[(string) $operation['operationId']] = [
                    'operationId' => (string) $operation['operationId'],
                    'type' => $type === 'REFUND' ? FiscalizationStatus::TYPE_REFUND : FiscalizationStatus::TYPE_PURCHASE,
                    'coinAmount' => (int) $operation['coinAmount'],
                ];
            }
        }

        foreach ($this->getRecurringPurchases($em, $orderId) as $operation) {
            $operations[$operation['operationId']] = $operation;
        }

        return array_values($operations);
    }

    public function findOperation(EntityManagerInterface $em, int $orderId, string $operationId): ?array
    {
        foreach ($this->getOperations($em, $orderId) as $operation) {
            if ($operation['operationId'] === $operationId) {
                return $operation;
            }
        }

        return null;
    }

    /**
     * @return array[]
     */
    private function getRecurringPurchases(EntityManagerInterface $em, int $orderId): array
    {
        $result = [];
        $subscriptions = $em->getRepository(AllianceSubscription::class)->findBy(['orderId' => $orderId]);

        foreach ($subscriptions as $subscription) {
            /** @var AllianceScheduledPayment $row */
            foreach ($em->getRepository(AllianceScheduledPayment::class)
                         ->findBy(['subscriptionId' => $subscription->getId()]) as $row) {
                if ($row->getOperationId() === null
                    || $row->getOperationId() === ''
                    || $row->getOperationStatus() !== Config::SUCCESS_ORDER_STATUS
                    || $row->getCoinAmount() === null
                ) {
                    continue;
                }

                $result[] = [
                    'operationId' => $row->getOperationId(),
                    'type' => FiscalizationStatus::TYPE_PURCHASE,
                    'coinAmount' => (int) $row->getCoinAmount(),
                ];
            }
        }

        return $result;
    }
}
