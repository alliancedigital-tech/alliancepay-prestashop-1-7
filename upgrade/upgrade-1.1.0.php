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
function upgrade_module_1_1_0(Alliancepay $module): bool
{
    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'alliance_order`'
        . ' ADD COLUMN IF NOT EXISTS `transaction_type` SMALLINT DEFAULT NULL;';

    return (bool) Db::getInstance()->execute($sql);
}
