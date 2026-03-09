<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Callback;


use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Entity\Hydrator\EntityHydrator;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Model\AllianceOrder\UpdateAllianceOrder;
use AlliancePay\Service\ConvertData\ConvertDataService;
use AlliancePay\Model\DateTime\DateTimeImmutableProvider;
use AlliancePay\Service\Order\UpdateOrderStatus;
use Context;
use DateTimeImmutable;
use Exception;

/**
 * Class CallBackProcessor.
 */
class CallbackProcessor
{
    /**
     * @var UpdateAllianceOrder
     */
    private $updateAllianceOrder;

    /**
     * @var UpdateOrderStatus
     */
    private $updateOrderStatus;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AllianceLogger
     */
    private $allianceLogger;

    public function __construct(
        UpdateAllianceOrder $updateAllianceOrder,
        UpdateOrderStatus  $updateOrderStatus,
        Config $config,
        AllianceLogger $allianceLogger
    ) {
        $this->updateAllianceOrder = $updateAllianceOrder;
        $this->updateOrderStatus = $updateOrderStatus;
        $this->config = $config;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param $callbackData
     * @return void
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException\
     */
    public function processCallback($em, $callbackData): void
    {
        try {
            $order = $this->updateAllianceOrder->updateAllianceOrder($em, $callbackData);
        } catch (Exception $exception) {
            $this->allianceLogger->error($exception->getMessage());
        }

        if ($callbackData['orderStatus'] == Config::SUCCESS_ORDER_STATUS) {
            $newStateId = $this->ifRefundsSameAsOrderAmount($order->getCoinAmount(), $order->getCallbackData())
                ? (int) $this->config->getSuccessRefundState() : (int) $this->config->getSuccessOrderState();
        } elseif ($callbackData['orderStatus'] == Config::FAIL_ORDER_STATUS) {
            $newStateId = (int) $this->config->getFailOrderState();
        }

        if (isset($newStateId)) {
            $this->updateOrderStatus->updateOrderStatus(
                $order->getOrderId(),
                $newStateId
            );
        }
    }

    /**
     * @param int $coinAmount
     * @param array $callbackData
     * @return bool
     */
    private function ifRefundsSameAsOrderAmount(int $coinAmount, array $callbackData): bool
    {
        $refundAmount = 0;

        foreach ($callbackData['operations'] as $operation) {
            if ($operation['type'] == 'REFUND' && $operation['status'] == Config::REFUND_STATUS_SUCCESS) {
                $refundAmount += $operation['coinAmount'];
            }
        }

        return $coinAmount === $refundAmount;
    }
}
