<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use RuntimeException;

/**
 * Fits order lines to the amount of the fiscalized operation. Works in kopecks (integers), so that
 * Σ(count × unitPrice) equals the operation amount exactly.
 *
 * The operation amount is distributed over the lines in proportion to their raw amounts: this spreads cart
 * discounts, and also converts the shop currency into the bank currency when the amounts differ. The rounding
 * remainder goes to the last line (when its count is a whole number greater than one the line is split in two).
 */
class FiscalAmountDistributor
{
    /**
     * @param array[] $lines each: id, name, count (float), amount (float, whole line, shop currency), plus any extra keys
     * @param int $targetKop fiscalized amount in kopecks
     * @return array[] the same lines with unitKop (int) instead of amount, possibly with a split last line
     * @throws RuntimeException when the lines cannot be fitted to the amount
     */
    public function distribute(array $lines, int $targetKop): array
    {
        $lines = array_values(array_filter($lines, function ($line) {
            return (int) round($line['amount'] * 100) > 0 && $line['count'] > 0;
        }));

        if ($lines === []) {
            throw new RuntimeException('There are no items with a positive amount.');
        }

        if ($targetKop <= 0) {
            throw new RuntimeException('The operation amount must be positive.');
        }

        $weights = [];
        foreach ($lines as $line) {
            $weights[] = (int) round($line['amount'] * 100);
        }
        $weightSum = array_sum($weights);

        $result = [];
        $allocated = 0;
        $last = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $count = (float) $line['count'];

            if ($index === $last) {
                $share = $targetKop - $allocated;
                if ($share <= 0) {
                    throw new RuntimeException('The discount is larger than the price of the last item.');
                }

                foreach ($this->fitLastLine($line, $count, $share) as $part) {
                    $result[] = $part;
                }
                break;
            }

            $share = intdiv($targetKop * $weights[$index], $weightSum);
            $unitKop = (int) round($share / $count);

            if ($unitKop <= 0) {
                throw new RuntimeException('After the discount the price of "' . $line['name'] . '" is not positive.');
            }

            $allocated += (int) round($unitKop * $count);
            unset($line['amount']);
            $line['unitKop'] = $unitKop;
            $result[] = $line;
        }

        return $result;
    }

    /**
     * @return array[]
     */
    private function fitLastLine(array $line, float $count, int $share): array
    {
        unset($line['amount']);
        $isWhole = abs($count - round($count)) < 0.0000001;

        if ($isWhole) {
            $qty = (int) round($count);
            $base = intdiv($share, $qty);
            $remainder = $share - $base * $qty;

            if ($base <= 0) {
                throw new RuntimeException('After the discount the price of "' . $line['name'] . '" is not positive.');
            }

            if ($remainder === 0) {
                return [$line + ['unitKop' => $base]];
            }

            $cheap = $line;
            $cheap['count'] = (float) ($qty - $remainder);
            $cheap['unitKop'] = $base;
            $expensive = $line;
            $expensive['count'] = (float) $remainder;
            $expensive['unitKop'] = $base + 1;

            return $cheap['count'] > 0 ? [$cheap, $expensive] : [$expensive];
        }

        $unitKop = (int) round($share / $count);

        if ($unitKop <= 0 || (int) round($unitKop * $count) !== $share) {
            throw new RuntimeException('The amount of "' . $line['name'] . '" cannot be reconciled with the operation amount.');
        }

        return [$line + ['unitKop' => $unitKop]];
    }
}
