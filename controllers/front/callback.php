<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Service\Callback\CallbackProcessor;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

/**
 * Class AlliancePayCallbackModuleFrontController.
 */
class AlliancePayCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * @var mixed|object
     */
    private $callbackProcessor;

    public function postProcess() {

        if ($this->initCallbackProcessor()) {
            $jsonRaw = file_get_contents('php://input');
            $callbackData = json_decode($jsonRaw, true);

            if (empty($callbackData)) {
                PrestaShopLogger::addLog('AlliancePay: Callback received empty data', 3);
                header('HTTP/1.1 400 Bad Request');
                die('No data received');
            }

            $entityManager = $this->container->get('doctrine.orm.entity_manager');
            $this->callbackProcessor->processCallback($entityManager, $callbackData);
        }
        die('OK');
    }

    /**
     * @return bool
     */
    private function initCallbackProcessor(): bool
    {
        try {
            $this->callbackProcessor = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get(
                \AlliancePay\Service\Callback\CallbackProcessor::class
            );
        } catch (Exception $exception) {
            //do nothing.
        }

        return $this->callbackProcessor instanceof CallBackProcessor;
    }
}
