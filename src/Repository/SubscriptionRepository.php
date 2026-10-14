<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use AlliancePay\Config\SubscriptionConfig;
use Doctrine\ORM\EntityRepository;

class SubscriptionRepository extends EntityRepository
{
    /**
     * @param int $customerId
     * @return \AlliancePay\Entity\AllianceSubscription[]
     */
    public function findByCustomer(int $customerId): array
    {
        return $this->findBy(['customerId' => $customerId], ['id' => 'DESC']);
    }

    /**
     * @param int[] $planIds empty = all plans
     * @return array<int, int> running subscriptions count by plan id, plans without any are absent
     */
    public function countRunningByPlan(array $planIds = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.planId AS planId', 'COUNT(s.id) AS total')
            ->where('s.status IN (:statuses)')
            ->setParameter('statuses', SubscriptionConfig::SUBSCRIPTION_RUNNING_STATUSES)
            ->groupBy('s.planId');

        if ($planIds !== []) {
            $qb->andWhere('s.planId IN (:planIds)')->setParameter('planIds', array_map('intval', $planIds));
        }

        $counts = [];

        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[(int) $row['planId']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countRunningForPlan(int $planId): int
    {
        return $this->countRunningByPlan([$planId])[$planId] ?? 0;
    }
}
