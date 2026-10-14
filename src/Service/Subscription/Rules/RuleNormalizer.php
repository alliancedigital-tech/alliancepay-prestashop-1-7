<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Rules;

use AlliancePay\Config\SubscriptionConfig;

/**
 * Class RuleNormalizer.
 */
class RuleNormalizer
{
    /**
     * @param array $rules
     * @return array
     */
    public function normalize(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $item = [];

            foreach (SubscriptionConfig::RULE_REQUIRED_FIELDS as $field) {
                $item[$field] = $this->normalizeValue($field, $rule[$field] ?? null);
            }

            $normalized[] = $item;
        }

        usort($normalized, function (array $a, array $b): int {
            return (int) $a['paymentNumberFrom'] <=> (int) $b['paymentNumberFrom'];
        });

        return $normalized;
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return int|string|null
     */
    private function normalizeValue(string $field, $value)
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        switch ($field) {
            case 'paymentNumberFrom':
            case 'paymentNumberTo':
            case 'intervalValue':
            case 'coinAmount':
                return is_numeric($value) ? (int) $value : $value;
            case 'intervalUnit':
                return strtoupper(trim((string) $value));
            default:
                return trim((string) $value);
        }
    }
}
