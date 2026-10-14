<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use AlliancePay\Config\SubscriptionConfig;
use Doctrine\ORM\EntityRepository;

class SubscriptionPlanRepository extends EntityRepository
{
    /**
     * Plans offered to NEW customers of a product. A product may have several (e.g. monthly and yearly).
     *
     * @param int $productId
     * @return \AlliancePay\Entity\AllianceSubscriptionPlan[]
     */
    public function findOfferedByProduct(int $productId): array
    {
        return $this->findBy(
            [
                'productId' => $productId,
                'isActive' => true,
                'status' => SubscriptionConfig::PLAN_STATUS_ACTIVE,
            ],
            ['id' => 'ASC']
        );
    }
}
