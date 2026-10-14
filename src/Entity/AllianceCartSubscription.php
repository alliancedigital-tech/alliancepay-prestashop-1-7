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
 *         @ORM\UniqueConstraint(name="ALLIANCE_CART_SUB_LINE", columns={"cart_id", "product_id", "product_attribute_id"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\CartSubscriptionRepository")
 */
class AllianceCartSubscription
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
     * @ORM\Column(name="cart_id", type="integer", nullable=false)
     */
    private $cartId;

    /**
     * @var int
     *
     * @ORM\Column(name="product_id", type="integer", nullable=false)
     */
    private $productId;

    /**
     * @var int
     *
     * @ORM\Column(name="product_attribute_id", type="integer", nullable=false)
     */
    private $productAttributeId = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="plan_id", type="integer", nullable=false)
     */
    private $planId;

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

    public function getCartId(): int
    {
        return (int) $this->cartId;
    }

    public function setCartId(int $cartId): self
    {
        $this->cartId = $cartId;

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

    public function getProductAttributeId(): int
    {
        return (int) $this->productAttributeId;
    }

    public function setProductAttributeId(int $productAttributeId): self
    {
        $this->productAttributeId = $productAttributeId;

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
