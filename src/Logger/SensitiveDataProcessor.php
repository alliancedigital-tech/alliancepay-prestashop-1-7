<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Logger;

/**
 * Monolog processor that masks keys, signatures and tokens in the log message and context.
 */
class SensitiveDataProcessor
{
    private const MASK = '***';

    private const SENSITIVE_KEY_PATTERN = '/key|secret|token|signature|authorization|password|pem/i';

    public function __invoke(array $record): array
    {
        $record['message'] = $this->maskString((string) $record['message']);

        foreach (['context', 'extra'] as $part) {
            if (!empty($record[$part]) && is_array($record[$part])) {
                $record[$part] = $this->maskArray($record[$part]);
            }
        }

        return $record;
    }

    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->maskString($value);
            }
        }

        return $data;
    }

    private function maskString(string $value): string
    {
        // PEM blocks and compact JWS tokens (three base64url parts).
        $value = preg_replace('/-----BEGIN [A-Z ]+-----.*?-----END [A-Z ]+-----/s', self::MASK, $value);

        return preg_replace('/\beyJ[\w-]+\.[\w-]+\.[\w-]*/', self::MASK, $value);
    }
}
