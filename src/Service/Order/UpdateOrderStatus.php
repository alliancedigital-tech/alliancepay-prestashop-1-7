<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Order;

use PrestaShop\PrestaShop\Adapter\Entity\Order;
use PrestaShop\PrestaShop\Adapter\Entity\OrderHistory;
use PrestaShopDatabaseException;
use PrestaShopException;
use PrestaShopLogger;

/**
 * Class UpdateOrderStatus.
 */
class UpdateOrderStatus
{
    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function updateOrderStatus(int $orderId, int $statusId)
    {
        $order = new Order($orderId);

        if ($order->current_state != $statusId) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState($statusId, $order);

            if ($history->add()) {
                $order->current_state = $statusId;
                $order->update();

                PrestaShopLogger::addLog("Order status updated to $statusId", 1);
            } else {
                PrestaShopLogger::addLog("Failed to add history record", 3);
            }
        }
    }
}
