<?php

declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Fiscalization;

use AlliancePay\Service\Fiscalization\FiscalAmountDistributor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FiscalAmountDistributorTest extends TestCase
{
    private function total(array $lines): int
    {
        $sum = 0;
        foreach ($lines as $line) {
            $sum += (int) round($line['unitKop'] * $line['count']);
        }

        return $sum;
    }

    private function line(string $id, float $count, float $amount): array
    {
        return ['id' => $id, 'name' => 'Item ' . $id, 'count' => $count, 'amount' => $amount];
    }

    public function testNoDiscountKeepsPrices(): void
    {
        $lines = (new FiscalAmountDistributor())->distribute(
            [$this->line('a', 2.0, 240.00), $this->line('b', 1.0, 50.50)],
            29050
        );

        $this->assertSame(12000, $lines[0]['unitKop']);
        $this->assertSame(5050, $lines[1]['unitKop']);
        $this->assertSame(29050, $this->total($lines));
    }

    public function testDiscountIsSpreadAndSumMatchesExactly(): void
    {
        $lines = (new FiscalAmountDistributor())->distribute(
            [$this->line('a', 3.0, 100.00), $this->line('b', 3.0, 33.33), $this->line('c', 1.0, 10.01)],
            12345
        );

        $this->assertSame(12345, $this->total($lines));
        foreach ($lines as $line) {
            $this->assertGreaterThan(0, $line['unitKop']);
        }
    }

    public function testRoundingRemainderSplitsLastLine(): void
    {
        $lines = (new FiscalAmountDistributor())->distribute([$this->line('a', 3.0, 10.00)], 1000);
        $this->assertCount(2, $lines);
        $this->assertSame(1000, $this->total($lines));
        $this->assertSame(2.0, $lines[0]['count'] + $lines[1]['count'] - 1.0);
    }

    public function testFractionalCounts(): void
    {
        $lines = (new FiscalAmountDistributor())->distribute(
            [$this->line('a', 1.5, 30.00), $this->line('s', 1.0, 10.00)],
            3500
        );

        $this->assertSame(3500, $this->total($lines));
    }

    public function testZeroAmountLinesAreDropped(): void
    {
        $lines = (new FiscalAmountDistributor())->distribute(
            [$this->line('a', 1.0, 10.00), $this->line('shipping', 1.0, 0.0)],
            1000
        );

        $this->assertCount(1, $lines);
    }

    public function testCurrencyConversionScalesPrices(): void
    {
        // 10 USD item paid as 41000 kopecks
        $lines = (new FiscalAmountDistributor())->distribute([$this->line('a', 1.0, 10.00)], 41000);

        $this->assertSame(41000, $lines[0]['unitKop']);
    }

    public function testDiscountLargerThanItemIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new FiscalAmountDistributor())->distribute(
            [$this->line('a', 1.0, 1000.00), $this->line('b', 1.0, 1.00)],
            1
        );
    }

    public function testEmptyLinesAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new FiscalAmountDistributor())->distribute([], 100);
    }
}
