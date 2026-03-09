<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Gateway;

use AlliancePay\Service\Gateway\Factory\HttpClientFactory;
use AlliancePay\Config\Config;
use AlliancePay\Service\Encryption\JweEncryptionService;
use Cassandra\Exception\AlreadyExistsException;
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\FutureResponse;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Ring\Future\FutureInterface;
use AlliancePay\Logger\AllianceLogger;
use Symfony\Component\Serializer\Serializer;

/**
 * Class HttpClient.
 */
class HttpClient
{
    private const METHOD_POST = 'POST';

    private const REQUEST_CONTENT_TYPE_TEXT = 'text/plain';

    private const REQUEST_CONTENT_TYPE_JSON = 'application/json';

    private const X_API_VERSION = 'V1';
    private const ENDPOINT_CREATE_ORDER = '/ecom/execute_request/hpp/v1/create-order';
    private const ENDPOINT_OPERATIONS = '/ecom/execute_request/hpp/v1/operations';
    private const ENDPOINT_REFUND = '/ecom/execute_request/payments/v3/refund';
    private const ENDPOINT_AUTHORIZE = '/api-gateway/authorize_virtual_device';
    private const MAX_AUTH_ATTEMPTS = 3;
    private $authCounter;

    /**
     * @var HttpClientFactory
     */
    private $httpFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var JweEncryptionService
     */
    private $jweEncryptionService;

    /**
     * @var AllianceLogger
     */
    private $allianceLogger;


    public function __construct(
        HttpClientFactory $httpFactory,
        Config $config,
        JweEncryptionService $jweEncryptionService,
        AllianceLogger $allianceLogger
    ) {
        $this->httpFactory = $httpFactory;
        $this->config = $config;
        $this->jweEncryptionService = $jweEncryptionService;
        $this->allianceLogger = $allianceLogger;
        $this->authCounter = 0;
    }

    /**
     * @param string $serviceCode
     * @return array
     * @throws Exception
     */
    public function authorize(string $serviceCode): array
    {
        $data = [
            'serviceCode' => $serviceCode,
        ];

        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_AUTHORIZE,
                $data
            );
        } catch (RequestException $e) {
            $this->allianceLogger->error('Authorization failed: ' . $e->getMessage());
            return [];
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createOrder(array $orderData): array
    {
        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_CREATE_ORDER,
                $orderData
            );

            return json_decode($response->getBody()->getContents(), true);
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
        $serverPublicKey = json_decode($this->config->getServerPublicKey(), true);
        $encryptedRefundData = $this->jweEncryptionService->encrypt(
            $refundData,
            $serverPublicKey
        );

        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_REFUND,
                $encryptedRefundData,
                self::REQUEST_CONTENT_TYPE_TEXT
            );
        } catch (RequestException $e) {
            $this->allianceLogger->error('Refund failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ];
        }

        $decodedResponse = json_decode($response->getBody()->getContents(), true);

        if (isset($decodedResponse['jwe'])) {
            $decryptedResponse = $this->jweEncryptionService->decrypt(
                $this->config->getAuthorizationKey(),
                $decodedResponse['jwe']
            );

            if (!empty($decryptedResponse)) {
                return $decryptedResponse;
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to refund order.',
        ];
    }

    /**
     * @param string $hppOrderId
     * @return array
     * @throws Exception
     */
    public function getOrderOperations(string $hppOrderId): array
    {
        try {
            $response = $this->sendRequest(
                self::METHOD_POST,
                self::ENDPOINT_OPERATIONS,
                ['hppOrderId' => $hppOrderId]
            );
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->allianceLogger->error('Get operation status failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Get operation status failed',
            ];
        }
    }

    /**
     * @param $method
     * @param $endpoint
     * @param $data
     * @param $contentType
     * @return FutureResponse|ResponseInterface|FutureInterface|null
     * @throws Exception
     */
    private function sendRequest(
        $method,
        $endpoint,
        $data = null,
        $contentType = self::REQUEST_CONTENT_TYPE_JSON
    ) {
        $baseUrl = $this->config->getApiUrl();

        $options = [
            'headers' => [
                'x-api_version' => self::X_API_VERSION,
                'x-device_id' => $this->config->getDeviceId(),
                'x-refresh_token' => $this->config->getRefreshToken(),
                'x-request_id' => uniqid(),
                'Content-Type' => $contentType
            ]
        ];

        if ($contentType === self::REQUEST_CONTENT_TYPE_TEXT) {
            $options['body'] = $data;
        }

        if ($data && $contentType === self::REQUEST_CONTENT_TYPE_JSON) {
            $options['json'] = $data;
            $options['headers']['Accept'] = $contentType;
        }
        try {
            $client = $this->httpFactory->create($baseUrl);

            return $client->send(
                $client->createRequest(
                    $method,
                    $baseUrl . $endpoint,
                    $options
                )
            );
        } catch (Exception $e) {
            $this->allianceLogger->error('Request failed: ' . $e->getMessage());

            if ($e->getCode() === 401) {
                $reAuthResult = $this->errorAuthorizationHandler($e);
                if ($reAuthResult) {
                    $options['headers']['x-device-id'] = $this->config->getDeviceId();
                    $options['headers']['x-refresh_token'] = $this->config->getRefreshToken();

                    $client = $this->httpFactory->create($baseUrl);

                    return $client->send(
                        $client->createRequest(
                            $method,
                            $baseUrl . $endpoint,
                            $options
                        )
                    );
                }
            } elseif ($e->getCode() === 0) {
                throw $e;
            }

            return $e->getResponse();
        }
    }

    /**
     * @param RequestException $e
     * @return bool
     * @throws Exception
     */
    private function errorAuthorizationHandler(RequestException $e): bool
    {
        if ($e->getCode() === 401 && self::MAX_AUTH_ATTEMPTS >= $this->authCounter) {
            $msgCodes = ['b_expired_token', 'b_used_token', 'b_auth_token_expired'];
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            if (in_array($response['msgCode'], $msgCodes)) {
                $this->authCounter++;
                $result = $this->authorize($this->config->getServiceCode());
                if (!empty($result['jwe'])) {
                    $decryptResult = $this->jweEncryptionService->decrypt(
                        $this->config->getAuthorizationKey(),
                        $result['jwe']
                    );

                    if (!empty($decryptResult['refreshToken'])
                        && !empty($decryptResult['authToken'])
                        && !empty($decryptResult['deviceId'])
                        && !empty($decryptResult['serverPublic'])
                        && !empty($decryptResult['tokenExpirationDateTime'])
                        && !empty($decryptResult['tokenExpiration'])
                        && !empty($decryptResult['sessionExpiration'])
                    ) {
                        try {
                            $this->config->saveAuthentificationData($decryptResult);

                            /*if (!empty($authResult['tokenExpirationDateTime'])) {
                                $this->scheduleReAuthorization->createSchedule(
                                    $authResult['tokenExpirationDateTime']
                                );
                            }*/
                        } catch (AlreadyExistsException $e) {
                            $this->allianceLogger->notice($e->getMessage());
                        }

                        return true;
                    }
                }
            }
        }

        return false;
    }
}
