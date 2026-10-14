<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Api;

use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Service\Gateway\HttpClient;

/**
 * Thin wrapper over the signed HttpClient for the scheduled_payments endpoints.
 */
class SubscriptionApiClient
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(HttpClient $httpClient, AllianceLogger $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    public function createPlan(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_PLAN_CREATE, $payload);
    }

    public function updatePlan(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_PLAN_UPDATE, $payload);
    }

    public function updatePlanStatus(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_PLAN_STATUS_UPDATE, $payload);
    }

    /**
     * Mandatory second step after plan creation / update: without it the provider applies a hidden
     * validity limit to the plan version and subscriptions silently stop renewing.
     */
    public function setInfiniteSchedule(string $planId, string $versionId): ApiResult
    {
        return $this->call(
            SubscriptionConfig::ENDPOINT_PLAN_VERSION_UPDATE,
            ['planId' => $planId, 'versionId' => $versionId, 'infiniteVersion' => true]
        );
    }

    public function deleteVersion(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_VERSION_DELETE, $payload);
    }

    public function createSubscriptionOrder(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_SUBSCRIPTION_ORDER_CREATE, $payload);
    }

    public function cancelSubscriptionOrder(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_SUBSCRIPTION_ORDER_CANCEL, $payload);
    }

    public function triggerRecurringPayment(array $payload): ApiResult
    {
        return $this->call(SubscriptionConfig::ENDPOINT_RECURRING_PAYMENT, $payload);
    }

    private function call(string $endpoint, array $payload): ApiResult
    {
        $result = ApiResult::fromResponse(
            $this->httpClient->post($endpoint, $payload, SubscriptionConfig::CONTENT_TYPE)
        );

        if (!$result->isSuccess()) {
            $this->logger->error(
                'Subscription API call failed: ' . $endpoint . ' -> ' . $result->getError(),
                ['http_code' => $result->getHttpCode(), 'raw' => mb_substr($result->getRaw(), 0, 2000)]
            );
        }

        return $result;
    }
}
