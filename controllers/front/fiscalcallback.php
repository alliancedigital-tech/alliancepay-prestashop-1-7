<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Service\Callback\FiscalizationCallbackProcessor;
use AlliancePay\Service\Jws\JwsVerifier;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

/**
 * Class AlliancePayFiscalcallbackModuleFrontController.
 *
 * Receives the asynchronous result of a fiscalization (the notificationUrl of the request).
 */
class AlliancePayFiscalcallbackModuleFrontController extends ModuleFrontController
{
    /**
     * @var mixed|object
     */
    private $processor;

    /**
     * @var mixed|object
     */
    private $jwsVerifier;

    public function postProcess()
    {
        if (!$this->initServices()) {
            header('HTTP/1.1 500 Internal Server Error');
            die('Callback services are not available');
        }

        $trimmed = trim((string) file_get_contents('php://input'));

        // Same rule as the payment callback: only a signed JWS is accepted.
        if ($trimmed === '' || strpos($trimmed, ' ') !== false || substr_count($trimmed, '.') !== 2) {
            PrestaShopLogger::addLog('AlliancePay: Fiscalization callback rejected, body is not a signed JWS token', 3);
            header('HTTP/1.1 400 Bad Request');
            die('JWS token expected');
        }

        try {
            $callbackData = $this->jwsVerifier->verify($trimmed);
        } catch (Exception $exception) {
            PrestaShopLogger::addLog(
                'AlliancePay: Fiscalization callback verification failed: ' . $exception->getMessage(),
                3
            );
            header('HTTP/1.1 400 Bad Request');
            die('JWS verification failed');
        }

        if (empty($callbackData) || !is_array($callbackData)) {
            header('HTTP/1.1 400 Bad Request');
            die('No data received');
        }

        try {
            $entityManager = $this->container->get('doctrine.orm.entity_manager');
            $this->processor->process($entityManager, $callbackData);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: Fiscalization callback processing failed: ' . $exception->getMessage(), 3);
            // A non-2xx answer lets the provider redeliver the event.
            header('HTTP/1.1 500 Internal Server Error');
            die('Callback processing failed');
        }

        die('OK');
    }

    /**
     * @return bool
     */
    private function initServices(): bool
    {
        try {
            $this->processor = ServiceLocator::get(FiscalizationCallbackProcessor::class);
            $this->jwsVerifier = ServiceLocator::get(JwsVerifier::class);
        } catch (Exception $exception) {
            //do nothing.
        }

        return $this->processor instanceof FiscalizationCallbackProcessor
            && $this->jwsVerifier instanceof JwsVerifier;
    }
}
