<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiscal attributes of a product or of one combination (id_product_attribute = 0 means the product itself).
 * Keep in sync with AlliancePay\Install\FiscalizationSchema.
 *
 * @ORM\Table(
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ALLIANCE_PRODUCT_FISCAL_PRODUCT", columns={"id_product", "id_product_attribute"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\ProductFiscalRepository")
 */
class AllianceProductFiscal
{
    /**
     * @var int|null
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer", nullable=false)
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product", type="integer", nullable=false)
     */
    private $idProduct;

    /**
     * @var int
     *
     * @ORM\Column(name="id_product_attribute", type="integer", nullable=false)
     */
    private $idProductAttribute = 0;

    /**
     * @var int|null
     *
     * @ORM\Column(name="tax_code", type="integer", nullable=true)
     */
    private $taxCode = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="classification_code", type="string", length=32, nullable=true)
     */
    private $classificationCode = null;

    /**
     * @var array|null
     *
     * @ORM\Column(name="excise_labels", type="json", nullable=true)
     */
    private $exciseLabels = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="measurement_code", type="string", length=16, nullable=true)
     */
    private $measurementCode = null;

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    /**
     * @var DateTimeImmutable
     *
     * @ORM\Column(name="updated_at", type="datetime_immutable", nullable=false)
     */
    private $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getIdProduct(): int
    {
        return (int) $this->idProduct;
    }

    public function setIdProduct(int $idProduct): self
    {
        $this->idProduct = $idProduct;

        return $this;
    }

    public function getIdProductAttribute(): int
    {
        return (int) $this->idProductAttribute;
    }

    public function setIdProductAttribute(int $idProductAttribute): self
    {
        $this->idProductAttribute = $idProductAttribute;

        return $this;
    }

    public function getTaxCode(): ?int
    {
        return $this->taxCode === null ? null : (int) $this->taxCode;
    }

    public function setTaxCode(?int $taxCode): self
    {
        $this->taxCode = $taxCode;

        return $this;
    }

    public function getClassificationCode(): ?string
    {
        return $this->classificationCode;
    }

    public function setClassificationCode(?string $classificationCode): self
    {
        $this->classificationCode = $classificationCode;

        return $this;
    }

    public function getExciseLabels(): ?array
    {
        return $this->exciseLabels;
    }

    public function setExciseLabels(?array $exciseLabels): self
    {
        $this->exciseLabels = $exciseLabels;

        return $this;
    }

    public function getMeasurementCode(): ?string
    {
        return $this->measurementCode;
    }

    public function setMeasurementCode(?string $measurementCode): self
    {
        $this->measurementCode = $measurementCode;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
