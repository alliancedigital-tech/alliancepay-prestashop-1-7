<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Payment\Refund;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Entity\Factory\AllianceRefundFactory;
use AlliancePay\Entity\Hydrator\EntityHydrator;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Model\DateTime\DateTimeNormalizer;
use AlliancePay\Model\Payment\Processor\AbstractProcessor;
use AlliancePay\Service\ConvertData\ConvertDataService;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\Order\UpdateOrderStatus;
use AlliancePay\Service\Url\UrlProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use \Context;

/**
 * Class RefundProcessor.
 */
class RefundProcessor extends AbstractProcessor
{
    public const REFUND_DATA_FIELD_MERCHANT_REQUEST_ID = 'merchantRequestId';
    public const REFUND_DATA_FIELD_OPERATION_ID = 'operationId';
    public const REFUND_DATA_FIELD_MERCHANT_ID = 'merchantId';
    public const REFUND_DATA_FIELD_COIN_AMOUNT = 'coinAmount';
    public const REFUND_DATA_FIELD_NOTIFICATION_URL = 'notificationUrl';
    public const REFUND_DATA_FIELD_DATE = 'date';

    /**
     * @var AllianceRefundFactory
     */
    private $allianceRefundFactory;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConvertDataService
     */
    private $convertDataService;

    /**
     * @var EntityHydrator
     */
    private $entityHydrator;

    /**
     * @var DateTimeNormalizer
     */
    private $dateTimeNormalizer;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    /**
     * @var UpdateOrderStatus
     */
    private $updateOrderStatus;

    /**
     * @var AllianceLogger
     */
    private $allianceLogger;

    public function __construct(
        AllianceRefundFactory $allianceRefundFactory,
        HttpClient $httpClient,
        Config $config,
        ConvertDataService  $convertDataService,
        EntityHydrator $entityHydrator,
        DateTimeNormalizer $dateTimeNormalizer,
        UrlProvider $urlProvider,
        UpdateOrderStatus  $updateOrderStatus,
        AllianceLogger $allianceLogger
    ) {
        $this->allianceRefundFactory = $allianceRefundFactory;
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->convertDataService = $convertDataService;
        $this->entityHydrator = $entityHydrator;
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->urlProvider = $urlProvider;
        $this->updateOrderStatus = $updateOrderStatus;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param Context $context
     * @param EntityManagerInterface $em
     * @param float $amount
     * @param string $orderId
     * @return array|true[]
     * @throws NonUniqueResultException
     */
    public function refund(Context $context, EntityManagerInterface $em, float $amount, string $orderId): array
    {
        $allianceOrderRepository = $em->getRepository(\AlliancePay\Entity\AllianceOrder::class);
        $allianceOrder = $allianceOrderRepository->findByOrderId($orderId);

        if (empty($allianceOrder)) {
            throw new Exception('Alliance order not found');
        }

        $operationId = $allianceOrder->getOperationId();
        $precision = $context->getComputingPrecision();

        $coinAmountForRefund = $this->resolveCoinAmountForRefund(
            $allianceOrder,
            (float) $amount,
            $precision
        );

        $refundData = $this->prepareRefundData(
            $operationId,
            $coinAmountForRefund,
            $this->urlProvider->getCallbackUrl()
        );

        $refundData = $this->httpClient->refund($refundData);
        $convertedRefundData = $this->convertDataService->camelToSnakeArrayKeys($refundData);
        $convertedRefundData['creation_date_time']
            = $this->dateTimeNormalizer->formatCustomDate(
            $convertedRefundData['creation_date_time']
        );
        $convertedRefundData['modification_date_time']
            = $this->dateTimeNormalizer->formatCustomDate(
            $convertedRefundData['modification_date_time']
        );
        $refundEntity = $this->allianceRefundFactory->create();
        $this->entityHydrator->hydrate($refundEntity, $convertedRefundData);
        $refundEntity->setOrderId($allianceOrder->getOrderId());

        try {
            $em->persist($refundEntity);
            $em->flush($refundEntity);
        } catch (Exception $e) {
            $this->allianceLogger->error('Refund service error: ' . $e->getMessage());
        }

        if (!isset($refundData['type'])) {
            throw new Exception('Invalid refund response from gateway');
        }

        $result = [
            'success' => true,
        ];

        if ($refundEntity->getStatus() === Config::REFUND_STATUS_SUCCESS
            || $refundEntity->getStatus() === Config::REFUND_STATUS_PENDING
        ) {
            $result = [
                'success' => true,
                'transaction_id' => $refundEntity->getOperationId() ?? '',
            ];
        }

        if ($refundEntity->getStatus() === Config::REFUND_STATUS_FAIL) {
            $result['success'] = false;
            $result['message'] = \Module::getInstanceByName('alliancepay')->l('Refund service error.');

            $this->updateOrderStatus->updateOrderStatus(
                (int) $refundEntity->getOrderId(),
                (int) $this->config->getFailRefundState()
            );
        }

        return $result;
    }

    /**
     * @param AllianceOrder $allianceOrder
     * @return bool
     */
    public function assertRefundAllowed(AllianceOrder $allianceOrder): bool
    {
        return !(
            $allianceOrder->getHppPayType() === Config::HPP_PAY_TYPE_A2A
            && $allianceOrder->getTransactionType() === Config::TRANSACTION_TYPE_A2A
        );
    }

    /**
     * @param AllianceOrder $allianceOrder
     * @param float $amount
     * @param int $precision
     * @return int
     */
    private function resolveCoinAmountForRefund(
        AllianceOrder $allianceOrder,
        float $amount,
        int $precision
    ): int {
        $conversionRate = $allianceOrder->getConversionRate() ?? 1.0;

        $refundCoinAmountConverted = (int) round(
            $this->prepareCoinAmount($amount, $precision) * $conversionRate
        );

        $originalCoinAmount = $allianceOrder->getCoinAmount();

        if ($refundCoinAmountConverted >= $originalCoinAmount) {
            return $this->resolveFullRefundCoinAmount($allianceOrder);
        }

        return $refundCoinAmountConverted;
    }

    /**
     * @param AllianceOrder $allianceOrder
     * @return int
     */
    private function resolveFullRefundCoinAmount(AllianceOrder $allianceOrder): int
    {
        if ($allianceOrder->getHppPayType() === Config::HPP_PAY_TYPE_PREAUTH) {
            $callbackData = $allianceOrder->getCallbackData();

            foreach ($callbackData['operations'] ?? [] as $operation) {
                if (isset($operation['type'], $operation['coinAmount'])
                    && $operation['type'] === Config::OPERATION_TYPE_COMPLETION
                ) {
                    return (int) $operation['coinAmount'];
                }
            }
        }

        return $allianceOrder->getCoinAmount();
    }

    /**
     * @param string $operationId
     * @param int $amount
     * @param string $callbackUrl
     * @return array
     * @throws Exception
     */
    private function prepareRefundData(string $operationId, int $amount, string $callbackUrl): array
    {
        $preparedData = [];
        $preparedData[self::REFUND_DATA_FIELD_OPERATION_ID] = $operationId;
        $preparedData[self::REFUND_DATA_FIELD_COIN_AMOUNT] = $amount;
        $preparedData[self::REFUND_DATA_FIELD_MERCHANT_REQUEST_ID] = $this->generateMerchantRequestId();
        $preparedData[self::REFUND_DATA_FIELD_MERCHANT_ID] = $this->config->getMerchantId();
        $preparedData[self::REFUND_DATA_FIELD_DATE] = $this->dateTimeNormalizer->getRefundDate();
        $preparedData[self::REFUND_DATA_FIELD_NOTIFICATION_URL] = $callbackUrl;

        return $preparedData;
    }
}
