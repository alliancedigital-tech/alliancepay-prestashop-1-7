<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription;

use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Service\Gateway\HttpClient;
use Throwable;

/**
 * Data for the support diagnostics page. Operations are ALWAYS requested from the provider:
 * the locally stored callback can be incomplete or stale (partial deliveries).
 */
class SubscriptionDiagnostics
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

    /**
     * @param AllianceSubscription $subscription
     * @return array{operations: array, warning: ?string} a provider failure never breaks the page
     */
    public function fetchOperations(AllianceSubscription $subscription): array
    {
        try {
            $response = $this->httpClient->getOrderOperations($subscription->getProviderOrderId());
        } catch (Throwable $e) {
            $this->logger->error('Subscription diagnostics failed: ' . $e->getMessage());

            return ['operations' => [], 'warning' => $e->getMessage()];
        }

        if (isset($response['success']) && $response['success'] === false) {
            return ['operations' => [], 'warning' => (string) ($response['message'] ?? 'Provider request failed')];
        }

        return ['operations' => $this->normalizeOperations($response), 'warning' => null];
    }

    /**
     * The provider returns a single "operation" object or an "operations" array depending on the count.
     *
     * @param array $response
     * @return array
     */
    public function normalizeOperations(array $response): array
    {
        if (!empty($response['operations']) && is_array($response['operations'])) {
            return array_values($response['operations']);
        }

        if (!empty($response['operation']) && is_array($response['operation'])) {
            return [$response['operation']];
        }

        return [];
    }
}
