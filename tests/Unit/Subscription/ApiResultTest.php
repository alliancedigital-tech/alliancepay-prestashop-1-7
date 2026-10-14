<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Subscription;

use AlliancePay\Service\Subscription\Api\ApiResult;
use AlliancePay\Service\Subscription\SubscriptionDiagnostics;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Logger\AllianceLogger;
use PHPUnit\Framework\TestCase;

class ApiResultTest extends TestCase
{
    public function testSuccessfulResponse(): void
    {
        $result = ApiResult::fromResponse([
            'status_code' => 200, 'body' => '{}', 'data' => ['planId' => 'P1', 'versionId' => 'V1'], 'error' => null,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('P1', $result->get('planId'));
        $this->assertNull($result->getError());
    }

    public function testHttpErrorKeepsProviderReasonFromBody(): void
    {
        $result = ApiResult::fromResponse([
            'status_code' => 422, 'body' => '', 'data' => ['msgCode' => 'NotNull', 'msgText' => 'rules[0].coinAmount'], 'error' => null,
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('422', (string) $result->getError());
        $this->assertStringContainsString('rules[0].coinAmount', (string) $result->getError());
    }

    public function testErrorMsgTypeWithHttp200IsAFailure(): void
    {
        $result = ApiResult::fromResponse([
            'status_code' => 200, 'body' => '', 'data' => ['msgType' => 'VALIDATION_ERROR', 'msgText' => 'bad'], 'error' => null,
        ]);

        $this->assertFalse($result->isSuccess());
    }

    public function testTransportErrorIsAFailure(): void
    {
        $result = ApiResult::fromResponse(['status_code' => 0, 'body' => '', 'data' => [], 'error' => 'Request failed (401).']);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('Request failed (401).', $result->getError());
    }

    public function testRawBodyIsUsedWhenNoStructuredReason(): void
    {
        $result = ApiResult::fromResponse(['status_code' => 500, 'body' => 'upstream exploded', 'data' => [], 'error' => null]);

        $this->assertStringContainsString('upstream exploded', (string) $result->getError());
    }

    public function testDiagnosticsAcceptsSingleOperationAndArray(): void
    {
        $diagnostics = new SubscriptionDiagnostics(
            $this->createMock(HttpClient::class),
            $this->createMock(AllianceLogger::class)
        );

        $this->assertSame([['operationId' => 'A']], $diagnostics->normalizeOperations(['operation' => ['operationId' => 'A']]));
        $this->assertSame(
            [['operationId' => 'A'], ['operationId' => 'B']],
            $diagnostics->normalizeOperations(['operations' => [['operationId' => 'A'], ['operationId' => 'B']]])
        );
        $this->assertSame([], $diagnostics->normalizeOperations(['orderStatus' => 'SUCCESS']));
    }
}
