<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Entity\AllianceFiscalization;
use AlliancePay\Service\Fiscalization\FiscalizationStatus;
use AlliancePay\Service\Fiscalization\FiscalizationStatusUpdater;
use AlliancePay\Service\Fiscalization\RefundSlipMatcher;
use PHPUnit\Framework\TestCase;

class FiscalizationStatusUpdaterTest extends TestCase
{
    private function entity(string $status): AllianceFiscalization
    {
        return (new AllianceFiscalization())->setStatus($status);
    }

    public function testPendingBecomesSuccess(): void
    {
        $entity = $this->entity(FiscalizationStatus::PENDING);

        $changed = (new FiscalizationStatusUpdater())->apply($entity, [
            'status' => 'SUCCESS',
            'fiscalNumber' => 'FN-1',
            'receiptUrl' => 'https://r.example/1',
            'fiscalizationDateTime' => '2026-09-03T10:39:09.540+03:00',
            'fiscalizationModificationDateTime' => '2026-09-03T10:39:10.000+03:00',
        ]);

        $this->assertTrue($changed);
        $this->assertSame('SUCCESS', $entity->getStatus());
        $this->assertSame('FN-1', $entity->getFiscalNumber());
        $this->assertSame('2026-09-03 07:39:09', $entity->getFiscalizationDateTime()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $entity->getFiscalizationDateTime()->getTimezone()->getName());
    }

    public function testSuccessIsNeverOverwritten(): void
    {
        $entity = $this->entity(FiscalizationStatus::SUCCESS)->setFiscalNumber('FN-1');

        $this->assertFalse((new FiscalizationStatusUpdater())->apply($entity, ['status' => 'PENDING']));
        $this->assertFalse((new FiscalizationStatusUpdater())->apply($entity, ['status' => 'FAIL', 'errorCode' => '541']));
        $this->assertSame('SUCCESS', $entity->getStatus());
        $this->assertNull($entity->getErrorCode());
    }

    public function testStaleDataIsIgnored(): void
    {
        $entity = $this->entity(FiscalizationStatus::PENDING);
        $updater = new FiscalizationStatusUpdater();

        $updater->apply($entity, ['status' => 'FAIL', 'errorCode' => '541', 'fiscalizationModificationDateTime' => '2026-09-03T10:00:10+03:00']);
        $this->assertFalse($updater->apply($entity, ['status' => 'PENDING', 'fiscalizationModificationDateTime' => '2026-09-03T10:00:05+03:00']));
        $this->assertSame('FAIL', $entity->getStatus());
        $this->assertSame('541', $entity->getErrorCode());
    }

    public function testFailDoesNotGoBackToPendingWithoutDates(): void
    {
        $entity = $this->entity(FiscalizationStatus::FAIL);

        $this->assertFalse((new FiscalizationStatusUpdater())->apply($entity, ['status' => 'PENDING']));
    }

    public function testToleratesMissingFieldsAndUnknownStatus(): void
    {
        $entity = $this->entity(FiscalizationStatus::PENDING);

        $this->assertFalse((new FiscalizationStatusUpdater())->apply($entity, []));
        $this->assertFalse((new FiscalizationStatusUpdater())->apply($entity, ['status' => 'WHATEVER']));
        $this->assertTrue((new FiscalizationStatusUpdater())->apply($entity, ['status' => 'PENDING']));
    }

    public function testActions(): void
    {
        $this->assertTrue(FiscalizationStatus::canFiscalize(null));
        $this->assertTrue(FiscalizationStatus::canFiscalize('ERROR'));
        $this->assertFalse(FiscalizationStatus::canFiscalize('PENDING'));
        $this->assertFalse(FiscalizationStatus::canFiscalize('FAIL'));
        $this->assertTrue(FiscalizationStatus::canRefresh('PENDING'));
        $this->assertTrue(FiscalizationStatus::canRefresh('FAIL'));
        $this->assertFalse(FiscalizationStatus::canRefresh('SUCCESS'));
        $this->assertFalse(FiscalizationStatus::canRefresh(null));
    }

    public function testRefundSlipMatcher(): void
    {
        $matched = (new RefundSlipMatcher())->match(
            [['operationId' => 'R1', 'coinAmount' => 5000], ['operationId' => 'R2', 'coinAmount' => 12000]],
            [['id' => 11, 'amount' => 120.0], ['id' => 10, 'amount' => 50.0]],
            1.0
        );

        $this->assertSame(['R1' => 10, 'R2' => 11], $matched);
    }

    public function testRefundSlipMatcherLoneRemainder(): void
    {
        $matched = (new RefundSlipMatcher())->match(
            [['operationId' => 'R1', 'coinAmount' => 9999]],
            [['id' => 5, 'amount' => 120.0]],
            1.0
        );

        $this->assertSame(['R1' => 5], $matched);
    }
}
