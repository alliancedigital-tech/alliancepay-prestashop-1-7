<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Plan;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Service\Subscription\Api\SubscriptionApiClient;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use AlliancePay\Service\Subscription\Rules\PlanKeyGenerator;
use AlliancePay\Service\Subscription\Rules\RuleNormalizer;
use AlliancePay\Service\Subscription\Rules\RuleValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Creation and synchronization of subscription plans.
 *
 * Provider first, database second: the local record is written only after a successful provider
 * answer (a crash between the two leaves a provider plan without a local trace - accepted trade-off).
 */
class PlanService
{
    private const LOCK_TIMEOUT_SECONDS = 15;

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

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(
        Config $config,
        SubscriptionApiClient $apiClient,
        RuleNormalizer $ruleNormalizer,
        RuleValidator $ruleValidator,
        PlanKeyGenerator $planKeyGenerator,
        AuditLogger $auditLogger,
        AllianceLogger $logger
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->ruleNormalizer = $ruleNormalizer;
        $this->ruleValidator = $ruleValidator;
        $this->planKeyGenerator = $planKeyGenerator;
        $this->auditLogger = $auditLogger;
        $this->logger = $logger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param array $input productId, templateId?, name, description?, categoryIndicator, rules, appliesFrom?
     * @param int|null $userId
     * @return array{success: bool, plan: ?AllianceSubscriptionPlan, errors: array}
     */
    public function create(EntityManagerInterface $em, array $input, ?int $userId = null): array
    {
        $productId = (int) ($input['productId'] ?? 0);
        $templateId = !empty($input['templateId']) ? (int) $input['templateId'] : null;
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $category = trim((string) ($input['categoryIndicator'] ?? ''));
        $rules = $this->ruleNormalizer->normalize((array) ($input['rules'] ?? []));
        $appliesFrom = ($input['appliesFrom'] ?? null) instanceof DateTimeImmutable ? $input['appliesFrom'] : null;
        $merchantId = $this->config->getMerchantId();

        $errors = $this->validate($productId, $name, $category, $merchantId, $rules);

        if (!empty($errors)) {
            return $this->fail($errors);
        }

        $planKey = $this->planKeyGenerator->generate($merchantId, $productId, $templateId, $category, $rules);
        $lockName = 'alliance_plan_' . substr($planKey, 0, 40);

        if (!$this->acquireLock($em, $lockName)) {
            return $this->fail([['message' => 'Another request is creating the same plan, try again.', 'params' => []]]);
        }

        try {
            return $this->createLocked(
                $em,
                compact('productId', 'templateId', 'name', 'description', 'category', 'rules', 'appliesFrom', 'merchantId', 'planKey'),
                $userId
            );
        } finally {
            $this->releaseLock($em, $lockName);
        }
    }

    /**
     * Repeats only step 2 (infinite schedule) for a plan stuck in the ERROR state.
     *
     * @return array{success: bool, plan: ?AllianceSubscriptionPlan, errors: array}
     */
    public function retryInfiniteSchedule(
        EntityManagerInterface $em,
        AllianceSubscriptionPlan $plan,
        ?int $userId = null
    ): array {
        $result = $this->apiClient->setInfiniteSchedule($plan->getProviderPlanId(), (string) $plan->getVersionId());

        if (!$result->isSuccess()) {
            $plan->setSyncError((string) $result->getError());
            $em->flush();

            return $this->fail([[
                'message' => 'Setting the infinite schedule failed: %error%',
                'params' => ['%error%' => (string) $result->getError()],
            ]], $plan);
        }

        $plan->setSyncStatus(SubscriptionConfig::SYNC_STATUS_SYNCED)
            ->setSyncError(null)
            ->setStatus(SubscriptionConfig::PLAN_STATUS_ACTIVE)
            ->setUpdatedAt(new DateTimeImmutable('now'));
        $em->flush();

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_PLAN_SYNC_RETRIED,
            ['provider_plan_id' => $plan->getProviderPlanId()],
            $plan->getId(),
            $plan->getProductId(),
            null,
            $userId
        );

        return ['success' => true, 'plan' => $plan, 'errors' => []];
    }

    /**
     * @param array $data
     * @param int|null $userId
     * @return array
     */
    private function createLocked(EntityManagerInterface $em, array $data, ?int $userId): array
    {
        $existing = $em->getRepository(AllianceSubscriptionPlan::class)->findOneBy(['planKey' => $data['planKey']]);

        if ($existing !== null) {
            return $this->fail([[
                'message' => 'An identical plan already exists for this product (provider plan %id%).',
                'params' => ['%id%' => $existing->getProviderPlanId()],
            ]], $existing);
        }

        $payload = [
            'name' => $data['name'],
            'categorySubcategoryIndicator' => $data['category'],
            'merchantId' => $data['merchantId'],
            'rules' => $data['rules'],
        ];

        if ($data['description'] !== '') {
            $payload['description'] = $data['description'];
        }

        if ($data['appliesFrom'] !== null) {
            $payload['appliesDateTimeFrom'] = $data['appliesFrom']->format('Y-m-d\TH:i:sP');
        }

        $created = $this->apiClient->createPlan($payload);
        $providerPlanId = (string) $created->get('planId');
        $versionId = (string) $created->get('versionId');

        if (!$created->isSuccess() || $providerPlanId === '') {
            return $this->fail([[
                'message' => 'The provider rejected the plan: %error%',
                'params' => ['%error%' => (string) ($created->getError() ?? 'planId is missing in the response')],
            ]]);
        }

        // Mandatory second step, NOT a parameter of the first call (see SubscriptionApiClient).
        $infinite = $this->apiClient->setInfiniteSchedule($providerPlanId, $versionId);

        $plan = new AllianceSubscriptionPlan();
        $plan->setProviderPlanId($providerPlanId)
            ->setProductId($data['productId'])
            ->setTemplateId($data['templateId'])
            ->setPlanKey($data['planKey'])
            ->setVersionId($versionId !== '' ? $versionId : null)
            ->setMerchantId($data['merchantId'])
            ->setName($data['name'])
            ->setDescription($data['description'] !== '' ? $data['description'] : null)
            ->setCategoryIndicator($data['category'])
            ->setRules($data['rules'])
            ->setCurrencyCode((string) $data['rules'][0]['currency'])
            ->setAppliesFrom($data['appliesFrom']);

        if ($infinite->isSuccess()) {
            $plan->setStatus(SubscriptionConfig::PLAN_STATUS_ACTIVE)
                ->setSyncStatus(SubscriptionConfig::SYNC_STATUS_SYNCED);
        } else {
            $plan->setStatus(SubscriptionConfig::PLAN_STATUS_ERROR)
                ->setSyncStatus(SubscriptionConfig::SYNC_STATUS_ERROR)
                ->setSyncError((string) $infinite->getError());
        }

        $em->persist($plan);
        $em->flush($plan);

        $this->auditLogger->log(
            $em,
            $infinite->isSuccess() ? SubscriptionConfig::AUDIT_PLAN_CREATED : SubscriptionConfig::AUDIT_PLAN_SYNC_FAILED,
            ['provider_plan_id' => $providerPlanId, 'version_id' => $versionId, 'error' => $infinite->getError()],
            $plan->getId(),
            $data['productId'],
            null,
            $userId
        );

        if (!$infinite->isSuccess()) {
            return $this->fail([[
                'message' => 'The plan was created at the provider, but setting the infinite schedule failed: %error%. Use "Retry infinite schedule" on the plan page.',
                'params' => ['%error%' => (string) $infinite->getError()],
            ]], $plan);
        }

        return ['success' => true, 'plan' => $plan, 'errors' => []];
    }

    /**
     * @return array list of error descriptors
     */
    private function validate(int $productId, string $name, string $category, string $merchantId, array $rules): array
    {
        $errors = [];

        if ($productId <= 0) {
            $errors[] = ['message' => 'Select a product.', 'params' => []];
        }

        if ($name === '') {
            $errors[] = ['message' => 'Plan name is required.', 'params' => []];
        }

        if ($category === '') {
            $errors[] = ['message' => 'Category indicator is required.', 'params' => []];
        }

        if ($merchantId === '') {
            $errors[] = ['message' => 'Merchant ID is not configured in the module settings.', 'params' => []];
        }

        $errors = array_merge($errors, $this->ruleValidator->validate($rules));

        if (empty($errors)) {
            $currencies = array_unique(array_column($rules, 'currency'));

            if (count($currencies) > 1) {
                $errors[] = ['message' => 'All rules of a plan must use the same currency.', 'params' => []];
            }
        }

        return $errors;
    }

    private function acquireLock(EntityManagerInterface $em, string $name): bool
    {
        try {
            return (int) $em->getConnection()->fetchColumn(
                'SELECT GET_LOCK(?, ' . self::LOCK_TIMEOUT_SECONDS . ')',
                [$name]
            ) === 1;
        } catch (Throwable $e) {
            $this->logger->error('Plan lock failed: ' . $e->getMessage());

            return false;
        }
    }

    private function releaseLock(EntityManagerInterface $em, string $name): void
    {
        try {
            $em->getConnection()->fetchColumn('SELECT RELEASE_LOCK(?)', [$name]);
        } catch (Throwable $e) {
            $this->logger->error('Plan unlock failed: ' . $e->getMessage());
        }
    }

    private function fail(array $errors, ?AllianceSubscriptionPlan $plan = null): array
    {
        return ['success' => false, 'plan' => $plan, 'errors' => $errors];
    }
}
