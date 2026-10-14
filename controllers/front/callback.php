<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use AlliancePay\Service\Callback\CallbackRouter;
use AlliancePay\Service\Jws\JwsVerifier;
use PrestaShop\PrestaShop\Adapter\ServiceLocator;

/**
 * Class AlliancePayCallbackModuleFrontController.
 */
class AlliancePayCallbackModuleFrontController extends ModuleFrontController
{
    /**
     * @var mixed|object
     */
    private $callbackRouter;

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

        $raw = file_get_contents('php://input');
        $trimmed = trim((string) $raw);

        // Signature verification is unconditional: anything that is not a compact JWS is rejected,
        // the body is never processed as plain JSON. The body is read raw, so the Content-Type header
        // the provider sends (application/json for a non-JSON token) cannot get in the way.
        if (!$this->isCompactJws($trimmed)) {
            PrestaShopLogger::addLog('AlliancePay: Callback rejected, body is not a signed JWS token', 3);
            header('HTTP/1.1 400 Bad Request');
            die('JWS token expected');
        }

        try {
            $callbackData = $this->jwsVerifier->verify($trimmed);
        } catch (Exception $exception) {
            PrestaShopLogger::addLog(
                'AlliancePay: JWS callback verification failed: ' . $exception->getMessage(),
                3
            );
            header('HTTP/1.1 400 Bad Request');
            die('JWS verification failed');
        }

        if (empty($callbackData) || !is_array($callbackData)) {
            PrestaShopLogger::addLog('AlliancePay: Callback received empty data', 3);
            header('HTTP/1.1 400 Bad Request');
            die('No data received');
        }

        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        try {
            $this->callbackRouter->route($entityManager, $callbackData);
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog('AlliancePay: Callback processing failed: ' . $exception->getMessage(), 3);
            // A non-2xx answer lets the provider redeliver the event.
            header('HTTP/1.1 500 Internal Server Error');
            die('Callback processing failed');
        }

        die('OK');
    }

    /**
     * @param string $body
     * @return bool
     */
    private function isCompactJws(string $body): bool
    {
        return $body !== '' && strpos($body, ' ') === false && substr_count($body, '.') === 2;
    }

    /**
     * @return bool
     */
    private function initServices(): bool
    {
        try {
            $this->callbackRouter = ServiceLocator::get(CallbackRouter::class);
            $this->jwsVerifier = ServiceLocator::get(JwsVerifier::class);
        } catch (Exception $exception) {
            //do nothing.
        }

        return $this->callbackRouter instanceof CallbackRouter
            && $this->jwsVerifier instanceof JwsVerifier;
    }
}
