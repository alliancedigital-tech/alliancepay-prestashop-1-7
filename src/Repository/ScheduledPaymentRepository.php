<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use Doctrine\ORM\EntityRepository;

class ScheduledPaymentRepository extends EntityRepository
{
    public function getMaxPaymentNumber(int $subscriptionId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('MAX(p.paymentNumber)')
            ->andWhere('p.subscriptionId = :sid')
            ->setParameter('sid', $subscriptionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int $subscriptionId
     * @return \AlliancePay\Entity\AllianceScheduledPayment[]
     */
    public function findBySubscription(int $subscriptionId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.subscriptionId = :sid')
            ->setParameter('sid', $subscriptionId)
            ->orderBy('p.paymentNumber', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
