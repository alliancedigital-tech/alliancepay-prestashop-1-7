<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\DateTime;

use DateTime;
use DateTimeZone;
use Exception;
use PrestaShop\PrestaShop\Adapter\Configuration;

/**
 * Class DateTimeNormalizer.
 */
class DateTimeNormalizer
{
    public const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public const REFUND_DATE_FORMAT = 'Y-m-d H:i:s.vP';

    public const BIRTHDAY_INPUT_FORMAT  = 'Y-m-d';

    public const BIRTHDAY_OUTPUT_FORMAT = 'd.m.Y';

    /**
     * @var Configuration
     */
    private $config;

    public function __construct(
        Configuration $config
    ) {
        $this->config = $config;
    }

    /**
     * @throws Exception
     */
    public function formatCustomDate(string $inputDate): string
    {
        $cleanMilliseconds = preg_replace('/\.\d{3}$/', '', $inputDate);
        $normalized = str_replace('.', '-', $cleanMilliseconds);
        $timeZone = $this->config->get('PS_TIMEZONE');
        $date = DateTime::createFromFormat(
            self::DATE_TIME_FORMAT,
            $normalized,
            new DateTimeZone($timeZone)
        );

        return $date->format(self::DATE_TIME_FORMAT);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getRefundDate(): string
    {
        $date = new DateTime('now', new DateTimeZone($this->config->get('PS_TIMEZONE')));

        return preg_replace(
            '/(\.\d{2})\d/',
            '$1',
            $date->format(self::REFUND_DATE_FORMAT)
        );
    }

    /**
     * @param string $inputDate
     * @return string|null
     */
    public function formatBirthday(string $inputDate): ?string
    {
        $date = DateTime::createFromFormat(self::BIRTHDAY_INPUT_FORMAT, $inputDate);

        if ($date === false) {
            return null;
        }

        $errors = DateTime::getLastErrors();
        if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
            return null;
        }

        return $date->format(self::BIRTHDAY_OUTPUT_FORMAT);
    }
}
