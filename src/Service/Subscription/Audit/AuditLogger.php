<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Audit;

use AlliancePay\Entity\AllianceSubscriptionAuditLog;
use AlliancePay\Logger\AllianceLogger;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

/**
 * Class AuditLogger.
 */
class AuditLogger
{
    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(AllianceLogger $logger)
    {
        $this->logger = $logger;
    }

    public function log(
        EntityManagerInterface $em,
        string $action,
        array $details = [],
        ?int $planId = null,
        ?int $productId = null,
        ?int $subscriptionId = null,
        ?int $userId = null
    ): void {
        try {
            $entry = new AllianceSubscriptionAuditLog();
            $entry->setAction($action)
                ->setPlanId($planId)
                ->setProductId($productId)
                ->setSubscriptionId($subscriptionId)
                ->setUserId($userId)
                ->setDetails($details);
            $em->persist($entry);
            $em->flush($entry);
        } catch (Throwable $e) {
            $this->logger->error('Audit log write failed: ' . $e->getMessage());
        }
    }
}
