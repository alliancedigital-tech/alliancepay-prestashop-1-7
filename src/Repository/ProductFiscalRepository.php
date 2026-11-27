<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Repository;

use AlliancePay\Entity\AllianceProductFiscal;
use Doctrine\ORM\EntityRepository;

/**
 * Class ProductFiscalRepository.
 */
class ProductFiscalRepository extends EntityRepository
{
    public function findOne(int $productId, int $productAttributeId = 0): ?AllianceProductFiscal
    {
        return $this->findOneBy(['idProduct' => $productId, 'idProductAttribute' => $productAttributeId]);
    }

    public function save(AllianceProductFiscal $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush($entity);
    }

    public function remove(AllianceProductFiscal $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush($entity);
    }
}
