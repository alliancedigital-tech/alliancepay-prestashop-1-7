<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Fiscalization;

use AlliancePay\Entity\AllianceProductFiscal;
use AlliancePay\Repository\ProductFiscalRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Picks taxCode, classificationCode, exciseLabels and measurementCode of a product line, field by field:
 * combination value, then product value, then the module default.
 */
class FiscalAttributeResolver
{
    /**
     * @param EntityManagerInterface $em
     * @param int $productId
     * @param int $productAttributeId 0 when the line has no combination
     * @param array $defaults see Config::getFiscalDefaults()
     * @return array{taxCode: ?int, classificationCode: ?string, exciseLabels: string[], measurementCode: ?string}
     */
    public function resolve(EntityManagerInterface $em, int $productId, int $productAttributeId, array $defaults): array
    {
        /** @var ProductFiscalRepository $repository */
        $repository = $em->getRepository(AllianceProductFiscal::class);

        $combinationRow = $productAttributeId > 0 ? $repository->findOne($productId, $productAttributeId) : null;
        $productRow = $repository->findOne($productId, 0);

        return $this->resolveFromRows($combinationRow, $productRow, $defaults);
    }

    /**
     * @param AllianceProductFiscal|null $combinationRow
     * @param AllianceProductFiscal|null $productRow
     * @param array $defaults
     * @return array{taxCode: ?int, classificationCode: ?string, exciseLabels: string[], measurementCode: ?string}
     */
    public function resolveFromRows(?AllianceProductFiscal $combinationRow, ?AllianceProductFiscal $productRow, array $defaults): array
    {
        $rows = array_filter([$combinationRow, $productRow]);

        $taxCode = $defaults['taxCode'] ?? null;
        $classification = $defaults['classificationCode'] ?? null;
        $excise = $defaults['exciseLabels'] ?? [];
        $measurement = $defaults['measurementCode'] ?? null;

        foreach (array_reverse($rows) as $row) {
            // The nearest row (the combination) is applied last and wins, but only with a non-empty value.
            if ($row->getTaxCode() !== null) {
                $taxCode = $row->getTaxCode();
            }
            if ($row->getClassificationCode() !== null && $row->getClassificationCode() !== '') {
                $classification = $row->getClassificationCode();
            }
            if (!empty($row->getExciseLabels())) {
                $excise = array_values($row->getExciseLabels());
            }
            if ($row->getMeasurementCode() !== null && $row->getMeasurementCode() !== '') {
                $measurement = $row->getMeasurementCode();
            }
        }

        return [
            'taxCode' => $taxCode === null ? null : (int) $taxCode,
            'classificationCode' => $classification === '' ? null : $classification,
            'exciseLabels' => array_values($excise),
            'measurementCode' => $measurement === '' ? null : $measurement,
        ];
    }
}
