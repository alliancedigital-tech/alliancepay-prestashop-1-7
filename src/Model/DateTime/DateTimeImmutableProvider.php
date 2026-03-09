<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\DateTime;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Class DateTimeImmutableProvider.
 */
class DateTimeImmutableProvider
{
    /**
     * @throws Exception
     */
    public function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function defaultDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('01-01-1970 00:00:00', new DateTimeZone('UTC'));
    }

    /**
     * @throws Exception
     */
    public function fromString(string $date, ?DateTimeZone $tz = null): DateTimeImmutable
    {
        return new DateTimeImmutable($date, $tz ?? new DateTimeZone('UTC'));
    }
}
