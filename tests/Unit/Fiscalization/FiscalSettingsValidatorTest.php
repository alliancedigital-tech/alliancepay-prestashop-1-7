<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Service\Fiscalization\FiscalSettingsValidator;
use PHPUnit\Framework\TestCase;

class FiscalSettingsValidatorTest extends TestCase
{
    public function testValidSettingsAreNormalized(): void
    {
        $result = (new FiscalSettingsValidator())->validate([
            'taxCode' => ' 2 ',
            'classificationCode' => '2203000000',
            'exciseLabels' => "A1, B2\n,,A1 ",
            'measurementCode' => '0001',
        ], true, true);

        $this->assertSame([], $result['errors']);
        $this->assertSame(2, $result['values']['taxCode']);
        $this->assertSame(['A1', 'B2'], $result['values']['exciseLabels']);
    }

    public function testClassificationRequiredForTaxCode278(): void
    {
        foreach ([2, 7, 8] as $code) {
            $result = (new FiscalSettingsValidator())->validate(
                ['taxCode' => (string) $code, 'measurementCode' => '0001'],
                true,
                true
            );
            $this->assertNotEmpty($result['errors'], 'taxCode ' . $code);
        }
    }

    public function testClassificationNotRequiredForProductForm(): void
    {
        $result = (new FiscalSettingsValidator())->validate(['taxCode' => '2'], false, false);

        $this->assertSame([], $result['errors']);
    }

    public function testInvalidValues(): void
    {
        $result = (new FiscalSettingsValidator())->validate(
            ['taxCode' => 'x', 'classificationCode' => '12ab', 'measurementCode' => ''],
            true,
            true
        );

        $this->assertCount(3, $result['errors']);
    }

    public function testEmptyOptionalFieldsAllowed(): void
    {
        $result = (new FiscalSettingsValidator())->validate(['measurementCode' => '0001'], true, true);

        $this->assertSame([], $result['errors']);
        $this->assertNull($result['values']['taxCode']);
        $this->assertSame([], $result['values']['exciseLabels']);
    }
}
