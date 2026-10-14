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
 *         @ORM\Index(name="ALLIANCE_SUB_AUDIT_PLAN_ID", columns={"plan_id"}),
 *         @ORM\Index(name="ALLIANCE_SUB_AUDIT_ACTION", columns={"action"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\SubscriptionAuditLogRepository")
 */
class AllianceSubscriptionAuditLog
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
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=50, nullable=false)
     */
    private $action;

    /**
     * @var int|null
     *
     * @ORM\Column(name="plan_id", type="integer", nullable=true)
     */
    private $planId = null;

    /**
     * @var int|null
     *
     * @ORM\Column(name="product_id", type="integer", nullable=true)
     */
    private $productId = null;

    /**
     * @var int|null
     *
     * @ORM\Column(name="subscription_id", type="integer", nullable=true)
     */
    private $subscriptionId = null;

    /**
     * @var int|null
     *
     * @ORM\Column(name="user_id", type="integer", nullable=true)
     */
    private $userId = null;

    /**
     * @var array
     *
     * @ORM\Column(name="details", type="json", nullable=false)
     */
    private $details = [];

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

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getPlanId(): ?int
    {
        return $this->planId === null ? null : (int) $this->planId;
    }

    public function setPlanId(?int $planId): self
    {
        $this->planId = $planId;

        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId === null ? null : (int) $this->productId;
    }

    public function setProductId(?int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getSubscriptionId(): ?int
    {
        return $this->subscriptionId === null ? null : (int) $this->subscriptionId;
    }

    public function setSubscriptionId(?int $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId === null ? null : (int) $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function setDetails(array $details): self
    {
        $this->details = $details;

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
