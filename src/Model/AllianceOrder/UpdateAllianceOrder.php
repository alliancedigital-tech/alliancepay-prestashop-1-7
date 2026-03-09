<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\AllianceOrder;

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
        $this->entityHydrator->hydrate($order, $preparedData);
        $order->setUpdatedAt($this->dateTimeImmutableProvider->nowUtc());
        $order->setOperationId($this->getPurchaseOperationIdFromCallbackData($preparedData));
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
     * @param $callbackData
     * @return string
     */
    private function getPurchaseOperationIdFromCallbackData($callbackData): string
    {
        $operationId = '';

        foreach ($callbackData['operations'] as $operation) {
            if (isset($operation['type'])
                && $operation['type'] == 'PURCHASE'
                && !empty($operation['operationId'])
            ) {
                $operationId = $operation['operationId'];
            }
        }
        return $operationId;
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
