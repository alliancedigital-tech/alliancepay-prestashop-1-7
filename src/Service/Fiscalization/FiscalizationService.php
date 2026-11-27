<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceFiscalization;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Repository\FiscalizationRepository;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\RequestIdentification\GenerateRequestIdentification;
use AlliancePay\Service\Url\UrlProvider;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

/**
 * Sends fiscalization requests and stores their results. Methods never throw: the result is
 * ['success' => bool, 'message' => string (English, translated by the caller), 'details' => string[]].
 */
class FiscalizationService
{
    private const BUSINESS_ERRORS = [
        'missing_required_field' => 'A required field is missing in the request',
        'invalid_format' => 'A field of the request has an invalid format',
        'payment_not_found' => 'The payment operation was not found',
        'original_receipt_not_found' => 'The receipt of the original purchase was not found, fiscalize the purchase first',
    ];

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    /**
     * @var FiscalizationRequestBuilder
     */
    private $requestBuilder;

    /**
     * @var FiscalOrderDataProvider
     */
    private $dataProvider;

    /**
     * @var FiscalOperationProvider
     */
    private $operationProvider;

    /**
     * @var FiscalizationStatusUpdater
     */
    private $statusUpdater;

    /**
     * @var FiscalOrderNotifier
     */
    private $notifier;

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(
        HttpClient $httpClient,
        Config $config,
        UrlProvider $urlProvider,
        FiscalizationRequestBuilder $requestBuilder,
        FiscalOrderDataProvider $dataProvider,
        FiscalOperationProvider $operationProvider,
        FiscalizationStatusUpdater $statusUpdater,
        FiscalOrderNotifier $notifier,
        AllianceLogger $logger
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->urlProvider = $urlProvider;
        $this->requestBuilder = $requestBuilder;
        $this->dataProvider = $dataProvider;
        $this->operationProvider = $operationProvider;
        $this->statusUpdater = $statusUpdater;
        $this->notifier = $notifier;
        $this->logger = $logger;
    }

    /**
     * Operations of the order with their fiscalization records, for the order page.
     *
     * @return array[] each: operationId, type, coinAmount, record (AllianceFiscalization|null), canFiscalize, canRefresh
     */
    public function getOverview(EntityManagerInterface $em, int $orderId): array
    {
        $repository = $this->getRepository($em);
        $rows = [];

        foreach ($this->operationProvider->getOperations($em, $orderId) as $operation) {
            $record = $repository->findByOperationId($operation['operationId']);
            $status = $record ? $record->getStatus() : null;

            $rows[] = $operation + [
                'record' => $record,
                'canFiscalize' => FiscalizationStatus::canFiscalize($status),
                'canRefresh' => FiscalizationStatus::canRefresh($status),
            ];
        }

        return $rows;
    }

    /**
     * @return array{success: bool, message: string, details: string[]}
     */
    public function fiscalize(EntityManagerInterface $em, int $orderId, string $operationId): array
    {
        try {
            if (!$this->config->isFiscalizationEnabled()) {
                return $this->result(false, 'Fiscalization is disabled in the module settings.');
            }

            $repository = $this->getRepository($em);
            $record = $repository->findByOperationId($operationId);

            if ($record !== null && !FiscalizationStatus::canFiscalize($record->getStatus())) {
                return $this->result(false, 'This operation is already fiscalized or being fiscalized.');
            }

            $operation = $this->operationProvider->findOperation($em, $orderId, $operationId);

            if ($operation === null) {
                return $this->result(false, 'The operation was not found or is not successful.');
            }

            try {
                $data = $this->dataProvider->getData($em, $orderId, $operation);
            } catch (RuntimeException $e) {
                return $this->result(false, 'Fiscalization request was not sent.', [$e->getMessage()]);
            }

            $built = $this->requestBuilder->build($em, [
                'merchantId' => $this->config->getMerchantId(),
                'operationId' => $operationId,
                'merchantRequestId' => $record ? $record->getMerchantRequestId() : $this->generateUuid(),
                'receiptComment' => $data['comment'],
                'notificationUrl' => $this->urlProvider->getFiscalizationCallbackUrl(),
                'lines' => $data['lines'],
                'targetKop' => $operation['coinAmount'],
                'defaults' => $this->config->getFiscalDefaults(),
            ]);

            if ($built['payload'] === null) {
                return $this->result(false, 'Fiscalization request was not sent.', $built['errors']);
            }

            if ($record === null) {
                $record = (new AllianceFiscalization())
                    ->setIdOrder($orderId)
                    ->setIdShop($data['shopId'])
                    ->setOperationId($operationId)
                    ->setOperationType($operation['type'])
                    ->setMerchantRequestId($built['payload']['merchantRequestId'])
                    ->setStatus(FiscalizationStatus::ERROR);
            }

            $record->setReceiptComment($data['comment'])->setRequestPayload($built['payload']);

            return $this->send($em, $record, false);
        } catch (Throwable $e) {
            $this->logger->error('Fiscalization failed: ' . $e->getMessage());

            return $this->result(false, 'Fiscalization failed.', [$e->getMessage()]);
        }
    }

    /**
     * Re-sends the stored request: Ecom is idempotent by operationId and answers with the current state.
     *
     * @return array{success: bool, message: string, details: string[]}
     */
    public function refresh(EntityManagerInterface $em, int $orderId, string $operationId): array
    {
        try {
            $record = $this->getRepository($em)->findByOperationId($operationId);

            if ($record === null || $record->getIdOrder() !== $orderId) {
                return $this->result(false, 'Fiscalization record was not found.');
            }

            if (!FiscalizationStatus::canRefresh($record->getStatus())) {
                return $this->result(false, 'The status of this fiscalization cannot be refreshed.');
            }

            return $this->send($em, $record, true);
        } catch (Throwable $e) {
            $this->logger->error('Fiscalization refresh failed: ' . $e->getMessage());

            return $this->result(false, 'Fiscalization failed.', [$e->getMessage()]);
        }
    }

    /**
     * Fiscalizes every operation of the order that has not been fiscalized yet, when the automatic mode is on.
     * The work runs after the response of the current request, so that it cannot slow down or break it.
     */
    public function scheduleAutoForOrder(EntityManagerInterface $em, int $orderId): void
    {
        if ($orderId <= 0 || !$this->config->isFiscalizationEnabled() || !$this->config->isFiscalizationAuto()) {
            return;
        }

        register_shutdown_function(function () use ($em, $orderId) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            $this->autoFiscalizeOrder($em, $orderId);
        });
    }

    public function autoFiscalizeOrder(EntityManagerInterface $em, int $orderId): void
    {
        try {
            if (!$this->config->isFiscalizationEnabled() || !$this->config->isFiscalizationAuto()) {
                return;
            }

            foreach ($this->getOverview($em, $orderId) as $row) {
                // Operations with a record (even a failed one) are left to the administrator.
                if ($row['record'] === null) {
                    $this->fiscalize($em, $orderId, $row['operationId']);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error('Automatic fiscalization failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{success: bool, message: string, details: string[]}
     */
    private function send(EntityManagerInterface $em, AllianceFiscalization $record, bool $isRefresh): array
    {
        $previousStatus = $record->getStatus();
        $result = $this->httpClient->createFiscalization($record->getRequestPayload());
        $statusCode = (int) $result['status_code'];
        $data = is_array($result['data']) ? $result['data'] : [];

        $this->logger->info('Fiscalization response', [
            'operationId' => $record->getOperationId(),
            'statusCode' => $statusCode,
            'error' => $result['error'],
        ]);

        $isAnswer = $result['error'] === null && $statusCode >= 200 && $statusCode < 300 && isset($data['status']);

        if ($isAnswer) {
            $record->setResponsePayload($data);
            $this->statusUpdater->apply($record, $data);
            $this->getRepository($em)->save($record);
            $this->notifier->noteStatusChange($record, $previousStatus);

            return $this->resultForRecord($record);
        }

        [$code, $description] = $this->describeFailure($statusCode, $data, $result);

        if ($isRefresh) {
            // The stored remote status stays, a failed refresh must not lose it.
            return $this->result(false, 'The status could not be refreshed.', [$description]);
        }

        $record->setStatus(FiscalizationStatus::ERROR)
            ->setErrorCode($code)
            ->setErrorDescription($description)
            ->setUpdatedAt(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->getRepository($em)->save($record);

        return $this->result(false, 'Fiscalization request failed.', [$description]);
    }

    /**
     * @return array{0: string, 1: string} error code and description
     */
    private function describeFailure(int $statusCode, array $data, array $result): array
    {
        if ($statusCode === 400 && !empty($data['msgCode'])) {
            $msgCode = (string) $data['msgCode'];
            $description = self::BUSINESS_ERRORS[$msgCode] ?? 'Request rejected';
            $attrs = $data['msgAttrs'] ?? null;

            if (!empty($attrs)) {
                $description .= ': ' . (is_array($attrs) ? implode(', ', array_map('strval', $attrs)) : (string) $attrs);
            }

            return [$msgCode, $description];
        }

        if ($statusCode === 0 || $result['error'] !== null) {
            return ['NETWORK_ERROR', (string) ($result['error'] ?: 'No answer from the bank')];
        }

        return ['HTTP_' . $statusCode, mb_substr('Unexpected answer of the bank: ' . $result['body'], 0, 1000)];
    }

    /**
     * @return array{success: bool, message: string, details: string[]}
     */
    private function resultForRecord(AllianceFiscalization $record): array
    {
        switch ($record->getStatus()) {
            case FiscalizationStatus::SUCCESS:
                return $this->result(true, 'The receipt was fiscalized.');
            case FiscalizationStatus::FAIL:
                return $this->result(false, 'Fiscalization failed.', array_filter([$record->getErrorCode(), $record->getErrorDescription()]));
            default:
                return $this->result(true, 'Fiscalization request was sent, waiting for the result.');
        }
    }

    private function result(bool $success, string $message, array $details = []): array
    {
        return ['success' => $success, 'message' => $message, 'details' => array_values($details)];
    }

    private function generateUuid(): string
    {
        return GenerateRequestIdentification::generateRequestId();
    }

    private function getRepository(EntityManagerInterface $em): FiscalizationRepository
    {
        /** @var FiscalizationRepository $repository */
        $repository = $em->getRepository(AllianceFiscalization::class);

        return $repository;
    }
}
