<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Encryption;

use Exception;
use Symfony\Component\Serializer\Serializer;
use AlliancePay\Service\Encryption\Factory\JWEFactory;
use SimpleJWT\JWE;
use SimpleJWT\Keys\KeyFactory;
use AlliancePay\Service\Encryption\Factory\KeySetFactory;
use AlliancePay\Logger\AllianceLogger as Logger;

/**
 * Class JweEncryptionService.
 */
class JweEncryptionService
{
    private const ALGORITHM = 'ECDH-ES+A256KW';

    private const ENCRYPTION = 'A256GCM';

    private $headers = [
        'alg' => self::ALGORITHM,
        'enc' => self::ENCRYPTION
    ];

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var KeyFactory
     */
    private $keyFactory;

    /**
     * @var KeySetFactory
     */
    private $keySetFactory;

    /**
     * @var JWEFactory
     */
    private $jweFactory;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        KeyFactory $keyFactory,
        KeySetFactory $keySetFactory,
        JWEFactory $jweFactory,
        Logger $logger
    ) {
        $this->keyFactory = $keyFactory;
        $this->keySetFactory = $keySetFactory;
        $this->jweFactory = $jweFactory;
        $this->logger = $logger;
    }

    /**
     * @param array $data
     * @param array $publicServerKey
     * @return string
     */
    public function encrypt(array $data, array $publicServerKey): string
    {
        $dataJson = json_encode($data);
        $key = $this->keyFactory::create(
            $publicServerKey,
            null,
            null,
            self::ALGORITHM
        );
        $keySet = $this->keySetFactory->create();
        $keySet->add($key);
        $jwe = $this->jweFactory->create(
            $this->headers,
            $dataJson
        );

        try {
            return $jwe->encrypt($keySet);
        } catch (Exception $e) {
            $this->logger->error('Encryption failed: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * @param string $authentificationKey
     * @param string $jweToken
     * @return array
     */
    public function decrypt(string $authentificationKey, string $jweToken): array
    {
        $decryptData = [];
        $key = $this->keyFactory::create(
            $authentificationKey, null, null, self::ALGORITHM
        );
        $keySet = $this->keySetFactory->create();
        $keySet->add($key);

        try {
            $jweObj = JWE::decrypt(
                $jweToken,
                $keySet,
                self::ALGORITHM
            );
        } catch (Exception $e) {
            $this->logger->error('Decryption failed: ' . $e->getMessage());
        }

        $decryptPlainText = $jweObj->getPlaintext();

        if ($decryptPlainText) {
            $decryptData = json_decode($decryptPlainText, true);
        }

        return $decryptData;
    }
}
