<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\AllianceOrder;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Entity\Hydrator\EntityHydrator;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Model\DateTime\DateTimeImmutableProvider;
use AlliancePay\Service\ConvertData\ConvertDataService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

/**
 * Class UpdateAllianceOrder.
 */
class UpdateAllianceOrder
{
    /**
     * @var ConvertDataService
     */
    private $convertDataService;

    /**
     * @var EntityHydrator
     */
    private $entityHydrator;

    /**
     * @var DateTimeImmutableProvider
     */
    private $dateTimeImmutableProvider;

    /**
     * @var AllianceLogger
     */
    private $allianceLogger;

    public function __construct(
        ConvertDataService  $convertDataService,
        EntityHydrator $entityHydrator,
        DateTimeImmutableProvider $dateTimeImmutableProvider,
        AllianceLogger $allianceLogger
    ) {
        $this->convertDataService = $convertDataService;
        $this->entityHydrator = $entityHydrator;
        $this->dateTimeImmutableProvider = $dateTimeImmutableProvider;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param array $data
     * @return AllianceOrder
     * @throws NonUniqueResultException
     */
    public function updateAllianceOrder(EntityManagerInterface $em, array $data): ?AllianceOrder
    {
        $repository = $em->getRepository(AllianceOrder::class);
        $hppOrderId = $this->getHppOrderIdFromCallbackData($data);

        if (empty($hppOrderId)) {
            return null;
        }

        $order = $repository->findByHppOrderId($hppOrderId);
        $convertedData = $this->convertDataService->camelToSnakeArrayKeys($data);
        $preparedData = $this->prepareCallbackData($order, $convertedData);
        $operation = $this->getPurchaseOperationFromCallbackData($preparedData);
        $this->entityHydrator->hydrate($order, $preparedData);
        $order->setUpdatedAt($this->dateTimeImmutableProvider->nowUtc());

        if (!empty($operation)) {
            $order->setOperationId($this->getPurchaseOperationIdFromCallbackData($operation));
            $order->setTransactionType($this->getPurchaseOperationTransactionTypeFromCallbackData($operation));
        }

        $order->setCallbackData($preparedData);
        $order->setIsCallbackReturned(true);
        $em->persist($order);

        try {
            $em->flush($order);
        } catch (Exception $exception) {
            $this->allianceLogger->error($exception->getMessage());
        }

        return $order;
    }

    /**
     * @param AllianceOrder $order
     * @param array $callbackData
     * @return array
     */
    private function prepareCallbackData(AllianceOrder $order, array $callbackData): array
    {
        $orderCallBackData = $order->getCallbackData();
        $callbackData = $this->prepareOperations($callbackData);

        if (!empty($orderCallBackData)) {
            $operations = [];
            foreach ($callbackData['operations'] as $operation) {
                if (!$this->checkIfAlreadyExistOperation($operation['operationId'], $orderCallBackData['operations'])) {
                    $operations[] = $operation;
                }
            }
            $callbackData['operations'] = array_merge($orderCallBackData['operations'], $operations);
        }

        return $callbackData;
    }

    /**
     * @param $callbackData
     * @return array|mixed
     */
    private function prepareOperations($callbackData)
    {
        if (isset($callbackData['operations'])) {
            return $callbackData;
        } elseif (isset($callbackData['operation'])) {
            $callbackData['operations'][] = $callbackData['operation'];
            unset($callbackData['operation']);
        }

        return $callbackData;
    }

    /**
     * @param $callbackData
     * @return mixed|null
     */
    private function getHppOrderIdFromCallbackData($callbackData)
    {
        return $callbackData['hppOrderId'] ?? null;
    }

    /**
     * @param $operation
     * @return string|null
     */
    private function getPurchaseOperationIdFromCallbackData($operation): ?string
    {
        if (!empty($operation['type']) && !empty($operation['operationId'])) {
            return $operation['operationId'];
        }

        return null;
    }

    /**
     * @param $operation
     * @return int|null
     */
    private function getPurchaseOperationTransactionTypeFromCallbackData($operation): ?int
    {
        if (!empty($operation['transactionType'])) {
            return (int) $operation['transactionType'];
        }

        return null;
    }

    /**
     * @param $callbackData
     * @return array
     */
    private function getPurchaseOperationFromCallbackData($callbackData): array
    {
        $purchaseOperation = [];
        $operationTypes = [
            Config::OPERATION_TYPE_PURCHASE,
            Config::OPERATION_TYPE_A2A,
            Config::OPERATION_TYPE_PREAUTH,
            Config::OPERATION_TYPE_COMPLETION,
        ];

        foreach ($callbackData['operations'] as $operation) {
            if (isset($operation['type']) && in_array($operation['type'], $operationTypes)) {
                $purchaseOperation = $operation;
            }
        }

        return $purchaseOperation;
    }

    /**
     * @param string $operationId
     * @param array $callbackOperations
     * @return bool
     */
    private function checkIfAlreadyExistOperation(string $operationId, array $callbackOperations): bool
    {
        foreach ($callbackOperations as $callbackOperation) {
            if ($callbackOperation['operationId'] === $operationId) {
                return true;
            }
        }

        return false;
    }
}
