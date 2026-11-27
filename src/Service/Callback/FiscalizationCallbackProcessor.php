<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Callback;

use AlliancePay\Entity\AllianceFiscalization;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Repository\FiscalizationRepository;
use AlliancePay\Service\Fiscalization\FiscalizationStatusUpdater;
use AlliancePay\Service\Fiscalization\FiscalOrderNotifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies the asynchronous result of a fiscalization (a verified callback payload) to its record.
 */
class FiscalizationCallbackProcessor
{
    /**
     * @var FiscalizationStatusUpdater
     */
    private $statusUpdater;

    /**
     * @var FiscalOrderNotifier
     */
    private $notifier;

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(
        FiscalizationStatusUpdater $statusUpdater,
        FiscalOrderNotifier $notifier,
        AllianceLogger $logger
    ) {
        $this->statusUpdater = $statusUpdater;
        $this->notifier = $notifier;
        $this->logger = $logger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param array $data verified callback payload, same structure as the synchronous response
     * @return bool false when no record matches the callback (it is logged and acknowledged anyway)
     */
    public function process(EntityManagerInterface $em, array $data): bool
    {
        /** @var FiscalizationRepository $repository */
        $repository = $em->getRepository(AllianceFiscalization::class);
        $record = null;

        if (!empty($data['operationId'])) {
            $record = $repository->findByOperationId((string) $data['operationId']);
        }

        if ($record === null && !empty($data['merchantRequestId'])) {
            $record = $repository->findByMerchantRequestId((string) $data['merchantRequestId']);
        }

        if ($record === null) {
            $this->logger->warning('Fiscalization callback for an unknown operation: ' . (string) ($data['operationId'] ?? ''));

            return false;
        }

        $previousStatus = $record->getStatus();
        $this->statusUpdater->apply($record, $data);
        $record->setCallbackPayload($data);
        $repository->save($record);
        $this->notifier->noteStatusChange($record, $previousStatus);

        return true;
    }
}
