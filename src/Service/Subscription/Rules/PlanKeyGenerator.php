<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Rules;

/**
 * Class PlanKeyGenerator.
 */
class PlanKeyGenerator
{
    /**
     * @var RuleNormalizer
     */
    private $ruleNormalizer;

    public function __construct(RuleNormalizer $ruleNormalizer)
    {
        $this->ruleNormalizer = $ruleNormalizer;
    }

    public function generate(
        string $merchantId,
        int $productId,
        ?int $templateId,
        string $categoryIndicator,
        array $rules
    ): string {
        return hash('sha256', json_encode([
            'merchantId' => $merchantId,
            'productId' => $productId,
            'templateId' => $templateId ?? 0,
            'categoryIndicator' => $categoryIndicator,
            'rules' => $this->ruleNormalizer->normalize($rules),
        ]));
    }
}
