<?php
declare(strict_types=1);

namespace AlliancePay\Tests\Unit\Jws;

use AlliancePay\Service\Jws\JwsSigner;
use Exception;
use PHPUnit\Framework\TestCase;

class JwsSignerTest extends TestCase
{
    private function pem(array $options): string
    {
        $res = openssl_pkey_new($options);
        openssl_pkey_export($res, $pem);

        return $pem;
    }

    private function header(string $jws): array
    {
        $parts = explode('.', $jws);
        $this->assertCount(3, $parts);

        return json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    }

    public function testSignsWithEs256ForEcP256Key(): void
    {
        $pem = $this->pem(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $jws = (new JwsSigner())->sign($pem, 'kid-1', '/x', '{"a":1}');

        $this->assertSame('ES256', $this->header($jws)['alg']);

        $signature = base64_decode(strtr(explode('.', $jws)[2], '-_', '+/'));
        $this->assertSame(64, strlen($signature));
    }

    public function testSignsWithRs256ForRsaKey(): void
    {
        $pem = $this->pem(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $jws = (new JwsSigner())->sign($pem, 'kid-1', '/x', '{"a":1}');

        $this->assertSame('RS256', $this->header($jws)['alg']);
    }

    public function testRejectsOtherCurve(): void
    {
        $pem = $this->pem(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);

        $this->expectException(Exception::class);
        (new JwsSigner())->sign($pem, 'kid-1', '/x', '{}');
    }

    public function testRejectsEmptyKeyAndKid(): void
    {
        $this->expectException(Exception::class);
        (new JwsSigner())->sign('', 'kid-1', '/x', '{}');
    }
}
