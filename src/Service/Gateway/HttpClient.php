<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Gateway;

use AlliancePay\Service\Gateway\Factory\HttpClientFactory;
use AlliancePay\Config\Config;
use AlliancePay\Service\Jws\JwsSigner;
use AlliancePay\Service\Jws\JwsVerifier;
use AlliancePay\Service\RequestIdentification\GenerateRequestIdentification;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Ring\Future\FutureInterface;
use AlliancePay\Logger\AllianceLogger;

/**
 * Class HttpClient.
 */
class HttpClient
{
    private const METHOD_POST = 'POST';

    public const CONTENT_TYPE_JWS = 'application/jws';

    private const X_API_VERSION = 'V1';

    private const ENDPOINT_CREATE_ORDER = '/ecom/jws/payments/hpp/create_order_v1';

    private const ENDPOINT_OPERATIONS = '/ecom/jws/payments/hpp/operations_v1';

    private const ENDPOINT_REFUND = '/ecom/jws/payments/refund_v1';

    private const ENDPOINT_COMPLETION = '/ecom/jws/payments/preauth/completion_v1';

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
     * @var JwsVerifier
     */
    private $jwsVerifier;

    /**
     * @var AllianceLogger
     */
    private $allianceLogger;

    public function __construct(
        HttpClientFactory $httpFactory,
        Config $config,
        JwsSigner $jwsSigner,
        JwsVerifier $jwsVerifier,
        AllianceLogger $allianceLogger
    ) {
        $this->httpFactory = $httpFactory;
        $this->config = $config;
        $this->jwsSigner = $jwsSigner;
        $this->jwsVerifier = $jwsVerifier;
        $this->allianceLogger = $allianceLogger;
    }

    public function createOrder(array $orderData): array
    {
        try {
            return $this->sendRequest(self::METHOD_POST, self::ENDPOINT_CREATE_ORDER, $orderData);
        } catch (RequestException $e) {
            $this->allianceLogger->error('Create order failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment failed. Please try again later.',
            ];
        }
    }

    /**
     * @param array $refundData
     * @return array
     * @throws Exception
     */
    public function refund(array $refundData): array
    {
        try {
            return $this->sendRequest(self::METHOD_POST, self::ENDPOINT_REFUND, $refundData);
        } catch (RequestException $e) {
            $this->allianceLogger->error('Refund failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @param array $completionData
     * @return array
     * @throws Exception
     */
    public function executeCompletion(array $completionData): array
    {
        try {
            return $this->sendRequest(self::METHOD_POST, self::ENDPOINT_COMPLETION, $completionData);
        } catch (RequestException $e) {
            $this->allianceLogger->error('Completion failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Completion failed: ' . $e->getMessage()];
        }
    }

    public function getOrderOperations(string $hppOrderId): array
    {
        try {
            return $this->sendRequest(self::METHOD_POST, self::ENDPOINT_OPERATIONS, ['hppOrderId' => $hppOrderId]);
        } catch (RequestException $e) {
            $this->allianceLogger->error('Get operation status failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Get operation status failed',
            ];
        }
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        $baseUrl = $this->config->getApiUrl();
        $payloadJson = json_encode($data);

        $jwsToken = $this->jwsSigner->sign(
            $this->config->getJwsPrivateKey(),
            $this->config->getJwsKeyId(),
            $endpoint,
            $payloadJson
        );

        $options = [
            'headers' => [
                'Content-Type' => 'application/jws',
                'Accept' => 'application/json',
                'x-api_version' => self::X_API_VERSION,
                'x-request_id' => GenerateRequestIdentification::generateRequestId(),
            ],
            'body' => $jwsToken,
        ];

        try {
            $client = $this->httpFactory->create($baseUrl);

            $response = $client->send(
                $client->createRequest(
                    $method,
                    $baseUrl . $endpoint,
                    $options
                )
            );
        } catch (Exception $e) {
            $this->allianceLogger->error('Request failed: ' . $e->getMessage());

            if ($e->getCode() === 401) {
                throw new Exception(
                    'Request failed (401). Check JWS Private Key and Key ID settings.'
                );
            }

            if ($e->getCode() === 0) {
                throw $e;
            }

            $response = $e->getResponse();

            if ($response === null) {
                throw $e;
            }
        }

        return $this->parseResponse($response);
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @param string $contentType
     * @return array
     */
    public function post(string $endpoint, array $data = [], string $contentType = self::CONTENT_TYPE_JWS): array
    {
        try {
            $raw = $this->sendRawRequest($endpoint, $data, $contentType);
        } catch (Exception $e) {
            return ['status_code' => 0, 'body' => '', 'data' => [], 'error' => $e->getMessage()];
        }

        try {
            $parsed = $this->parseBody($raw['body']);
        } catch (Exception $e) {
            $this->allianceLogger->error('Response verification failed (' . $endpoint . '): ' . $e->getMessage());

            return [
                'status_code' => $raw['status_code'],
                'body' => $raw['body'],
                'data' => [],
                'error' => $e->getMessage(),
            ];
        }

        return [
            'status_code' => $raw['status_code'],
            'body' => $raw['body'],
            'data' => $parsed,
            'error' => null,
        ];
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @param string $contentType
     * @return array{status_code: int, body: string}
     * @throws Exception on transport errors and 401
     */
    private function sendRawRequest(string $endpoint, array $data, string $contentType): array
    {
        $baseUrl = $this->config->getApiUrl();

        $jwsToken = $this->jwsSigner->sign(
            $this->config->getJwsPrivateKey(),
            $this->config->getJwsKeyId(),
            $endpoint,
            json_encode($data)
        );

        $options = [
            'headers' => [
                'Content-Type' => $contentType,
                'Accept' => 'application/json',
                'x-api_version' => self::X_API_VERSION,
                'x-request_id' => GenerateRequestIdentification::generateRequestId(),
            ],
            'body' => $jwsToken,
        ];

        try {
            $client = $this->httpFactory->create($baseUrl);
            $response = $client->send($client->createRequest(self::METHOD_POST, $baseUrl . $endpoint, $options));
        } catch (Exception $e) {
            $this->allianceLogger->error('Request failed (' . $endpoint . '): ' . $e->getMessage());

            if ($e->getCode() === 401) {
                throw new Exception('Request failed (401). Check JWS Private Key and Key ID settings.');
            }

            $response = $e instanceof RequestException ? $e->getResponse() : null;

            if ($response === null) {
                throw $e;
            }
        }

        return [
            'status_code' => (int) $response->getStatusCode(),
            'body' => trim((string) $response->getBody()->getContents()),
        ];
    }

    /**
     * @param FutureResponse|ResponseInterface|FutureInterface $response
     * @return array
     * @throws Exception
     */
    private function parseResponse($response): array
    {
        return $this->parseBody(trim((string)$response->getBody()->getContents()));
    }

    /**
     * @param string $body
     * @return array
     * @throws Exception
     */
    private function parseBody(string $body): array
    {
        $dotCount = substr_count($body, '.');
        $isToken = $body !== '' && strpos($body, ' ') === false;

        if ($isToken && $dotCount === 2) {
            return $this->jwsVerifier->verify($body);
        }

        return (array)json_decode($body, true);
    }
}
