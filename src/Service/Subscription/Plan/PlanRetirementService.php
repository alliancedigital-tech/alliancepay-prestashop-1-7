<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Plan;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Scenario "new plan only for new customers": the old plan stops being offered LOCALLY only,
 * at the provider it stays active so existing subscribers keep being charged on the old terms.
 * Archiving at the provider is a separate, manual step allowed only when no active subscriptions are left.
 */
class PlanRetirementService
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var SubscriptionApiClient
     */
    private $apiClient;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    public function __construct(Config $config, SubscriptionApiClient $apiClient, AuditLogger $auditLogger)
    {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->auditLogger = $auditLogger;
    }

    public function setOfferedToNewCustomers(
        EntityManagerInterface $em,
        AllianceSubscriptionPlan $plan,
        bool $offered,
        ?int $userId = null
    ): void {
        $plan->setIsActive($offered)->setUpdatedAt(new DateTimeImmutable('now'));
        $em->flush();

        $this->auditLogger->log(
            $em,
            $offered ? SubscriptionConfig::AUDIT_PLAN_ACTIVATED : SubscriptionConfig::AUDIT_PLAN_DEACTIVATED,
            ['active_subscriptions' => $this->countRunning($em, $plan)],
            $plan->getId(),
            $plan->getProductId(),
            null,
            $userId
        );
    }

    /**
     * Plans that are no longer offered and have no active subscriptions: candidates for provider-side archiving.
     * The decision and the confirmation always stay with the administrator.
     *
     * @return AllianceSubscriptionPlan[]
     */
    public function getArchiveCandidates(EntityManagerInterface $em): array
    {
        $plans = $em->getRepository(AllianceSubscriptionPlan::class)->findBy([
            'isActive' => false,
            'status' => SubscriptionConfig::PLAN_STATUS_ACTIVE,
        ]);
        $running = $em->getRepository(AllianceSubscription::class)->countRunningByPlan();

        return array_values(array_filter($plans, function (AllianceSubscriptionPlan $plan) use ($running) {
            return empty($running[$plan->getId()]);
        }));
    }

    private function countRunning(EntityManagerInterface $em, AllianceSubscriptionPlan $plan): int
    {
        return $em->getRepository(AllianceSubscription::class)->countRunningForPlan($plan->getId());
    }

    /**
     * @return array{success: bool, errors: array}
     */
    public function archiveAtProvider(
        EntityManagerInterface $em,
        AllianceSubscriptionPlan $plan,
        ?int $userId = null
    ): array {
        if ($plan->isActive() || $this->countRunning($em, $plan) > 0) {
            return ['success' => false, 'errors' => [[
                'message' => 'Only a plan that is no longer offered and has no active subscriptions can be archived.',
                'params' => [],
            ]]];
        }

        $result = $this->apiClient->updatePlanStatus([
            'planId' => $plan->getProviderPlanId(),
            'status' => SubscriptionConfig::PLAN_STATUS_CANCELED,
            'merchantId' => $this->config->getMerchantId(),
        ]);

        if (!$result->isSuccess()) {
            return ['success' => false, 'errors' => [[
                'message' => 'The provider rejected the status change: %error%',
                'params' => ['%error%' => (string) $result->getError()],
            ]]];
        }

        $plan->setStatus(SubscriptionConfig::PLAN_STATUS_CANCELED)->setUpdatedAt(new DateTimeImmutable('now'));
        $em->flush();

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_PLAN_ARCHIVED,
            ['provider_plan_id' => $plan->getProviderPlanId()],
            $plan->getId(),
            $plan->getProductId(),
            null,
            $userId
        );

        return ['success' => true, 'errors' => []];
    }
}
