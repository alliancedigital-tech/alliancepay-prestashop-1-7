<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Alliancepay $module
 * @return bool
 */
function upgrade_module_1_3_0(Alliancepay $module): bool
{
    $db = Db::getInstance();
    $table = '`' . _DB_PREFIX_ . 'alliance_order`';

    $sqlCurrencyCode = 'ALTER TABLE ' . $table
        . ' ADD COLUMN IF NOT EXISTS `currency_code` SMALLINT(3) DEFAULT NULL;';

    $sqlConversionRate = 'ALTER TABLE ' . $table
        . ' ADD COLUMN IF NOT EXISTS `conversion_rate` DECIMAL(20,8) DEFAULT NULL;';

    $refundTable = '`' . _DB_PREFIX_ . 'alliance_refund_order`';

    $sqlRrn = 'ALTER TABLE ' . $refundTable
        . ' MODIFY COLUMN `rrn` VARCHAR(255) NULL;';

    $sqlOperationId = 'ALTER TABLE ' . $refundTable
        . ' MODIFY COLUMN `operation_id` VARCHAR(255) NULL;';

    $sqlProcessingMerchantId = 'ALTER TABLE ' . $refundTable
        . ' MODIFY COLUMN `processing_merchant_id` VARCHAR(255) NULL;';

    $sqlProcessingTerminalId = 'ALTER TABLE ' . $refundTable
        . ' MODIFY COLUMN `processing_terminal_id` VARCHAR(255) NULL;';

    return (bool) $db->execute($sqlCurrencyCode)
        && (bool) $db->execute($sqlConversionRate)
        && (bool) $db->execute($sqlRrn)
        && (bool) $db->execute($sqlOperationId)
        && (bool) $db->execute($sqlProcessingMerchantId)
        && (bool) $db->execute($sqlProcessingTerminalId);
}
