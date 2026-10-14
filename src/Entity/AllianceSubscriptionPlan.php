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
 *         @ORM\UniqueConstraint(name="ALLIANCE_SUB_PLAN_PROVIDER_PLAN_ID", columns={"provider_plan_id"}),
 *         @ORM\UniqueConstraint(name="ALLIANCE_SUB_PLAN_PLAN_KEY", columns={"plan_key"})
 *     },
 *     indexes={
 *         @ORM\Index(name="ALLIANCE_SUB_PLAN_PRODUCT_ID", columns={"product_id"}),
 *         @ORM\Index(name="ALLIANCE_SUB_PLAN_STATUS", columns={"status"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\SubscriptionPlanRepository")
 */
class AllianceSubscriptionPlan
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
     * @ORM\Column(name="provider_plan_id", type="string", length=255, nullable=false)
     */
    private $providerPlanId;

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", nullable=false)
     */
    private $productId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="template_id", type="integer", nullable=true)
     */
    private $templateId = null;

    /**
     * @var string
     *
     * @ORM\Column(name="plan_key", type="string", length=64, nullable=false)
     */
    private $planKey;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_active", type="boolean", nullable=false)
     */
    private $isActive = true;

    /**
     * @var int
     *
     * @ORM\Column(name="active_subscriptions_count", type="integer", nullable=false)
     */
    private $activeSubscriptionsCount = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="sync_status", type="string", length=20, nullable=false)
     */
    private $syncStatus = 'synced';

    /**
     * @var string|null
     *
     * @ORM\Column(name="sync_error", type="text", nullable=true)
     */
    private $syncError = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="version_id", type="string", length=255, nullable=true)
     */
    private $versionId = null;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_id", type="string", length=255, nullable=false)
     */
    private $merchantId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description = null;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=20, nullable=false)
     */
    private $status = 'ACTIVE';

    /**
     * @var string
     *
     * @ORM\Column(name="category_indicator", type="string", length=100, nullable=false)
     */
    private $categoryIndicator;

    /**
     * @var array
     *
     * @ORM\Column(name="rules", type="json", nullable=false)
     */
    private $rules = [];

    /**
     * @var string
     *
     * @ORM\Column(name="currency_code", type="string", length=10, nullable=false)
     */
    private $currencyCode;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="applies_from", type="datetime_immutable", nullable=true)
     */
    private $appliesFrom = null;

    /**
     * @var \DateTimeImmutable|null
     *
     * @ORM\Column(name="applies_to", type="datetime_immutable", nullable=true)
     */
    private $appliesTo = null;

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

    public function getProviderPlanId(): string
    {
        return $this->providerPlanId;
    }

    public function setProviderPlanId(string $providerPlanId): self
    {
        $this->providerPlanId = $providerPlanId;

        return $this;
    }

    public function getProductId(): int
    {
        return (int) $this->productId;
    }

    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getTemplateId(): ?int
    {
        return $this->templateId === null ? null : (int) $this->templateId;
    }

    public function setTemplateId(?int $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    public function getPlanKey(): string
    {
        return $this->planKey;
    }

    public function setPlanKey(string $planKey): self
    {
        $this->planKey = $planKey;

        return $this;
    }

    public function isActive(): bool
    {
        return (bool) $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getActiveSubscriptionsCount(): int
    {
        return (int) $this->activeSubscriptionsCount;
    }

    public function setActiveSubscriptionsCount(int $activeSubscriptionsCount): self
    {
        $this->activeSubscriptionsCount = $activeSubscriptionsCount;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): self
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function setSyncError(?string $syncError): self
    {
        $this->syncError = $syncError;

        return $this;
    }

    public function getVersionId(): ?string
    {
        return $this->versionId;
    }

    public function setVersionId(?string $versionId): self
    {
        $this->versionId = $versionId;

        return $this;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function setMerchantId(string $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getCategoryIndicator(): string
    {
        return $this->categoryIndicator;
    }

    public function setCategoryIndicator(string $categoryIndicator): self
    {
        $this->categoryIndicator = $categoryIndicator;

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function setRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;

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

    public function getAppliesTo(): ?DateTimeImmutable
    {
        return $this->appliesTo;
    }

    public function setAppliesTo(?DateTimeImmutable $appliesTo): self
    {
        $this->appliesTo = $appliesTo;

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
