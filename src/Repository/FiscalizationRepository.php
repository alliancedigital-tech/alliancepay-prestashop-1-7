<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use AlliancePay\Entity\AllianceFiscalization;
use Doctrine\ORM\EntityRepository;

/**
 * Class FiscalizationRepository.
 */
class FiscalizationRepository extends EntityRepository
{
    public function findByOperationId(string $operationId): ?AllianceFiscalization
    {
        return $this->findOneBy(['operationId' => $operationId]);
    }

    public function findByMerchantRequestId(string $merchantRequestId): ?AllianceFiscalization
    {
        return $this->findOneBy(['merchantRequestId' => $merchantRequestId]);
    }

    /**
     * @return AllianceFiscalization[]
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->findBy(['idOrder' => $orderId], ['id' => 'ASC']);
    }

    public function save(AllianceFiscalization $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush($entity);
    }
}
