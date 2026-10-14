<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Api;

/**
 * Class ApiResult.
 */
class ApiResult
{
    private const ERROR_MSG_TYPES = ['ERROR', 'VALIDATION_ERROR'];

    /**
     * @var bool
     */
    private $success;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $httpCode;

    /**
     * @var string
     */
    private $raw;

    /**
     * @var string|null
     */
    private $error;

    public function __construct(bool $success, array $data, int $httpCode, string $raw, ?string $error)
    {
        $this->success = $success;
        $this->data = $data;
        $this->httpCode = $httpCode;
        $this->raw = $raw;
        $this->error = $error;
    }

    /**
     * @param array $response result of HttpClient::post()
     * @return self
     */
    public static function fromResponse(array $response): self
    {
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $httpCode = (int) ($response['status_code'] ?? 0);
        $raw = (string) ($response['body'] ?? '');
        $error = $response['error'] ?? null;

        if ($error === null && ($httpCode < 200 || $httpCode >= 300)) {
            $error = 'HTTP ' . $httpCode . self::describe($data, $raw);
        }

        if ($error === null && isset($data['msgType']) && in_array($data['msgType'], self::ERROR_MSG_TYPES, true)) {
            $error = 'Provider error' . self::describe($data, $raw);
        }

        return new self($error === null, $data, $httpCode, $raw, $error);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    private static function describe(array $data, string $raw): string
    {
        $parts = [];

        foreach (['msgCode', 'msgText', 'message', 'error', 'description'] as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                $parts[] = $key . '=' . $data[$key];
            }
        }

        if (empty($parts) && $raw !== '') {
            $parts[] = mb_substr($raw, 0, 500);
        }

        return empty($parts) ? '' : ': ' . implode('; ', $parts);
    }
}
