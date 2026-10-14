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
 *         @ORM\UniqueConstraint(name="ALLIANCE_SCHED_PAY_SUB_SCHED_ID", columns={"subscription_id", "scheduled_payment_id"})
 *     },
 *     indexes={
 *         @ORM\Index(name="ALLIANCE_SCHED_PAY_SUBSCRIPTION_ID", columns={"subscription_id"}),
 *         @ORM\Index(name="ALLIANCE_SCHED_PAY_OPERATION_ID", columns={"operation_id"}),
 *         @ORM\Index(name="ALLIANCE_SCHED_PAY_PROVIDER_ORDER_ID", columns={"provider_order_id"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\ScheduledPaymentRepository")
 */
class AllianceScheduledPayment
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
     * @ORM\Column(name="subscription_id", type="integer", nullable=false)
     */
    private $subscriptionId;

    /**
     * @var string
     *
     * @ORM\Column(name="provider_order_id", type="string", length=255, nullable=false)
     */
    private $providerOrderId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="scheduled_payment_id", type="string", length=255, nullable=true)
     */
    private $scheduledPaymentId = null;

    /**
     * @var int|null
     *
     * @ORM\Column(name="payment_number", type="integer", nullable=true)
     */
    private $paymentNumber = null;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=20, nullable=false)
     */
    private $status = 'WAITING';

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="scheduled_date", type="datetime_immutable", nullable=true)
     */
    private $scheduledDate = null;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="executed_at", type="datetime_immutable", nullable=true)
     */
    private $executedAt = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="operation_id", type="string", length=255, nullable=true)
     */
    private $operationId = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="operation_status", type="string", length=50, nullable=true)
     */
    private $operationStatus = null;

    /**
     * @var array
     *
     * @ORM\Column(name="operation_ids", type="json", nullable=false)
     */
    private $operationIds = [];

    /**
     * @var int|null
     *
     * @ORM\Column(name="coin_amount", type="integer", nullable=true)
     */
    private $coinAmount = null;

    /**
     * @var int
     *
     * @ORM\Column(name="retry_count", type="integer", nullable=false)
     */
    private $retryCount = 0;

    /**
     * @var bool
     *
     * @ORM\Column(name="needs_review", type="boolean", nullable=false)
     */
    private $reviewRequired = false;

    /**
     * @var string|null
     *
     * @ORM\Column(name="review_note", type="text", nullable=true)
     */
    private $reviewNote = null;

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

    public function getSubscriptionId(): int
    {
        return (int) $this->subscriptionId;
    }

    public function setSubscriptionId(int $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

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

    public function getScheduledPaymentId(): ?string
    {
        return $this->scheduledPaymentId;
    }

    public function setScheduledPaymentId(?string $scheduledPaymentId): self
    {
        $this->scheduledPaymentId = $scheduledPaymentId;

        return $this;
    }

    public function getPaymentNumber(): ?int
    {
        return $this->paymentNumber === null ? null : (int) $this->paymentNumber;
    }

    public function setPaymentNumber(?int $paymentNumber): self
    {
        $this->paymentNumber = $paymentNumber;

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

    public function getScheduledDate(): ?DateTimeImmutable
    {
        return $this->scheduledDate;
    }

    public function setScheduledDate(?DateTimeImmutable $scheduledDate): self
    {
        $this->scheduledDate = $scheduledDate;

        return $this;
    }

    public function getExecutedAt(): ?DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function setExecutedAt(?DateTimeImmutable $executedAt): self
    {
        $this->executedAt = $executedAt;

        return $this;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): self
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function getOperationStatus(): ?string
    {
        return $this->operationStatus;
    }

    public function setOperationStatus(?string $operationStatus): self
    {
        $this->operationStatus = $operationStatus;

        return $this;
    }

    public function getOperationIds(): array
    {
        return $this->operationIds;
    }

    public function setOperationIds(array $operationIds): self
    {
        $this->operationIds = $operationIds;

        return $this;
    }

    public function getCoinAmount(): ?int
    {
        return $this->coinAmount === null ? null : (int) $this->coinAmount;
    }

    public function setCoinAmount(?int $coinAmount): self
    {
        $this->coinAmount = $coinAmount;

        return $this;
    }

    public function getRetryCount(): int
    {
        return (int) $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;

        return $this;
    }

    public function isReviewRequired(): bool
    {
        return (bool) $this->reviewRequired;
    }

    public function setReviewRequired(bool $reviewRequired): self
    {
        $this->reviewRequired = $reviewRequired;

        return $this;
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    public function setReviewNote(?string $reviewNote): self
    {
        $this->reviewNote = $reviewNote;

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
