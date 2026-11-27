<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Entity\AllianceProductFiscal;
use AlliancePay\Service\Fiscalization\FiscalAttributeResolver;
use PHPUnit\Framework\TestCase;

class FiscalAttributeResolverTest extends TestCase
{
    private $defaults = [
        'taxCode' => 5,
        'classificationCode' => '1111111111',
        'exciseLabels' => ['D1'],
        'measurementCode' => '0001',
    ];

    public function testDefaultsWhenNoRows(): void
    {
        $result = (new FiscalAttributeResolver())->resolveFromRows(null, null, $this->defaults);

        $this->assertSame($this->defaults, $result);
    }

    public function testMixedProductAndDefaults(): void
    {
        $row = (new AllianceProductFiscal())->setIdProduct(1)->setTaxCode(2);

        $result = (new FiscalAttributeResolver())->resolveFromRows(null, $row, $this->defaults);

        $this->assertSame(2, $result['taxCode']);
        $this->assertSame('1111111111', $result['classificationCode']);
        $this->assertSame(['D1'], $result['exciseLabels']);
        $this->assertSame('0001', $result['measurementCode']);
    }

    public function testCombinationFallsBackToProduct(): void
    {
        $product = (new AllianceProductFiscal())->setIdProduct(1)->setTaxCode(2)->setMeasurementCode('0009')
            ->setExciseLabels(['P1']);
        $combination = (new AllianceProductFiscal())->setIdProduct(1)->setIdProductAttribute(7)->setTaxCode(7);

        $result = (new FiscalAttributeResolver())->resolveFromRows($combination, $product, $this->defaults);

        $this->assertSame(7, $result['taxCode']);
        $this->assertSame('0009', $result['measurementCode']);
        $this->assertSame(['P1'], $result['exciseLabels']);
    }

    public function testEmptyStringsFallBack(): void
    {
        $row = (new AllianceProductFiscal())->setIdProduct(1)->setClassificationCode('')->setMeasurementCode('');

        $result = (new FiscalAttributeResolver())->resolveFromRows(null, $row, $this->defaults);

        $this->assertSame('1111111111', $result['classificationCode']);
        $this->assertSame('0001', $result['measurementCode']);
    }
}
