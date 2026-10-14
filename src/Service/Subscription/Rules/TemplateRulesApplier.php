<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Rules;

/**
 * Class TemplateRulesApplier.
 */
class TemplateRulesApplier
{
    public const STAGE_TRIAL = 'TRIAL';
    public const STAGE_PAID = 'PAID';
    public const STAGE_TYPES = [self::STAGE_TRIAL, self::STAGE_PAID];

    /**
     * @param array $templateRows
     * @param int $coinAmount price in minor units
     * @param string $currencyCode numeric ISO 4217 code
     * @return array rules (not yet normalized)
     */
    public function apply(array $templateRows, int $coinAmount, string $currencyCode): array
    {
        $rules = [];

        foreach ($templateRows as $row) {
            $isTrial = strtoupper((string) ($row['type'] ?? self::STAGE_PAID)) === self::STAGE_TRIAL;

            $rules[] = [
                'paymentNumberFrom' => $row['paymentNumberFrom'] ?? null,
                'paymentNumberTo' => $row['paymentNumberTo'] ?? null,
                'intervalValue' => $row['intervalValue'] ?? null,
                'intervalUnit' => $row['intervalUnit'] ?? null,
                'coinAmount' => $isTrial ? 0 : $coinAmount,
                'currency' => $currencyCode,
            ];
        }

        return $rules;
    }
}
