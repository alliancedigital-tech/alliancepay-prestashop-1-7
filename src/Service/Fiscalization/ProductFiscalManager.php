<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Entity\AllianceProductFiscal;
use AlliancePay\Repository\ProductFiscalRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads and saves the fiscal attributes of a product (the product form fields).
 */
class ProductFiscalManager
{
    /**
     * @return array{taxCode: ?int, classificationCode: ?string, exciseLabels: string[], measurementCode: ?string}
     */
    public function get(EntityManagerInterface $em, int $productId, int $productAttributeId = 0): array
    {
        /** @var ProductFiscalRepository $repository */
        $repository = $em->getRepository(AllianceProductFiscal::class);
        $row = $repository->findOne($productId, $productAttributeId);

        return [
            'taxCode' => $row ? $row->getTaxCode() : null,
            'classificationCode' => $row ? $row->getClassificationCode() : null,
            'exciseLabels' => $row ? (array) $row->getExciseLabels() : [],
            'measurementCode' => $row ? $row->getMeasurementCode() : null,
        ];
    }

    /**
     * Validates and stores the values; a row with only empty values is removed (full fallback to the defaults).
     *
     * @param array $input keys: taxCode, classificationCode, exciseLabels, measurementCode
     * @return string[] validation errors, nothing is saved when there are any
     */
    public function save(EntityManagerInterface $em, int $productId, int $productAttributeId, array $input): array
    {
        $result = (new FiscalSettingsValidator())->validate($input, false, false);

        if ($result['errors'] !== []) {
            return $result['errors'];
        }

        $values = $result['values'];
        /** @var ProductFiscalRepository $repository */
        $repository = $em->getRepository(AllianceProductFiscal::class);
        $row = $repository->findOne($productId, $productAttributeId);
        $isEmpty = $values['taxCode'] === null
            && $values['classificationCode'] === null
            && $values['exciseLabels'] === []
            && $values['measurementCode'] === null;

        if ($isEmpty) {
            if ($row !== null) {
                $repository->remove($row);
            }

            return [];
        }

        if ($row === null) {
            $row = (new AllianceProductFiscal())
                ->setIdProduct($productId)
                ->setIdProductAttribute($productAttributeId);
        }

        $row->setTaxCode($values['taxCode'])
            ->setClassificationCode($values['classificationCode'])
            ->setExciseLabels($values['exciseLabels'] === [] ? null : $values['exciseLabels'])
            ->setMeasurementCode($values['measurementCode'])
            ->setUpdatedAt(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $repository->save($row);

        return [];
    }
}
