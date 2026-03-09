<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Logger;

use Monolog\Handler\StreamHandler;

/**
 * Class AllianceLoggerHandler.
 */
class AllianceLoggerHandler extends StreamHandler
{
    public function __construct()
    {
        $logFilePath = _PS_ROOT_DIR_ . '/var/logs/alliance.log';
        parent::__construct($logFilePath, \Monolog\Logger::DEBUG, true);
    }
}
