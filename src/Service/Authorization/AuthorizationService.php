<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Authorization;

use AlliancePay\Config\Config;
use AlliancePay\Service\Encryption\JweEncryptionService;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Logger\AllianceLogger as Logger;
use PrestaShop\PrestaShop\Core\Foundation\IoC\Exception;

/**
 * Class AuthorizationService.
 */
class AuthorizationService
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var JweEncryptionService
     */
    private $jweEncryptionService;

    public function __construct(
        HttpClient $client,
        Config $config,
        JweEncryptionService  $jweEncryptionService,
        Logger $logger
    ) {
        $this->httpClient = $client;
        $this->config = $config;
        $this->jweEncryptionService = $jweEncryptionService;
        $this->logger = $logger;
    }

    /**
     * @throws \Exception
     */
    public function authorize(): bool
    {
        $serviceCode = $this->config->getServiceCode();

        if (empty($serviceCode)) {
            return false;
        }

        try {
            $authorizationResult = $this->httpClient->authorize($serviceCode);
        } catch (Exception $exception) {

        }

        if (!empty($authorizationResult['jwe'])) {
            $authorizationKey = $this->config->getAuthorizationKey();
            $authData = $this->jweEncryptionService->decrypt(
                $authorizationKey,
                $authorizationResult['jwe']
            );
            $this->config->saveAuthentificationData($authData);

            return true;
        } elseif (isset($authResult['msgType']) && $authResult['msgType'] === 'ERROR') {
            return false;
        }

        return false;
    }

}
