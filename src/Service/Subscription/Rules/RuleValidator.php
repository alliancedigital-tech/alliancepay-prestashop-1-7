<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Rules;

use AlliancePay\Config\SubscriptionConfig;

/**
 * Class RuleValidator.
 */
class RuleValidator
{
    /**
     * @param array $rules normalized rules (see RuleNormalizer)
     * @return array[] list of errors, empty when the rules are valid
     */
    public function validate(array $rules): array
    {
        $errors = [];

        if (empty($rules)) {
            return [['message' => 'At least one rule is required.', 'params' => []]];
        }

        foreach ($rules as $index => $rule) {
            $number = $index + 1;

            foreach (SubscriptionConfig::RULE_REQUIRED_FIELDS as $field) {
                if (!array_key_exists($field, $rule) || $rule[$field] === null || $rule[$field] === '') {
                    $errors[] = [
                        'message' => 'Rule #%rule%: field "%field%" is required.',
                        'params' => ['%rule%' => $number, '%field%' => $field],
                    ];
                }
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        $expectedFrom = 1;

        foreach ($rules as $index => $rule) {
            $number = $index + 1;

            $hasTypeError = false;

            foreach (['paymentNumberFrom', 'paymentNumberTo', 'intervalValue', 'coinAmount'] as $field) {
                if (!is_int($rule[$field])) {
                    $hasTypeError = true;
                    $errors[] = [
                        'message' => 'Rule #%rule%: field "%field%" must be an integer.',
                        'params' => ['%rule%' => $number, '%field%' => $field],
                    ];
                }
            }

            if ($hasTypeError) {
                continue;
            }

            if ($rule['paymentNumberFrom'] < 1 || $rule['paymentNumberTo'] < $rule['paymentNumberFrom']) {
                $errors[] = [
                    'message' => 'Rule #%rule%: invalid payment number range.',
                    'params' => ['%rule%' => $number],
                ];
            }

            if ($rule['intervalValue'] < 1) {
                $errors[] = [
                    'message' => 'Rule #%rule%: interval must be at least 1.',
                    'params' => ['%rule%' => $number],
                ];
            }

            if ($rule['coinAmount'] < 0) {
                $errors[] = [
                    'message' => 'Rule #%rule%: amount cannot be negative.',
                    'params' => ['%rule%' => $number],
                ];
            }

            if (!in_array($rule['intervalUnit'], SubscriptionConfig::INTERVAL_UNITS, true)) {
                $errors[] = [
                    'message' => 'Rule #%rule%: interval unit must be one of %units%.',
                    'params' => ['%rule%' => $number, '%units%' => implode(', ', SubscriptionConfig::INTERVAL_UNITS)],
                ];
            }

            if (!preg_match('/^\d{3}$/', (string) $rule['currency'])) {
                $errors[] = [
                    'message' => 'Rule #%rule%: currency must be a numeric ISO 4217 code (e.g. 980).',
                    'params' => ['%rule%' => $number],
                ];
            }

            if ($rule['paymentNumberFrom'] !== $expectedFrom) {
                $errors[] = [
                    'message' => 'Rule #%rule%: payment number ranges must be contiguous, expected start %expected%.',
                    'params' => ['%rule%' => $number, '%expected%' => $expectedFrom],
                ];
            }

            $expectedFrom = (int) $rule['paymentNumberTo'] + 1;
        }

        return $errors;
    }
}
