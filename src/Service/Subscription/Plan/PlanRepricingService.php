<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Plan;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AlliancePlanVersionHistory;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use AlliancePay\Service\Subscription\Rules\PlanKeyGenerator;
use AlliancePay\Service\Subscription\Rules\RuleNormalizer;
use AlliancePay\Service\Subscription\Rules\RuleValidator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Scenario "mass repricing": all current subscribers of the SAME plan move to the new rules
 * from a future date. Guarded by lead time, explicit notification confirmation and full version history.
 */
class PlanRepricingService
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
     * @var RuleNormalizer
     */
    private $ruleNormalizer;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    /**
     * @var PlanKeyGenerator
     */
    private $planKeyGenerator;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    public function __construct(
        Config $config,
        SubscriptionApiClient $apiClient,
        RuleNormalizer $ruleNormalizer,
        RuleValidator $ruleValidator,
        PlanKeyGenerator $planKeyGenerator,
        AuditLogger $auditLogger
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->ruleNormalizer = $ruleNormalizer;
        $this->ruleValidator = $ruleValidator;
        $this->planKeyGenerator = $planKeyGenerator;
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param AllianceSubscriptionPlan $plan
     * @param array $rawRules new rules
     * @param DateTimeImmutable $effectiveFrom
     * @param bool $customersNotified explicit confirmation from the administrator
     * @param string $reason
     * @param int|null $userId
     * @return array{success: bool, errors: array}
     */
    public function reprice(
        EntityManagerInterface $em,
        AllianceSubscriptionPlan $plan,
        array $rawRules,
        DateTimeImmutable $effectiveFrom,
        bool $customersNotified,
        string $reason,
        ?int $userId = null
    ): array {
        $rules = $this->ruleNormalizer->normalize($rawRules);
        $errors = $this->ruleValidator->validate($rules);

        if (!$customersNotified) {
            $errors[] = [
                'message' => 'Confirm that customers have been (or will be) notified about the price change.',
                'params' => [],
            ];
        }

        $minDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . SubscriptionConfig::REPRICING_MIN_LEAD_DAYS . ' days');

        if ($effectiveFrom < $minDate) {
            $errors[] = [
                'message' => 'The new rules cannot take effect earlier than %days% days from now.',
                'params' => ['%days%' => SubscriptionConfig::REPRICING_MIN_LEAD_DAYS],
            ];
        }

        if (!empty($rules) && count(array_unique(array_column($rules, 'currency'))) > 1) {
            $errors[] = ['message' => 'All rules of a plan must use the same currency.', 'params' => []];
        }

        $newKey = $this->planKeyGenerator->generate(
            $this->config->getMerchantId(),
            $plan->getProductId(),
            $plan->getTemplateId(),
            $plan->getCategoryIndicator(),
            $rules
        );
        $clash = $em->getRepository(AllianceSubscriptionPlan::class)->findOneBy(['planKey' => $newKey]);

        if ($clash !== null && $clash->getId() !== $plan->getId()) {
            $errors[] = ['message' => 'Another plan with identical rules already exists.', 'params' => []];
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $updated = $this->apiClient->updatePlan([
            'planId' => $plan->getProviderPlanId(),
            'name' => $plan->getName(),
            'categorySubcategoryIndicator' => $plan->getCategoryIndicator(),
            'merchantId' => $this->config->getMerchantId(),
            'appliesDateTimeFrom' => $effectiveFrom->format('Y-m-d\TH:i:sP'),
            'rules' => $rules,
        ]);
        $newVersionId = (string) $updated->get('versionId');

        if (!$updated->isSuccess() || $newVersionId === '') {
            return ['success' => false, 'errors' => [[
                'message' => 'The provider rejected the new rules: %error%',
                'params' => ['%error%' => (string) ($updated->getError() ?? 'versionId is missing in the response')],
            ]]];
        }

        $infinite = $this->apiClient->setInfiniteSchedule($plan->getProviderPlanId(), $newVersionId);

        $history = new AlliancePlanVersionHistory();
        $history->setPlanId((int) $plan->getId())
            ->setOldVersionId($plan->getVersionId())
            ->setNewVersionId($newVersionId)
            ->setOldRules($plan->getRules())
            ->setNewRules($rules)
            ->setAppliesFrom($effectiveFrom)
            ->setReason($reason !== '' ? $reason : null)
            ->setCreatedBy($userId);
        $em->persist($history);

        $plan->setVersionId($newVersionId)
            ->setRules($rules)
            ->setPlanKey($newKey)
            ->setCurrencyCode((string) $rules[0]['currency'])
            ->setUpdatedAt(new DateTimeImmutable('now'));

        if (!$infinite->isSuccess()) {
            $plan->setStatus(SubscriptionConfig::PLAN_STATUS_ERROR)
                ->setSyncStatus(SubscriptionConfig::SYNC_STATUS_ERROR)
                ->setSyncError((string) $infinite->getError());
        }

        $em->flush();

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_PLAN_REPRICED,
            [
                'old_version_id' => $history->getOldVersionId(),
                'new_version_id' => $newVersionId,
                'applies_from' => $effectiveFrom->format('c'),
                'active_subscriptions' => $em->getRepository(AllianceSubscription::class)->countRunningForPlan($plan->getId()),
                'customers_notified' => true,
                'infinite_schedule_error' => $infinite->getError(),
            ],
            $plan->getId(),
            $plan->getProductId(),
            null,
            $userId
        );

        if (!$infinite->isSuccess()) {
            return ['success' => false, 'errors' => [[
                'message' => 'The new version was created, but setting the infinite schedule failed: %error%. Use "Retry infinite schedule" on the plan page.',
                'params' => ['%error%' => (string) $infinite->getError()],
            ]]];
        }

        return ['success' => true, 'errors' => []];
    }
}
