<?php
/**
 * Test bootstrap: module autoloader plus the PrestaShop core vendor (Doctrine, Monolog), when the module
 * sits inside a PrestaShop tree, and minimal stand-ins for the legacy PrestaShop classes the services touch.
 */

require __DIR__ . '/../vendor/autoload.php';

$coreAutoload = __DIR__ . '/../../../vendor/autoload.php';

if (file_exists($coreAutoload)) {
    require_once $coreAutoload;
}

require_once __DIR__ . '/stubs/PrestaShopStubs.php';
