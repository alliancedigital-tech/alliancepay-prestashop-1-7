<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

/**
 * Statuses of a fiscalization record and the actions allowed for each of them.
 */
class FiscalizationStatus
{
    public const PENDING = 'PENDING';
    public const SUCCESS = 'SUCCESS';
    public const FAIL = 'FAIL';
    /** Local status: the request did not produce an answer of the bank (network, 5xx, 400, validation). */
    public const ERROR = 'ERROR';

    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_REFUND = 'REFUND';

    /**
     * @param string|null $status null when there is no record yet
     */
    public static function canFiscalize(?string $status): bool
    {
        return $status === null || $status === self::ERROR;
    }

    public static function canRefresh(?string $status): bool
    {
        return $status === self::PENDING || $status === self::FAIL;
    }

    public static function isRemote(?string $status): bool
    {
        return in_array($status, [self::PENDING, self::SUCCESS, self::FAIL], true);
    }
}
