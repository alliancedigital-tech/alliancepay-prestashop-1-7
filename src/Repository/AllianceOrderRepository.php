<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * Class AllianceOrderRepository.
 */
class AllianceOrderRepository extends EntityRepository
{
    /**
     * @param string $hppOrderId
     * @return float|int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function findByHppOrderId(string $hppOrderId)
    {
        $queryBuilder = $this->createQueryBuilder('o');
        $queryBuilder->andWhere('o.hppOrderId = :hpp_order_id');
        $queryBuilder->setParameter('hpp_order_id', $hppOrderId);
        $queryBuilder->orderBy('o.id', 'DESC');
        $query = $queryBuilder->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * @param string $orderId
     * @return float|int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function findByOrderId(string $orderId)
    {
        $queryBuilder = $this->createQueryBuilder('o');
        $queryBuilder->andWhere('o.orderId = :order_id');
        $queryBuilder->setParameter('order_id', $orderId);
        $query = $queryBuilder->getQuery();

        return $query->getOneOrNullResult();
    }
}
