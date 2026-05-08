<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Validator;

use DateTime;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use AlliancePay\Model\DateTime\DateTimeNormalizer;
use AlliancePay\Logger\AllianceLogger as Logger;

/**
 * Class CustomerDataValidator.
 */
class CustomerDataValidator
{
    /**
     * Gateway field validation rules.
     *
     * Supported rule keys:
     *  - type string|ip|numeric_string|email|date
     *  - max_len int — maximum allowed mb_strlen
     *  - required bool — field must be present and non-empty
     *  - no_only_digits bool — value must not consist of digits only
     *  - pattern string — PCRE pattern the value must match
     *  - stop_words string[] — exact (case-insensitive) forbidden values
     *  - date_format string — PHP date format string used for date type validation
     */
    private const CUSTOMER_DATA_RULES = [
        'senderBirthday' => [
            'type' => 'date',
            'max_len' => 50,
            'required' => false,
            'date_format' => 'd.m.Y',
        ],
        'senderCustomerId' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => true,
        ],
        'senderFirstName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
            'stop_words' => ['NULL', '3D SECURE', 'SURNAME', 'CARDHOLDER', 'UNKNOWN'],
        ],
        'senderLastName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
        ],
        'senderMiddleName' => [
            'type' => 'string',
            'max_len' => 30,
            'required' => false,
            'no_only_digits' => true,
            'pattern' =>
                '/^[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ]([a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ\s\-\']*[a-zA-Z0-9а-яА-ЯёЁіІїЇєЄґҐ])?$/u',
        ],
        'senderEmail' => [
            'type' => 'email',
            'max_len' => 256,
            'required' => false,
        ],
        'senderCountry' => [
            'type' => 'string',
            'max_len' => 3,
            'required' => false,
        ],
        'senderRegion' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false,
        ],
        'senderCity' => [
            'type' => 'string',
            'max_len' => 25,
            'required' => false,
        ],
        'senderStreet' => [
            'type' => 'string',
            'max_len' => 35,
            'required' => false,
        ],
        'senderAdditionalAddress' => [
            'type' => 'string',
            'max_len' => 255,
            'required' => false,
        ],
        'senderIp' => [
            'type' => 'ip',
        ],
        'senderPhone' => [
            'type' => 'numeric_string',
            'max_len' => 20,
            'required' => false,
        ],
        'senderZipCode' => [
            'type' => 'string',
            'max_len' => 50,
            'required' => false,
        ],
    ];

    private $dateTimeNormalizer;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        DateTimeNormalizer $dateTimeNormalizer,
        Logger $logger
    ) {
        $this->dateTimeNormalizer = $dateTimeNormalizer;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function validate(array $data): array
    {
        $clean = $this->normalizeBirthday($data);

        foreach (self::CUSTOMER_DATA_RULES as $field => $rules) {
            $value = $clean[$field] ?? null;
            $required = $rules['required'] ?? false;
            $type = $rules['type'];

            if ($value === null || $value === '') {
                if ($required) {
                    throw new LocalizationException(
                        sprintf('Alliance Pay: required customer data field "%1" is missing or empty.',
                            $field
                        )
                    );
                }

                unset($clean[$field]);
                continue;
            }

            $stringValue = (string)$value;

            if ($type === 'ip') {
                $violations = $this->checkIp($stringValue);
            } elseif ($type === 'email') {
                $violations = $this->checkEmail($stringValue, $rules);
            } elseif ($type === 'numeric_string') {
                $violations = $this->checkNumericString($stringValue, $rules);
            } elseif ($type === 'date') {
                $violations = $this->checkDate($stringValue, $rules);
            } else {
                $violations = $this->checkString($stringValue, $rules);
            }

            if (!empty($violations)) {
                foreach ($violations as $message) {
                    $this->logger->warning(
                        sprintf(
                            'Alliance Pay customer data validation: field "%s" removed. Reason: %s',
                            $field,
                            $message
                        )
                    );
                }

                if ($required) {
                    throw new LocalizationException(
                        sprintf('Alliance Pay: required customer data field "%1" failed validation.',
                            $field
                        )
                    );
                }

                unset($clean[$field]);
            }
        }

        return $clean;
    }

    /**
     * @param array $data
     * @return array
     */
    private function normalizeBirthday(array $data): array
    {
        if (empty($data['senderBirthday'])) {
            return $data;
        }

        $normalized = $this->dateTimeNormalizer->formatBirthday((string)$data['senderBirthday']);

        if ($normalized === null) {
            $this->logger->warning(
                sprintf(
                    'Alliance Pay customer data validation: field "senderBirthday" '
                    . 'could not be normalized from value "%s". Field will be validated as-is.',
                    $data['senderBirthday']
                )
            );

            return $data;
        }

        $data['senderBirthday'] = $normalized;

        return $data;
    }

    /**
     * @param string $value
     * @param array $rules
     * @return array
     */
    private function checkString(string $value, array $rules): array
    {
        $violations = [];

        if (isset($rules['max_len']) && !$this->validateMaxLength($value, $rules['max_len'])) {
            $violations[] = sprintf(
                'exceeds max length %d (actual %d)',
                $rules['max_len'],
                mb_strlen($value)
            );
        }

        if (!empty($rules['no_only_digits']) && !$this->validateNoOnlyDigits($value)) {
            $violations[] = 'value consists of digits only';
        }

        if (!empty($rules['pattern']) && !$this->validatePattern($value, $rules['pattern'])) {
            $violations[] = sprintf('does not match required pattern "%s"', $rules['pattern']);
        }

        if (!empty($rules['stop_words']) && !$this->validateStopWords($value, $rules['stop_words'])) {
            $violations[] = sprintf('matches a forbidden stop-word (value: "%s")', $value);
        }

        return $violations;
    }

    /**
     * @param string $value
     * @return array
     */
    private function checkIp(string $value): array
    {
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return [sprintf('"%s" is not a valid IP address', $value)];
        }

        return [];
    }

    /**
     * @param string $value
     * @param array $rules
     * @return array
     */
    private function checkEmail(string $value, array $rules): array
    {
        $violations = [];

        if (isset($rules['max_len']) && !$this->validateMaxLength($value, $rules['max_len'])) {
            $violations[] = sprintf(
                'exceeds max length %d (actual %d)',
                $rules['max_len'],
                mb_strlen($value)
            );
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $violations[] = sprintf('"%s" is not a valid e-mail address', $value);
        }

        return $violations;
    }

    /**
     * @param string $value
     * @param array $rules
     * @return array
     */
    private function checkNumericString(string $value, array $rules): array
    {
        $violations = [];

        if (!$this->validateNumericString($value)) {
            $violations[] = 'value contains non-digit characters';
        }

        if (isset($rules['max_len']) && !$this->validateMaxLength($value, $rules['max_len'])) {
            $violations[] = sprintf(
                'exceeds max length %d (actual %d)',
                $rules['max_len'],
                mb_strlen($value)
            );
        }

        return $violations;
    }

    /**
     * @param string $value
     * @param array $rules
     * @return array
     */
    private function checkDate(string $value, array $rules): array
    {
        $violations = [];

        if (isset($rules['max_len']) && !$this->validateMaxLength($value, $rules['max_len'])) {
            $violations[] = sprintf(
                'exceeds max length %d (actual %d)',
                $rules['max_len'],
                mb_strlen($value)
            );
        }

        $format = $rules['date_format'] ?? 'd.m.Y';
        if (!$this->validateDateFormat($value, $format)) {
            $violations[] = sprintf('"%s" is not a valid date in format "%s"', $value, $format);
        }

        return $violations;
    }

    /**
     * @param string $value
     * @param int $maxLen
     * @return bool
     */
    private function validateMaxLength(string $value, int $maxLen): bool
    {
        return mb_strlen($value) <= $maxLen;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function validateNoOnlyDigits(string $value): bool
    {
        return !ctype_digit($value) && !empty(trim($value));
    }

    /**
     * @param string $value
     * @param string $pattern
     * @return bool
     */
    private function validatePattern(string $value, string $pattern): bool
    {
        return (bool)preg_match($pattern, $value);
    }

    /**
     * @param string $value
     * @param array $stopWords
     * @return bool
     */
    private function validateStopWords(string $value, array $stopWords): bool
    {
        return !in_array(strtoupper($value), $stopWords, true);
    }

    /**
     * @param string $value
     * @return bool
     */
    private function validateNumericString(string $value): bool
    {
        return ctype_digit($value);
    }

    /**
     * @param string $value
     * @param string $format
     * @return bool
     */
    private function validateDateFormat(string $value, string $format): bool
    {
        $date = DateTime::createFromFormat($format, $value);

        if ($date === false) {
            return false;
        }

        $errors = DateTime::getLastErrors();

        return empty($errors['warning_count']) && empty($errors['error_count']);
    }
}
