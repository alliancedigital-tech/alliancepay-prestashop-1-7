<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Fiscalization: tables, default measurement code and product form hooks.
 *
 * @param Alliancepay $module
 * @return bool
 */
function upgrade_module_1_5_0(Alliancepay $module): bool
{
    return $module->createFiscalizationTables()
        && $module->installFiscalizationDefaults()
        && $module->registerFiscalizationHooks();
}
