<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Logger;

use Exception;
use Monolog\Logger;
use AlliancePay\Logger\AllianceLoggerHandler;

/**
 * Class AllianceLogger.
 */
class AllianceLogger extends Logger
{
    public const CHANNEL = 'alliance';

    /**
     * @throws Exception
     */
    public function __construct(
        AllianceLoggerHandler $handler
    ) {
        parent::__construct(self::CHANNEL);
        $this->pushHandler($handler);
    }

}
