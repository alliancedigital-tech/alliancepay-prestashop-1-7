<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Entity\AllianceFiscalization;
use Message;
use Throwable;

/**
 * Adds a private note to the PrestaShop order (the order history of the administrator).
 */
class FiscalOrderNotifier
{
    /**
     * Notes a change to a final status (receipt fiscalized / fiscalization failed).
     */
    public function noteStatusChange(AllianceFiscalization $record, ?string $previousStatus): void
    {
        if ($record->getStatus() === $previousStatus) {
            return;
        }

        if ($record->getStatus() === FiscalizationStatus::SUCCESS) {
            $this->note($record->getIdOrder(), 'Receipt fiscalized, fiscal number ' . $record->getFiscalNumber());
        } elseif ($record->getStatus() === FiscalizationStatus::FAIL) {
            $this->note(
                $record->getIdOrder(),
                'Fiscalization failed: ' . $record->getErrorCode() . ' ' . $record->getErrorDescription()
            );
        }
    }

    public function note(int $orderId, string $text): void
    {
        try {
            $message = new Message();
            $message->message = mb_substr($text, 0, 1500);
            $message->id_order = $orderId;
            $message->private = 1;
            $message->add();
        } catch (Throwable $e) {
            // A failed note must not break the fiscalization flow.
        }
    }
}
