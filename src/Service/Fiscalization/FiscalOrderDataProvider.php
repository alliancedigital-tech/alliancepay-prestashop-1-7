<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Entity\AllianceOrder;
use Db;
use Doctrine\ORM\EntityManagerInterface;
use Order;
use RuntimeException;
use Validate;

/**
 * Reads the lines (items, shipping, wrapping) of a PrestaShop order or of one of its credit slips
 * in the shape expected by FiscalAmountDistributor.
 */
class FiscalOrderDataProvider
{
    /**
     * @var FiscalOperationProvider
     */
    private $operationProvider;

    /**
     * @var RefundSlipMatcher
     */
    private $slipMatcher;

    public function __construct(FiscalOperationProvider $operationProvider, RefundSlipMatcher $slipMatcher)
    {
        $this->operationProvider = $operationProvider;
        $this->slipMatcher = $slipMatcher;
    }

    /**
     * @param EntityManagerInterface $em
     * @param int $orderId
     * @param array $operation see FiscalOperationProvider::getOperations()
     * @return array{lines: array[], shopId: ?int, comment: string}
     * @throws RuntimeException with a message for the administrator
     */
    public function getData(EntityManagerInterface $em, int $orderId, array $operation): array
    {
        $order = new Order($orderId);

        if (!Validate::isLoadedObject($order)) {
            throw new RuntimeException('Order not found.');
        }

        if ($operation['type'] === FiscalizationStatus::TYPE_REFUND) {
            $lines = $this->getRefundLines($em, $order, $operation);
            $comment = 'REFUND_FOR_ORDER_' . $order->reference;
        } else {
            $lines = $this->getPurchaseLines($order);
            $comment = 'PAYMENT_FOR_ORDER_' . $order->reference;
        }

        return [
            'lines' => $lines,
            'shopId' => $order->id_shop ? (int) $order->id_shop : null,
            'comment' => mb_substr($comment, 0, 255),
        ];
    }

    /**
     * @return array[]
     */
    private function getPurchaseLines(Order $order): array
    {
        $lines = [];

        foreach ($order->getProducts() as $product) {
            $lines[] = [
                'id' => $this->productId($product),
                'name' => (string) $product['product_name'],
                'count' => (float) $product['product_quantity'],
                'amount' => (float) $product['total_price_tax_incl'],
                'productId' => (int) $product['product_id'],
                'attributeId' => (int) $product['product_attribute_id'],
                'service' => false,
            ];
        }

        $lines[] = $this->serviceLine('shipping', 'Доставка', (float) $order->total_shipping_tax_incl);
        $lines[] = $this->serviceLine('wrapping', 'Подарункове пакування', (float) $order->total_wrapping_tax_incl);

        return $lines;
    }

    /**
     * @return array[]
     */
    private function getRefundLines(EntityManagerInterface $em, Order $order, array $operation): array
    {
        $allianceOrder = $em->getRepository(AllianceOrder::class)->findByOrderId((string) $order->id);
        $rate = $allianceOrder instanceof AllianceOrder ? ($allianceOrder->getConversionRate() ?? 1.0) : 1.0;

        $refundOperations = array_values(array_filter(
            $this->operationProvider->getOperations($em, (int) $order->id),
            function ($item) {
                return $item['type'] === FiscalizationStatus::TYPE_REFUND;
            }
        ));

        $slips = [];
        foreach ($order->getOrderSlipsCollection() as $slip) {
            $slips[] = [
                'id' => (int) $slip->id,
                'amount' => (float) $slip->total_products_tax_incl + (float) $slip->total_shipping_tax_incl,
            ];
        }
        usort($slips, function ($a, $b) {
            return $a['id'] <=> $b['id'];
        });

        $matched = $this->slipMatcher->match($refundOperations, $slips, (float) $rate);

        if (!isset($matched[$operation['operationId']])) {
            throw new RuntimeException('The credit slip of this refund was not found, the refunded items are unknown.');
        }

        $slipId = $matched[$operation['operationId']];
        $rows = Db::getInstance()->executeS(
            'SELECT osd.product_quantity, osd.amount_tax_incl, od.product_id, od.product_attribute_id, '
            . 'od.product_name, od.product_reference '
            . 'FROM `' . _DB_PREFIX_ . 'order_slip_detail` osd '
            . 'INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail = osd.id_order_detail '
            . 'WHERE osd.id_order_slip = ' . (int) $slipId
        ) ?: [];

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = [
                'id' => $this->productId($row),
                'name' => (string) $row['product_name'],
                'count' => (float) $row['product_quantity'],
                'amount' => (float) $row['amount_tax_incl'],
                'productId' => (int) $row['product_id'],
                'attributeId' => (int) $row['product_attribute_id'],
                'service' => false,
            ];
        }

        foreach ($order->getOrderSlipsCollection() as $slip) {
            if ((int) $slip->id === $slipId) {
                $lines[] = $this->serviceLine('shipping', 'Доставка', (float) $slip->total_shipping_tax_incl);
            }
        }

        return $lines;
    }

    private function serviceLine(string $id, string $name, float $amount): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'count' => 1.0,
            'amount' => $amount,
            'productId' => null,
            'attributeId' => 0,
            'service' => true,
        ];
    }

    private function productId(array $product): string
    {
        $reference = trim((string) ($product['product_reference'] ?? ''));

        return $reference !== '' ? $reference : (string) $product['product_id'];
    }
}
