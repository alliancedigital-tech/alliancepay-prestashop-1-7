<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

/**
 * Validates and normalizes the fiscal fields (taxCode, classificationCode, exciseLabels, measurementCode),
 * shared by the module settings and the product form.
 */
class FiscalSettingsValidator
{
    /**
     * taxCode values for which classificationCode (and exciseLabels of excise goods) are required.
     */
    public const TAX_CODES_REQUIRING_CLASSIFICATION = [2, 7, 8];

    public static function requiresClassification(?int $taxCode): bool
    {
        return $taxCode !== null && in_array($taxCode, self::TAX_CODES_REQUIRING_CLASSIFICATION, true);
    }

    /**
     * @param array $input keys: taxCode, classificationCode, exciseLabels (string or array), measurementCode
     * @param bool $measurementRequired true for the module settings, false for the product form (fallback to default)
     * @param bool $classificationRequiredByTaxCode true for the module settings (defaults must be complete)
     * @return array{values: array, errors: string[]}
     */
    public function validate(array $input, bool $measurementRequired, bool $classificationRequiredByTaxCode): array
    {
        $errors = [];
        $values = ['taxCode' => null, 'classificationCode' => null, 'exciseLabels' => [], 'measurementCode' => null];

        $taxCode = trim((string) ($input['taxCode'] ?? ''));
        if ($taxCode !== '') {
            if (!preg_match('/^\d+$/', $taxCode)) {
                $errors[] = 'Tax code must be an integer.';
            } else {
                $values['taxCode'] = (int) $taxCode;
            }
        }

        $classification = trim((string) ($input['classificationCode'] ?? ''));
        if ($classification !== '') {
            if (!preg_match('/^\d+$/', $classification)) {
                $errors[] = 'UKTZED code must contain digits only.';
            } else {
                $values['classificationCode'] = $classification;
            }
        }

        $values['exciseLabels'] = self::parseExciseLabels($input['exciseLabels'] ?? '');

        $measurement = trim((string) ($input['measurementCode'] ?? ''));
        if ($measurement === '') {
            if ($measurementRequired) {
                $errors[] = 'Measurement code is required.';
            }
        } else {
            $values['measurementCode'] = $measurement;
        }

        if ($classificationRequiredByTaxCode
            && self::requiresClassification($values['taxCode'])
            && $values['classificationCode'] === null
        ) {
            $errors[] = 'UKTZED code is required when the tax code is 2, 7 or 8.';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param string|array $raw comma / newline separated string or a list
     * @return string[]
     */
    public static function parseExciseLabels($raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/[,\r\n]+/', (string) $raw) ?: [];
        }

        $labels = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $labels[] = $item;
            }
        }

        return array_values(array_unique($labels));
    }
}
