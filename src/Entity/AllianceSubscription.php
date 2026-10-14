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
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ALLIANCE_SUB_PROVIDER_ORDER_ID", columns={"provider_order_id"})
 *     },
 *     indexes={
 *         @ORM\Index(name="ALLIANCE_SUB_ORDER_ID", columns={"order_id"}),
 *         @ORM\Index(name="ALLIANCE_SUB_CUSTOMER_ID", columns={"customer_id"}),
 *         @ORM\Index(name="ALLIANCE_SUB_PLAN_ID", columns={"plan_id"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\SubscriptionRepository")
 */
class AllianceSubscription
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
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId;

    /**
     * @var int
     *
     * @ORM\Column(name="customer_id", type="integer", nullable=false)
     */
    private $customerId;

    /**
     * @var int
     *
     * @ORM\Column(name="plan_id", type="integer", nullable=false)
     */
    private $planId;

    /**
     * @var array
     *
     * @ORM\Column(name="rules_snapshot", type="json", nullable=false)
     */
    private $rulesSnapshot = [];

    /**
     * @var string
     *
     * @ORM\Column(name="provider_order_id", type="string", length=255, nullable=false)
     */
    private $providerOrderId;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_request_id", type="string", length=255, nullable=false)
     */
    private $merchantRequestId;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=20, nullable=false)
     */
    private $status = 'PENDING';

    /**
     * @var array
     *
     * @ORM\Column(name="callback_data", type="json", nullable=false)
     */
    private $callbackData = [];

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="created_at", type="datetime_immutable", nullable=false)
     */
    private $createdAt;

    /**
     * @var \DateTimeImmutable
     *
     * @ORM\Column(name="updated_at", type="datetime_immutable", nullable=false)
     */
    private $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->updatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id === null ? null : (int) $this->id;
    }

    public function getOrderId(): int
    {
        return (int) $this->orderId;
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getCustomerId(): int
    {
        return (int) $this->customerId;
    }

    public function setCustomerId(int $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
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

    public function getRulesSnapshot(): array
    {
        return $this->rulesSnapshot;
    }

    public function setRulesSnapshot(array $rulesSnapshot): self
    {
        $this->rulesSnapshot = $rulesSnapshot;

        return $this;
    }

    public function getProviderOrderId(): string
    {
        return $this->providerOrderId;
    }

    public function setProviderOrderId(string $providerOrderId): self
    {
        $this->providerOrderId = $providerOrderId;

        return $this;
    }

    public function getMerchantRequestId(): string
    {
        return $this->merchantRequestId;
    }

    public function setMerchantRequestId(string $merchantRequestId): self
    {
        $this->merchantRequestId = $merchantRequestId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCallbackData(): array
    {
        return $this->callbackData;
    }

    public function setCallbackData(array $callbackData): self
    {
        $this->callbackData = $callbackData;

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
