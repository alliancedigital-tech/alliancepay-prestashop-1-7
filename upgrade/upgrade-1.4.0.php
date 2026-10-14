<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Subscriptions: tables, hooks and admin tabs.
 *
 * @param Alliancepay $module
 * @return bool
 */
function upgrade_module_1_4_0(Alliancepay $module): bool
{
    return $module->createSubscriptionTables()
        && $module->registerSubscriptionHooks()
        && $module->installSubscriptionTabs();
}
