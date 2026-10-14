<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Rules;

use AlliancePay\Service\Subscription\Rules\RuleNormalizer;
use AlliancePay\Service\Subscription\Rules\RuleValidator;
use PHPUnit\Framework\TestCase;

class RuleValidatorTest extends TestCase
{
    private function rule(array $override = []): array
    {
        return array_merge([
            'paymentNumberFrom' => 1,
            'paymentNumberTo' => 1,
            'intervalValue' => 1,
            'intervalUnit' => 'MONTH',
            'coinAmount' => 0,
            'currency' => '980',
        ], $override);
    }

    private function validate(array $rules): array
    {
        return (new RuleValidator())->validate((new RuleNormalizer())->normalize($rules));
    }

    public function testValidTrialPlusPaidSchedule(): void
    {
        $errors = $this->validate([
            $this->rule(),
            $this->rule(['paymentNumberFrom' => 2, 'paymentNumberTo' => 999, 'coinAmount' => 19900]),
        ]);

        $this->assertSame([], $errors);
    }

    public function testZeroAmountIsNotTreatedAsMissing(): void
    {
        $this->assertSame([], $this->validate([$this->rule(['coinAmount' => 0])]));
    }

    public function testEmptyRulesAreRejected(): void
    {
        $this->assertNotEmpty($this->validate([]));
    }

    /**
     * @dataProvider requiredFields
     */
    public function testEveryRequiredFieldIsReportedByRuleAndName(string $field): void
    {
        $rule = $this->rule();
        unset($rule[$field]);

        $errors = $this->validate([$rule]);

        $this->assertCount(1, $errors);
        $this->assertSame($field, $errors[0]['params']['%field%']);
        $this->assertSame(1, $errors[0]['params']['%rule%']);
    }

    public function requiredFields(): array
    {
        return [
            ['paymentNumberFrom'], ['paymentNumberTo'], ['intervalValue'],
            ['intervalUnit'], ['coinAmount'], ['currency'],
        ];
    }

    public function testBlankStringCountsAsMissing(): void
    {
        $this->assertNotEmpty($this->validate([$this->rule(['currency' => '  '])]));
    }

    public function testGapBetweenRangesIsRejected(): void
    {
        $errors = $this->validate([
            $this->rule(),
            $this->rule(['paymentNumberFrom' => 3, 'paymentNumberTo' => 10, 'coinAmount' => 100]),
        ]);

        $this->assertNotEmpty($errors);
    }

    public function testFirstRangeMustStartAtOne(): void
    {
        $this->assertNotEmpty($this->validate([$this->rule(['paymentNumberFrom' => 2, 'paymentNumberTo' => 5])]));
    }

    public function testUnknownIntervalUnitAndBadCurrencyAreRejected(): void
    {
        $this->assertNotEmpty($this->validate([$this->rule(['intervalUnit' => 'FORTNIGHT'])]));
        $this->assertNotEmpty($this->validate([$this->rule(['currency' => 'UAH'])]));
    }

    public function testNegativeAmountAndReversedRangeAreRejected(): void
    {
        $this->assertNotEmpty($this->validate([$this->rule(['coinAmount' => -1])]));
        $this->assertNotEmpty($this->validate([$this->rule(['paymentNumberFrom' => 1, 'paymentNumberTo' => 0])]));
    }

    public function testNonNumericAmountIsRejectedAsNotInteger(): void
    {
        $errors = $this->validate([$this->rule(['coinAmount' => 'abc'])]);

        $this->assertNotEmpty($errors);
    }
}
