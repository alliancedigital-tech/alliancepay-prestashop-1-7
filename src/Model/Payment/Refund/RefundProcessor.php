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
        $operationId = $allianceOrder->getOperationId();

        $refundData = $this->prepareRefundData(
            $operationId,
            $this->prepareCoinAmount((float) $amount, $context->getComputingPrecision()),
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
                'transaction_id' => $refundEntity->getOperationId(),
            ];
        }

        if ($refundEntity->getStatus() === Config::REFUND_STATUS_FAIL) {
            $result['success'] = false;
            $result['message'] = $context->l('Refund service error.');

            $this->updateOrderStatus->updateOrderStatus(
                (int) $refundEntity->getOrderId(),
                (int) $this->config->getFailRefundState()
            );
        }

        return $result;
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
