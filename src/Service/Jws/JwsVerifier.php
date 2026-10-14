<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Jws;

use AlliancePay\Logger\AllianceLogger as Logger;
use Exception;
use RuntimeException;
use SimpleJWT\InvalidTokenException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\ECKey;
use SimpleJWT\Keys\KeySet;

/**
 * Class JwsVerifier.
 */
class JwsVerifier
{
    /**
     * @var BankPublicKeyProvider
     */
    private $bankPublicKeyProvider;

    /**
     * @var Logger
     */
    private $allianceLogger;

    public function __construct(BankPublicKeyProvider $bankPublicKeyProvider, Logger $allianceLogger)
    {
        $this->bankPublicKeyProvider = $bankPublicKeyProvider;
        $this->allianceLogger = $allianceLogger;
    }

    /**
     * @param string $jws
     * @return array
     * @throws Exception
     */
    public function verify(string $jws): array
    {
        try {
            $deserialised = JWT::deserialise($jws);
        } catch (InvalidTokenException $e) {
            throw new Exception('JWS callback parse error: ' . $e->getMessage());
        }

        $headers = $deserialised['signatures'][0]['headers'] ?? [];
        $kid = $headers['kid'] ?? null;
        $alg = $headers['alg'] ?? null;

        if (empty($kid) || empty($alg)) {
            throw new Exception('JWS callback is missing kid or alg in header.');
        }

        $jwk = $this->bankPublicKeyProvider->getPublicKey($kid);

        $key = new ECKey($jwk, 'php');
        $key->setKeyId($kid);

        $keySet = new KeySet();
        $keySet->add($key);

        try {
            $jwt = JWT::decode($jws, $keySet, $alg);
        } catch (InvalidTokenException $e) {
            $this->allianceLogger->error('JWS callback verification failed: ' . $e->getMessage());

            throw new Exception('JWS callback verification failed: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            $this->allianceLogger->error('JWS callback verification failed: ' . $e->getMessage());

            throw new Exception('JWS callback verification failed: ' . $e->getMessage());
        }

        return $jwt->getClaims();
    }
}
