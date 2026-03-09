<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Payment\Processor;

use Tools;

/**
 * Class PaymentAbstract.
 */
abstract class AbstractProcessor
{
    /**
     * @return string
     */
    public function generateMerchantRequestId(): string
    {
        return uniqid();
    }

    /**
     * @param float $amount
     * @param $precision
     * @return int
     */
    public function prepareCoinAmount(float $amount, $precision)
    {
        $roundedAmount = Tools::ps_round($amount, $precision);

        return (int) round($roundedAmount * 100);
    }
}
