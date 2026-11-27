<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Config\Config;
use AlliancePay\Entity\AllianceFiscalization;
use AlliancePay\Entity\AllianceProductFiscal;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Repository\FiscalizationRepository;
use AlliancePay\Service\Callback\FiscalizationCallbackProcessor;
use AlliancePay\Service\Fiscalization\FiscalAmountDistributor;
use AlliancePay\Service\Fiscalization\FiscalAttributeResolver;
use AlliancePay\Service\Fiscalization\FiscalizationRequestBuilder;
use AlliancePay\Service\Fiscalization\FiscalizationService;
use AlliancePay\Service\Fiscalization\FiscalizationStatusUpdater;
use AlliancePay\Service\Fiscalization\FiscalOperationProvider;
use AlliancePay\Service\Fiscalization\FiscalOrderDataProvider;
use AlliancePay\Service\Fiscalization\FiscalOrderNotifier;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\Url\UrlProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FiscalizationServiceTest extends TestCase
{
    /** @var EntityManager */
    private $em;

    /** @var HttpClient|MockObject */
    private $http;

    /** @var Config|MockObject */
    private $config;

    /** @var FiscalOperationProvider|MockObject */
    private $operations;

    /** @var FiscalOrderDataProvider|MockObject */
    private $data;

    /** @var FiscalOrderNotifier|MockObject */
    private $notifier;

    /** @var FiscalizationService */
    private $service;

    protected function setUp(): void
    {
        $config = Setup::createAnnotationMetadataConfiguration([__DIR__ . '/../../../src/Entity'], true, null, null, false);
        $this->em = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(AllianceFiscalization::class),
            $this->em->getClassMetadata(AllianceProductFiscal::class),
        ]);

        $this->http = $this->createMock(HttpClient::class);
        $this->config = $this->createMock(Config::class);
        $this->config->method('isFiscalizationEnabled')->willReturn(true);
        $this->config->method('isFiscalizationAuto')->willReturn(true);
        $this->config->method('getMerchantId')->willReturn('M-1');
        $this->config->method('getFiscalDefaults')->willReturn([
            'taxCode' => null, 'classificationCode' => null, 'exciseLabels' => [], 'measurementCode' => '0001',
        ]);

        $urls = $this->createMock(UrlProvider::class);
        $urls->method('getFiscalizationCallbackUrl')->willReturn('https://shop.example/fiscalcallback');

        $this->operations = $this->createMock(FiscalOperationProvider::class);
        $this->operations->method('getOperations')->willReturn([
            ['operationId' => 'OP1', 'type' => 'PURCHASE', 'coinAmount' => 24000],
        ]);
        $this->operations->method('findOperation')->willReturn(
            ['operationId' => 'OP1', 'type' => 'PURCHASE', 'coinAmount' => 24000]
        );

        $this->data = $this->createMock(FiscalOrderDataProvider::class);
        $this->data->method('getData')->willReturn([
            'lines' => [[
                'id' => 'SKU1', 'name' => 'Item', 'count' => 2.0, 'amount' => 240.0,
                'productId' => 5, 'attributeId' => 0, 'service' => false,
            ]],
            'shopId' => 1,
            'comment' => 'PAYMENT_FOR_ORDER_ABC',
        ]);

        $this->notifier = $this->createMock(FiscalOrderNotifier::class);

        $this->service = new FiscalizationService(
            $this->http,
            $this->config,
            $urls,
            new FiscalizationRequestBuilder(new FiscalAmountDistributor(), new FiscalAttributeResolver()),
            $this->data,
            $this->operations,
            new FiscalizationStatusUpdater(),
            $this->notifier,
            $this->createMock(AllianceLogger::class)
        );
    }

    private function response(int $code, array $data, ?string $error = null): array
    {
        return ['status_code' => $code, 'body' => json_encode($data), 'data' => $data, 'error' => $error];
    }

    private function record(): ?AllianceFiscalization
    {
        /** @var FiscalizationRepository $repository */
        $repository = $this->em->getRepository(AllianceFiscalization::class);

        return $repository->findByOperationId('OP1');
    }

    public function testPendingResponseIsStoredAndRequestIsBuilt(): void
    {
        $sent = null;
        $this->http->method('createFiscalization')->willReturnCallback(function ($payload) use (&$sent) {
            $sent = $payload;

            return $this->response(200, ['status' => 'PENDING', 'bucketId' => 'B1']);
        });

        $result = $this->service->fiscalize($this->em, 10, 'OP1');

        $this->assertTrue($result['success']);
        $this->assertSame('M-1', $sent['merchantId']);
        $this->assertSame('OP1', $sent['operationId']);
        $this->assertSame('https://shop.example/fiscalcallback', $sent['notificationUrl']);
        $this->assertSame(120.0, $sent['products'][0]['unitPrice']);
        $this->assertSame('0001', $sent['products'][0]['measurementCode']);
        $this->assertArrayNotHasKey('taxCode', $sent['products'][0]);
        $this->assertSame('PENDING', $this->record()->getStatus());
        $this->assertSame('B1', $this->record()->getBucketId());
        $this->assertSame($sent, $this->record()->getRequestPayload());
    }

    public function testSuccessResponseStoresReceiptAndNotes(): void
    {
        $this->http->method('createFiscalization')->willReturn($this->response(200, [
            'status' => 'SUCCESS', 'fiscalNumber' => 'FN-7', 'receiptUrl' => 'https://r.example/7',
            'fiscalizationDateTime' => '2026-09-03T10:39:09.540+03:00',
        ]));
        $this->notifier->expects($this->once())->method('noteStatusChange');

        $result = $this->service->fiscalize($this->em, 10, 'OP1');

        $this->assertTrue($result['success']);
        $this->assertSame('FN-7', $this->record()->getFiscalNumber());
        $this->assertSame('https://r.example/7', $this->record()->getReceiptUrl());
    }

    public function testFailResponseIsStored(): void
    {
        $this->http->method('createFiscalization')->willReturn($this->response(200, [
            'status' => 'FAIL', 'errorCode' => '541', 'errorDescription' => 'Signing error',
        ]));

        $result = $this->service->fiscalize($this->em, 10, 'OP1');

        $this->assertFalse($result['success']);
        $this->assertSame('FAIL', $this->record()->getStatus());
        $this->assertSame('541', $this->record()->getErrorCode());
        $this->assertFalse($this->service->fiscalize($this->em, 10, 'OP1')['success'], 'FAIL cannot be re-fiscalized');
    }

    /**
     * @dataProvider businessErrors
     */
    public function testBusinessErrors400(string $msgCode): void
    {
        $this->http->method('createFiscalization')->willReturn(
            $this->response(400, ['msgCode' => $msgCode, 'msgAttrs' => ['operationId']])
        );

        $result = $this->service->fiscalize($this->em, 10, 'OP1');

        $this->assertFalse($result['success']);
        $this->assertSame('ERROR', $this->record()->getStatus());
        $this->assertSame($msgCode, $this->record()->getErrorCode());
        $this->assertStringContainsString('operationId', (string) $this->record()->getErrorDescription());
    }

    public function businessErrors(): array
    {
        return [['missing_required_field'], ['invalid_format'], ['payment_not_found'], ['original_receipt_not_found']];
    }

    public function testServerErrorAndTimeoutAreStoredAsErrorAndCanBeRetried(): void
    {
        $this->http->method('createFiscalization')->willReturnOnConsecutiveCalls(
            $this->response(503, []),
            $this->response(0, [], 'cURL error 28: timeout'),
            $this->response(200, ['status' => 'PENDING'])
        );

        $this->assertFalse($this->service->fiscalize($this->em, 10, 'OP1')['success']);
        $this->assertSame('HTTP_503', $this->record()->getErrorCode());
        $requestId = $this->record()->getMerchantRequestId();

        $this->assertFalse($this->service->fiscalize($this->em, 10, 'OP1')['success']);
        $this->assertSame('NETWORK_ERROR', $this->record()->getErrorCode());

        $this->assertTrue($this->service->fiscalize($this->em, 10, 'OP1')['success']);
        $this->assertSame('PENDING', $this->record()->getStatus());
        $this->assertSame($requestId, $this->record()->getMerchantRequestId(), 'the request id is kept');
    }

    public function testPendingRecordIsNotSentAgainButCanBeRefreshed(): void
    {
        $this->http->expects($this->exactly(2))->method('createFiscalization')->willReturnOnConsecutiveCalls(
            $this->response(200, ['status' => 'PENDING']),
            $this->response(200, ['status' => 'SUCCESS', 'fiscalNumber' => 'FN-1'])
        );

        $this->service->fiscalize($this->em, 10, 'OP1');
        $this->assertFalse($this->service->fiscalize($this->em, 10, 'OP1')['success']);

        $this->assertTrue($this->service->refresh($this->em, 10, 'OP1')['success']);
        $this->assertSame('SUCCESS', $this->record()->getStatus());
        $this->assertFalse($this->service->refresh($this->em, 10, 'OP1')['success'], 'SUCCESS is final');
    }

    public function testFailedRefreshKeepsRemoteStatus(): void
    {
        $this->http->method('createFiscalization')->willReturnOnConsecutiveCalls(
            $this->response(200, ['status' => 'PENDING']),
            $this->response(0, [], 'timeout')
        );

        $this->service->fiscalize($this->em, 10, 'OP1');
        $this->assertFalse($this->service->refresh($this->em, 10, 'OP1')['success']);
        $this->assertSame('PENDING', $this->record()->getStatus());
    }

    public function testDisabledFiscalizationSendsNothing(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFiscalizationEnabled')->willReturn(false);
        $service = new FiscalizationService(
            $this->http, $config, $this->createMock(UrlProvider::class),
            new FiscalizationRequestBuilder(new FiscalAmountDistributor(), new FiscalAttributeResolver()),
            $this->data, $this->operations, new FiscalizationStatusUpdater(), $this->notifier,
            $this->createMock(AllianceLogger::class)
        );
        $this->http->expects($this->never())->method('createFiscalization');

        $this->assertFalse($service->fiscalize($this->em, 10, 'OP1')['success']);
    }

    public function testLocalValidationBlocksTheRequest(): void
    {
        $this->em->persist((new AllianceProductFiscal())->setIdProduct(5)->setTaxCode(2));
        $this->em->flush();
        $this->http->expects($this->never())->method('createFiscalization');

        $result = $this->service->fiscalize($this->em, 10, 'OP1');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('UKTZED', implode(' ', $result['details']));
        $this->assertNull($this->record());
    }

    public function testMixedProductAndDefaultValuesGoToTheRequest(): void
    {
        $this->em->persist((new AllianceProductFiscal())->setIdProduct(5)->setTaxCode(2)->setClassificationCode('2203000000')
            ->setExciseLabels(['AATE123456']));
        $this->em->flush();
        $sent = null;
        $this->http->method('createFiscalization')->willReturnCallback(function ($payload) use (&$sent) {
            $sent = $payload;

            return $this->response(200, ['status' => 'PENDING']);
        });

        $this->service->fiscalize($this->em, 10, 'OP1');

        $product = $sent['products'][0];
        $this->assertSame(2, $product['taxCode']);
        $this->assertSame('2203000000', $product['classificationCode']);
        $this->assertSame(['AATE123456'], $product['exciseLabels']);
        $this->assertSame('0001', $product['measurementCode']);
    }

    public function testCallbackFlow(): void
    {
        $this->http->method('createFiscalization')->willReturn($this->response(200, ['status' => 'PENDING']));
        $this->service->fiscalize($this->em, 10, 'OP1');

        $processor = new FiscalizationCallbackProcessor(
            new FiscalizationStatusUpdater(),
            $this->notifier,
            $this->createMock(AllianceLogger::class)
        );

        $this->assertTrue($processor->process($this->em, [
            'operationId' => 'OP1', 'status' => 'SUCCESS', 'fiscalNumber' => 'FN-9', 'receiptUrl' => 'https://r.example/9',
        ]));
        $this->assertSame('SUCCESS', $this->record()->getStatus());
        $this->assertSame('SUCCESS', $this->record()->getCallbackPayload()['status']);

        // a repeated or late callback does not downgrade SUCCESS and creates no duplicates
        $processor->process($this->em, ['operationId' => 'OP1', 'status' => 'PENDING']);
        $this->assertSame('SUCCESS', $this->record()->getStatus());
        $this->assertCount(1, $this->em->getRepository(AllianceFiscalization::class)->findAll());

        $this->assertFalse($processor->process($this->em, ['operationId' => 'UNKNOWN', 'status' => 'SUCCESS']));
    }

    public function testCallbackFindsRecordByMerchantRequestId(): void
    {
        $this->http->method('createFiscalization')->willReturn($this->response(200, ['status' => 'PENDING']));
        $this->service->fiscalize($this->em, 10, 'OP1');
        $processor = new FiscalizationCallbackProcessor(
            new FiscalizationStatusUpdater(),
            $this->notifier,
            $this->createMock(AllianceLogger::class)
        );

        $this->assertTrue($processor->process($this->em, [
            'merchantRequestId' => $this->record()->getMerchantRequestId(), 'status' => 'FAIL', 'errorCode' => '541',
        ]));
        $this->assertSame('FAIL', $this->record()->getStatus());
    }

    public function testAutoFiscalizationSkipsOperationsWithRecords(): void
    {
        $this->http->expects($this->once())->method('createFiscalization')
            ->willReturn($this->response(200, ['status' => 'PENDING']));

        $this->service->autoFiscalizeOrder($this->em, 10);
        $this->service->autoFiscalizeOrder($this->em, 10);

        $this->assertSame('PENDING', $this->record()->getStatus());
    }

    public function testAutoFiscalizationDisabled(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFiscalizationEnabled')->willReturn(true);
        $config->method('isFiscalizationAuto')->willReturn(false);
        $service = new FiscalizationService(
            $this->http, $config, $this->createMock(UrlProvider::class),
            new FiscalizationRequestBuilder(new FiscalAmountDistributor(), new FiscalAttributeResolver()),
            $this->data, $this->operations, new FiscalizationStatusUpdater(), $this->notifier,
            $this->createMock(AllianceLogger::class)
        );
        $this->http->expects($this->never())->method('createFiscalization');

        $service->autoFiscalizeOrder($this->em, 10);
        $this->assertNull($this->record());
    }
}
