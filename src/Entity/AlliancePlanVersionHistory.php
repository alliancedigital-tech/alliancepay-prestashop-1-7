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
 * Generated from the subscription schema spec, keep in sync with AlliancePay\Install\SubscriptionSchema.
 *
 * @ORM\Table(
 *     indexes={
 *         @ORM\Index(name="ALLIANCE_PLAN_VERSION_PLAN_ID", columns={"plan_id"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\PlanVersionHistoryRepository")
 */
class AlliancePlanVersionHistory
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
     * @ORM\Column(name="plan_id", type="integer", nullable=false)
     */
    private $planId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="old_version_id", type="string", length=255, nullable=true)
     */
    private $oldVersionId = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="new_version_id", type="string", length=255, nullable=true)
     */
    private $newVersionId = null;

    /**
     * @var array
     *
     * @ORM\Column(name="old_rules", type="json", nullable=false)
     */
    private $oldRules = [];

    /**
     * @var array
     *
     * @ORM\Column(name="new_rules", type="json", nullable=false)
     */
    private $newRules = [];

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="applies_from", type="datetime_immutable", nullable=true)
     */
    private $appliesFrom = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="reason", type="text", nullable=true)
     */
    private $reason = null;

    /**
     * @var int|null
     *
     * @ORM\Column(name="created_by", type="integer", nullable=true)
     */
    private $createdBy = null;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getPlanId(): int
    {
        return (int) $this->planId;
    }

    public function setPlanId(int $planId): self
    {
        $this->planId = $planId;

        return $this;
    }

    public function getOldVersionId(): ?string
    {
        return $this->oldVersionId;
    }

    public function setOldVersionId(?string $oldVersionId): self
    {
        $this->oldVersionId = $oldVersionId;

        return $this;
    }

    public function getNewVersionId(): ?string
    {
        return $this->newVersionId;
    }

    public function setNewVersionId(?string $newVersionId): self
    {
        $this->newVersionId = $newVersionId;

        return $this;
    }

    public function getOldRules(): array
    {
        return $this->oldRules;
    }

    public function setOldRules(array $oldRules): self
    {
        $this->oldRules = $oldRules;

        return $this;
    }

    public function getNewRules(): array
    {
        return $this->newRules;
    }

    public function setNewRules(array $newRules): self
    {
        $this->newRules = $newRules;

        return $this;
    }

    public function getAppliesFrom(): ?DateTimeImmutable
    {
        return $this->appliesFrom;
    }

    public function setAppliesFrom(?DateTimeImmutable $appliesFrom): self
    {
        $this->appliesFrom = $appliesFrom;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy === null ? null : (int) $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;

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
}
