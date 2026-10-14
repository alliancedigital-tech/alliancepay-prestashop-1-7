<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Plan;

use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AlliancePlanTemplate;
use AlliancePay\Entity\AllianceSubscriptionPlan;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use AlliancePay\Service\Subscription\Rules\RuleNormalizer;
use AlliancePay\Service\Subscription\Rules\RuleValidator;
use AlliancePay\Service\Subscription\Rules\TemplateRulesApplier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Plan templates: schedule structure without amounts.
 * A template already used by a plan can only be archived, never edited.
 */
class TemplateService
{
    /**
     * @var RuleNormalizer
     */
    private $ruleNormalizer;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    /**
     * @var TemplateRulesApplier
     */
    private $rulesApplier;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    public function __construct(
        RuleNormalizer $ruleNormalizer,
        RuleValidator $ruleValidator,
        TemplateRulesApplier $rulesApplier,
        AuditLogger $auditLogger
    ) {
        $this->ruleNormalizer = $ruleNormalizer;
        $this->ruleValidator = $ruleValidator;
        $this->rulesApplier = $rulesApplier;
        $this->auditLogger = $auditLogger;
    }

    public function isUsed(EntityManagerInterface $em, AlliancePlanTemplate $template): bool
    {
        return $em->getRepository(AllianceSubscriptionPlan::class)
                ->count(['templateId' => $template->getId()]) > 0;
    }

    /**
     * @param EntityManagerInterface $em
     * @param AlliancePlanTemplate|null $template null to create
     * @param array $input code, name, categoryIndicator, rows
     * @param int|null $userId
     * @return array{success: bool, template: ?AlliancePlanTemplate, errors: array}
     */
    public function save(
        EntityManagerInterface $em,
        ?AlliancePlanTemplate $template,
        array $input,
        ?int $userId = null
    ): array {
        $code = trim((string) ($input['code'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['categoryIndicator'] ?? ''));
        $rows = $this->normalizeRows((array) ($input['rows'] ?? []));
        $errors = [];

        if ($template !== null && $this->isUsed($em, $template)) {
            return $this->fail([['message' => 'A template used by plans cannot be edited, archive it instead.', 'params' => []]]);
        }

        if ($code === '' || !preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $code)) {
            $errors[] = ['message' => 'Template code is required (letters, digits, "-" and "_").', 'params' => []];
        }

        if ($name === '') {
            $errors[] = ['message' => 'Template name is required.', 'params' => []];
        }

        if ($category === '') {
            $errors[] = ['message' => 'Category indicator is required.', 'params' => []];
        }

        foreach ($rows as $row) {
            if (!in_array($row['type'], TemplateRulesApplier::STAGE_TYPES, true)) {
                $errors[] = ['message' => 'Unknown stage type.', 'params' => []];
                break;
            }
        }

        $errors = array_merge(
            $errors,
            $this->ruleValidator->validate(
                $this->ruleNormalizer->normalize($this->rulesApplier->apply($rows, 0, '980'))
            )
        );

        $duplicate = $em->getRepository(AlliancePlanTemplate::class)->findOneBy(['code' => $code]);

        if ($duplicate !== null && ($template === null || $duplicate->getId() !== $template->getId())) {
            $errors[] = ['message' => 'A template with this code already exists.', 'params' => []];
        }

        if (!empty($errors)) {
            return $this->fail($errors);
        }

        if ($template === null) {
            $template = new AlliancePlanTemplate();
            $em->persist($template);
        }

        $template->setCode($code)
            ->setName($name)
            ->setCategoryIndicator($category)
            ->setRulesTemplate($rows)
            ->setUpdatedAt(new DateTimeImmutable('now'));
        $em->flush();

        $this->auditLogger->log($em, SubscriptionConfig::AUDIT_TEMPLATE_SAVED, ['code' => $code], null, null, null, $userId);

        return ['success' => true, 'template' => $template, 'errors' => []];
    }

    public function archive(EntityManagerInterface $em, AlliancePlanTemplate $template, ?int $userId = null): void
    {
        $template->setStatus(SubscriptionConfig::TEMPLATE_STATUS_ARCHIVED)
            ->setUpdatedAt(new DateTimeImmutable('now'));
        $em->flush();

        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_TEMPLATE_ARCHIVED,
            ['code' => $template->getCode()],
            null,
            null,
            null,
            $userId
        );
    }

    /**
     * @param array $rows raw form rows
     * @return array canonical template rows
     */
    private function normalizeRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $result[] = [
                'type' => strtoupper(trim((string) ($row['type'] ?? TemplateRulesApplier::STAGE_PAID))),
                'paymentNumberFrom' => $this->toInt($row['paymentNumberFrom'] ?? null),
                'paymentNumberTo' => $this->toInt($row['paymentNumberTo'] ?? null),
                'intervalValue' => $this->toInt($row['intervalValue'] ?? null),
                'intervalUnit' => isset($row['intervalUnit']) ? strtoupper(trim((string) $row['intervalUnit'])) : null,
            ];
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return int|mixed|null numeric input becomes int, anything else is left for the validator to reject
     */
    private function toInt($value)
    {
        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : $value;
    }

    private function fail(array $errors): array
    {
        return ['success' => false, 'template' => null, 'errors' => $errors];
    }
}
