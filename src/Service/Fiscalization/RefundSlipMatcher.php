<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

/**
 * Pairs successful REFUND operations of the bank with the PrestaShop credit slips, as the module does not
 * store which slip a refund operation belongs to. The pairing is by amount, in order of appearance.
 */
class RefundSlipMatcher
{
    /**
     * @param array[] $refundOperations each: operationId, coinAmount (bank currency, kopecks)
     * @param array[] $slips each: id, amount (order currency, with shipping), oldest first
     * @param float $conversionRate bank amount = order amount × rate
     * @return array<string, int> operationId => slip id; operations without a slip are absent
     */
    public function match(array $refundOperations, array $slips, float $conversionRate): array
    {
        $rate = $conversionRate > 0 ? $conversionRate : 1.0;
        $unusedSlips = array_values($slips);
        $matched = [];
        $unmatchedOperations = [];

        foreach ($refundOperations as $operation) {
            $found = null;

            foreach ($unusedSlips as $index => $slip) {
                $slipCoin = (int) round($slip['amount'] * 100 * $rate);

                if (abs($slipCoin - (int) $operation['coinAmount']) <= 1) {
                    $found = $index;
                    break;
                }
            }

            if ($found === null) {
                $unmatchedOperations[] = $operation;
                continue;
            }

            $matched[(string) $operation['operationId']] = (int) $unusedSlips[$found]['id'];
            array_splice($unusedSlips, $found, 1);
        }

        // A full refund can be capped to the paid amount by the module, so the amounts may differ.
        if (count($unmatchedOperations) === 1 && count($unusedSlips) === 1) {
            $matched[(string) $unmatchedOperations[0]['operationId']] = (int) $unusedSlips[0]['id'];
        }

        return $matched;
    }
}
