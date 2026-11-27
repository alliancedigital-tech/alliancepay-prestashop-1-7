<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Jws;

use Exception;
use RuntimeException;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeyException;
use SimpleJWT\Keys\ECKey;
use SimpleJWT\Keys\KeyFactory;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;
use AlliancePay\Service\RequestIdentification\GenerateRequestIdentification;

/**
 * Class JwsSigner.
 */
class JwsSigner
{
    private const ALGORITHM_RSA = 'RS256';
    private const ALGORITHM_EC = 'ES256';
    private const EC_CURVE = 'P-256';

    /**
     * @param string $privateKeyPem
     * @param string $kid
     * @param string $targetUrl
     * @param string $payloadJson
     * @return string
     * @throws Exception
     */
    public function sign(string $privateKeyPem, string $kid, string $targetUrl, string $payloadJson): string
    {
        if (trim($privateKeyPem) === '') {
            throw new Exception('JWS Private Key (PEM) is not configured.');
        }

        if (trim($kid) === '') {
            throw new Exception('JWS Key ID (kid) is not configured.');
        }

        $claims = json_decode($payloadJson, true);

        if (!is_array($claims) && $payloadJson !== '' && $payloadJson !== '[]') {
            throw new Exception('JWS: payload is not valid JSON.');
        }

        try {
            $key = KeyFactory::create($privateKeyPem, 'pem');
        } catch (KeyException $e) {
            throw new Exception('JWS Private Key is invalid: ' . $e->getMessage());
        }

        if ($key instanceof ECKey) {
            if ($key->getCurve() !== self::EC_CURVE) {
                throw new Exception(
                    'JWS Private Key is invalid: expected an EC key on curve ' . self::EC_CURVE
                    . ', got ' . $key->getCurve() . '.'
                );
            }

            $algorithm = self::ALGORITHM_EC;
        } elseif ($key instanceof RSAKey) {
            $algorithm = self::ALGORITHM_RSA;
        } else {
            throw new Exception(
                'JWS Private Key is invalid: expected an EC P-256 key (PKCS#8), got ' . get_class($key) . '.'
            );
        }

        $key->setKeyId($kid);

        $keySet = new KeySet();
        $keySet->add($key);

        $jwt = new JWT(
            [
                'alg' => $algorithm,
                'ts' => (string) (int) (microtime(true) * 1000),
                'targetUrl' => $targetUrl,
                'kid' => $kid,
                'jti' => GenerateRequestIdentification::generateRequestId(),
            ],
            (array) $claims
        );

        try {
            return $jwt->encode($keySet, null, false);
        } catch (RuntimeException $e) {
            throw new Exception('JWS signing failed: ' . $e->getMessage());
        }
    }
}
