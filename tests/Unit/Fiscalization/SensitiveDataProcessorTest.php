<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Logger\SensitiveDataProcessor;
use PHPUnit\Framework\TestCase;

class SensitiveDataProcessorTest extends TestCase
{
    public function testMasksKeysTokensAndPem(): void
    {
        $jws = 'eyJhbGciOiJFUzI1NiJ9.eyJhIjoxfQ.c2lnbmF0dXJl';
        $record = (new SensitiveDataProcessor())([
            'message' => 'sent ' . $jws . " -----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----",
            'context' => ['operationId' => 'OP1', 'privateKey' => 'secret', 'nested' => ['Authorization' => 'Bearer x']],
            'extra' => [],
        ]);

        $this->assertStringNotContainsString($jws, $record['message']);
        $this->assertStringNotContainsString('abc', $record['message']);
        $this->assertSame('OP1', $record['context']['operationId']);
        $this->assertSame('***', $record['context']['privateKey']);
        $this->assertSame('***', $record['context']['nested']['Authorization']);
    }
}
