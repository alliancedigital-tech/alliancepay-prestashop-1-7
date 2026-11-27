<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Entity\AllianceFiscalization;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Applies a fiscalization answer (the synchronous response or the callback, both have the same structure)
 * to a record. Tolerates missing fields, never lowers a final status and ignores stale data.
 */
class FiscalizationStatusUpdater
{
    private const REMOTE_STATUSES = [
        FiscalizationStatus::PENDING,
        FiscalizationStatus::SUCCESS,
        FiscalizationStatus::FAIL,
    ];

    /**
     * @param AllianceFiscalization $entity
     * @param array $payload
     * @return bool true when the record fields were changed
     */
    public function apply(AllianceFiscalization $entity, array $payload): bool
    {
        $status = isset($payload['status']) ? strtoupper((string) $payload['status']) : null;

        if (!in_array($status, self::REMOTE_STATUSES, true)) {
            return false;
        }

        if ($entity->getStatus() === FiscalizationStatus::SUCCESS && $status !== FiscalizationStatus::SUCCESS) {
            return false;
        }

        $incomingModified = self::parseDate($payload['fiscalizationModificationDateTime'] ?? null);
        $storedModified = $entity->getFiscalizationModificationDateTime();

        if ($incomingModified !== null && $storedModified !== null && $incomingModified < $storedModified) {
            return false;
        }

        if ($incomingModified === null
            && $entity->getStatus() === FiscalizationStatus::FAIL
            && $status === FiscalizationStatus::PENDING
        ) {
            return false;
        }

        $entity->setStatus($status);

        foreach ([
            'bucketId' => 'setBucketId',
            'fiscalNumber' => 'setFiscalNumber',
            'receiptUrl' => 'setReceiptUrl',
        ] as $key => $setter) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                $entity->$setter((string) $payload[$key]);
            }
        }

        $fiscalizedAt = self::parseDate($payload['fiscalizationDateTime'] ?? null);
        if ($fiscalizedAt !== null) {
            $entity->setFiscalizationDateTime($fiscalizedAt);
        }
        if ($incomingModified !== null) {
            $entity->setFiscalizationModificationDateTime($incomingModified);
        }

        if ($status === FiscalizationStatus::SUCCESS) {
            $entity->setErrorCode(null)->setErrorDescription(null);
        } else {
            $entity->setErrorCode(isset($payload['errorCode']) ? (string) $payload['errorCode'] : null)
                ->setErrorDescription(isset($payload['errorDescription']) ? (string) $payload['errorDescription'] : null);
        }

        $entity->setUpdatedAt(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        return true;
    }

    /**
     * Parses an ISO 8601 date with a zone ("2026-09-03T10:39:09.540+03:00") into UTC.
     *
     * @param mixed $value
     */
    public static function parseDate($value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception $e) {
            return null;
        }
    }
}
