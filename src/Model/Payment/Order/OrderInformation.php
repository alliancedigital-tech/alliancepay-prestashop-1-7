<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Payment\Order;

use Doctrine\ORM\EntityManagerInterface;
use AlliancePay\Entity\AllianceOrder;

/**
 * Class OrderInformation.
 */
class OrderInformation
{
    public function getOrderOperationsInfo(int $orderId, EntityManagerInterface $entityManager): array
    {
        $repository = $entityManager->getRepository(AllianceOrder::class);
        $order = $repository->findByOrderId((string) $orderId);

        return $this->getOperationsFromCallbackData($order);
    }

    private function getOperationsFromCallbackData(AllianceOrder $order): array
    {
        $callbackData = $order->getCallbackData();

        return $callbackData['operations'] ?? [];
    }
}
