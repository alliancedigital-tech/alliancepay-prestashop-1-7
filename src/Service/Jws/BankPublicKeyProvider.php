<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Jws;

use AlliancePay\Config\Config;
use AlliancePay\Logger\AllianceLogger as Logger;
use AlliancePay\Service\Gateway\Factory\HttpClientFactory;
use Exception;
use GuzzleHttp\Exception\RequestException;

/**
 * Class BankPublicKeyProvider.
 */
class BankPublicKeyProvider
{
    private const METHOD_POST = 'POST';

    private const ENDPOINT_PUBLIC_KEY = '/ecom/keys/public_key/get_v1';

    private const X_API_VERSION = 'V1';

    /**
     * @var HttpClientFactory
     */
    private $httpFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var JwsSigner
     */
    private $jwsSigner;

    /**
     * @var Logger
     */
    private $allianceLogger;

    public function __construct(
        HttpClientFactory $httpFactory,
        Config $config,
        JwsSigner $jwsSigner,
        Logger $allianceLogger
    ) {
        $this->httpFactory = $httpFactory;
        $this->config = $config;
        $this->jwsSigner = $jwsSigner;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param string $kid
     * @return array
     * @throws Exception
     */
    public function getPublicKey(string $kid): array
    {
        $cached = $this->config->getCachedBankPublicKey($kid);

        if ($cached !== null) {
            return $cached;
        }

        $jwk = $this->fetchPublicKey($kid);
        $this->config->setCachedBankPublicKey($kid, $jwk);

        return $jwk;
    }

    /**
     * @param string $kid
     * @return array
     * @throws Exception
     */
    private function fetchPublicKey(string $kid): array
    {
        $baseUrl = $this->config->getApiUrl();

        $options = [
            'json' => ['kid' => $kid]
        ];

        try {
            $client = $this->httpFactory->create($baseUrl);
            $response = $client->send(
                $client->createRequest(
                    self::METHOD_POST,
                    $baseUrl . self::ENDPOINT_PUBLIC_KEY,
                    $options
                )
            );
        } catch (RequestException $e) {
            $this->allianceLogger->error('Cannot fetch bank public key (kid=' . $kid . '): ' . $e->getMessage());

            throw new Exception('Cannot fetch bank public key (kid=' . $kid . '): ' . $e->getMessage());
        }

        $jwk = json_decode($response->getBody()->getContents(), true);

        if (!is_array($jwk) || empty($jwk['kty']) || empty($jwk['crv']) || empty($jwk['x']) || empty($jwk['y'])) {
            throw new Exception('Invalid public key response from bank for kid=' . $kid);
        }

        return $jwk;
    }
}
