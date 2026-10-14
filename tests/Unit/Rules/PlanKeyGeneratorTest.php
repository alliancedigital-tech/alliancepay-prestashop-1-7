<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Rules;

use AlliancePay\Service\Subscription\Rules\PlanKeyGenerator;
use AlliancePay\Service\Subscription\Rules\RuleNormalizer;
use AlliancePay\Service\Subscription\Rules\TemplateRulesApplier;
use PHPUnit\Framework\TestCase;

class PlanKeyGeneratorTest extends TestCase
{
    private $generator;

    protected function setUp(): void
    {
        $this->generator = new PlanKeyGenerator(new RuleNormalizer());
    }

    private function rules(): array
    {
        return [
            ['paymentNumberFrom' => 1, 'paymentNumberTo' => 1, 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'coinAmount' => 0, 'currency' => '980'],
            ['paymentNumberFrom' => 2, 'paymentNumberTo' => 999, 'intervalValue' => 1, 'intervalUnit' => 'MONTH', 'coinAmount' => 19900, 'currency' => '980'],
        ];
    }

    public function testKeyIsStableAcrossOrderTypesAndKeyOrder(): void
    {
        $base = $this->generator->generate('M1', 10, null, 'CAT', $this->rules());

        $reversed = array_reverse($this->rules());
        $stringy = array_map(function (array $r) {
            return array_map('strval', array_reverse($r, true));
        }, $this->rules());

        $this->assertSame($base, $this->generator->generate('M1', 10, null, 'CAT', $reversed));
        $this->assertSame($base, $this->generator->generate('M1', 10, null, 'CAT', $stringy));
    }

    public function testKeyChangesWithAnyIdentityPart(): void
    {
        $base = $this->generator->generate('M1', 10, null, 'CAT', $this->rules());

        $this->assertNotSame($base, $this->generator->generate('M2', 10, null, 'CAT', $this->rules()));
        $this->assertNotSame($base, $this->generator->generate('M1', 11, null, 'CAT', $this->rules()));
        $this->assertNotSame($base, $this->generator->generate('M1', 10, 5, 'CAT', $this->rules()));
        $this->assertNotSame($base, $this->generator->generate('M1', 10, null, 'OTHER', $this->rules()));

        $changed = $this->rules();
        $changed[1]['coinAmount'] = 20000;
        $this->assertNotSame($base, $this->generator->generate('M1', 10, null, 'CAT', $changed));
    }

    public function testKeyIsSha256Hex(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->generator->generate('M1', 10, null, 'CAT', $this->rules()));
    }

    public function testTemplateApplierBuildsTrialAndPaidStages(): void
    {
        $rules = (new TemplateRulesApplier())->apply([
            ['type' => 'TRIAL', 'paymentNumberFrom' => 1, 'paymentNumberTo' => 1, 'intervalValue' => 14, 'intervalUnit' => 'DAY'],
            ['type' => 'PAID', 'paymentNumberFrom' => 2, 'paymentNumberTo' => 999, 'intervalValue' => 1, 'intervalUnit' => 'MONTH'],
        ], 19900, '980');

        $this->assertSame(0, $rules[0]['coinAmount']);
        $this->assertSame(19900, $rules[1]['coinAmount']);
        $this->assertSame('980', $rules[1]['currency']);
        $this->assertArrayNotHasKey('type', $rules[0]);
    }
}
