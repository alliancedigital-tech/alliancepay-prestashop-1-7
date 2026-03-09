<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity;

use AlliancePay\Model\DateTime\DateTimeImmutableFactory;
use AlliancePay\Model\DateTime\DateTimeImmutableProvider;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(
 *               name="ALLIANCE_CHECKOUT_INTEGRATION_ORDER_MERCHANT_REQUEST_ID",
 *               columns={"merchant_request_id"}
 *          ),
 *          @ORM\Index(
 *              name="ALLIANCE_CHECKOUT_INTEGRATION_ORDER_HPP_ORDER_ID",
 *              columns={"hpp_order_id"}
 *          ),
 *          @ORM\Index(
 *               name="ALLIANCE_CHECKOUT_INTEGRATION_ORDER_MERCHANT_ID",
 *               columns={"merchant_id"}
 *          ),
 *          @ORM\Index(
 *                name="ALLIANCE_CHECKOUT_INTEGRATION_ORDER_ORDER_ID",
 *                columns={"order_id"}
 *           ),
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\AllianceOrderRepository")
 */
class AllianceOrder
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="entity_id", type="integer", nullable=false)
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="order_id", type="integer", nullable=false)
     */
    private $orderId;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_request_id", type="string", length=255, nullable=false)
     */
    private $merchantRequestId;

    /**
     * @var string
     *
     * @ORM\Column(name="hpp_order_id", type="string", length=255, nullable=false)
     */
    private $hppOrderId;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_id", type="string", length=255, nullable=false)
     */
    private $merchantId;

    /**
     * @var int
     *
     * @ORM\Column(name="coin_amount", type="integer", nullable=false)
     */
    private $coinAmount;

    /**
     * @var string
     *
     * @ORM\Column(name="hpp_pay_type", type="string", length=50, nullable=false)
     */
    private $hppPayType;

    /**
     * @var string
     *
     * @ORM\Column(name="order_status", type="string", length=50, nullable=false)
     */
    private $orderStatus;

    /**
     * @var array
     *
     * @ORM\Column(name="payment_methods", type="json", nullable=false)
     */
    private $paymentMethods;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="create_date", type="datetime_immutable", nullable=false)
     */
    private $createDate;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="updated_at", type="datetime_immutable", nullable=false)
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="operation_id", type="string", length=255, nullable=false)
     */
    private $operationId = '';

    /**
     * @var string
     *
     * @ORM\Column(name="ecom_order_id", type="string", length=255, nullable=false)
     */
    private $ecomOrderId;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_callback_returned", type="boolean", nullable=false)
     */
    private $isCallbackReturned = false;

    /**
     * @var array
     *
     * @ORM\Column(name="callback_data", type="json", nullable=false)
     */
    private $callbackData = [];

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="expired_order_date", type="datetime_immutable", nullable=false)
     */
    private $expiredOrderDate;

    public function __construct(
        DateTimeImmutableProvider $dateTimeImmutableProvider
    ) {
        $this->updatedAt = $dateTimeImmutableProvider->defaultDate();
        $this->createDate = $dateTimeImmutableProvider->defaultDate();
        $this->expiredOrderDate = $dateTimeImmutableProvider->defaultDate();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return void
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return string
     */
    public function getMerchantRequestId(): string
    {
        return $this->merchantRequestId;
    }

    /**
     * @param string $merchantRequestId
     * @return void
     */
    public function setMerchantRequestId(string $merchantRequestId): void
    {
        $this->merchantRequestId = $merchantRequestId;
    }

    /**
     * @return string
     */
    public function getHppOrderId(): string
    {
        return $this->hppOrderId;
    }

    /**
     * @param string $hppOrderId
     * @return void
     */
    public function setHppOrderId(string $hppOrderId): void
    {
        $this->hppOrderId = $hppOrderId;
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * @param string $merchantId
     * @return void
     */
    public function setMerchantId(string $merchantId): void
    {
        $this->merchantId = $merchantId;
    }

    /**
     * @return int
     */
    public function getCoinAmount(): int
    {
        return $this->coinAmount;
    }

    /**
     * @param int $coinAmount
     * @return void
     */
    public function setCoinAmount(int $coinAmount): void
    {
        $this->coinAmount = $coinAmount;
    }

    /**
     * @return string
     */
    public function getHppPayType(): string
    {
        return $this->hppPayType;
    }

    /**
     * @param string $hppPayType
     * @return void
     */
    public function setHppPayType(string $hppPayType): void
    {
        $this->hppPayType = $hppPayType;
    }

    /**
     * @return string
     */
    public function getOrderStatus(): string
    {
        return $this->orderStatus;
    }

    /**
     * @param string $orderStatus
     * @return void
     */
    public function setOrderStatus(string $orderStatus): void
    {
        $this->orderStatus = $orderStatus;
    }

    /**
     * @return array
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @param array $paymentMethods
     * @return void
     */
    public function setPaymentMethods(array $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return DateTimeInterface
     */
    public function getCreateDate(): DateTimeInterface
    {
        return $this->createDate;
    }

    /**
     * @param DateTimeInterface $createDate
     * @return void
     */
    public function setCreateDate(DateTimeInterface $createDate): void
    {
        $this->createDate = $createDate;
    }

    /**
     * @return DateTimeInterface
     */
    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTimeInterface $updatedAt
     * @return void
     */
    public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return string
     */
    public function getOperationId(): string
    {
        return $this->operationId;
    }

    /**
     * @param string $operationId
     * @return void
     */
    public function setOperationId(string $operationId): void
    {
        $this->operationId = $operationId;
    }

    /**
     * @return string
     */
    public function getEcomOrderId(): string
    {
        return $this->ecomOrderId;
    }

    /**
     * @param string $ecomOrderId
     * @return void
     */
    public function setEcomOrderId(string $ecomOrderId): void
    {
        $this->ecomOrderId = $ecomOrderId;
    }

    /**
     * @return bool
     */
    public function isCallbackReturned(): bool
    {
        return $this->isCallbackReturned;
    }

    /**
     * @param bool $isCallbackReturned
     * @return void
     */
    public function setIsCallbackReturned(bool $isCallbackReturned): void
    {
        $this->isCallbackReturned = $isCallbackReturned;
    }

    /**
     * @return array
     */
    public function getCallbackData(): array
    {
        return $this->callbackData;
    }

    /**
     * @param array $callbackData
     * @return void
     */
    public function setCallbackData(array $callbackData): void
    {
        $this->callbackData = $callbackData;
    }

    /**
     * @return DateTimeInterface
     */
    public function getExpiredOrderDate(): DateTimeInterface
    {
        return $this->expiredOrderDate;
    }

    /**
     * @param DateTimeInterface $expiredOrderDate
     * @return void
     */
    public function setExpiredOrderDate(DateTimeInterface $expiredOrderDate): void
    {
        $this->expiredOrderDate = $expiredOrderDate;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'orderId' => $this->getOrderId(),
            'merchantRequestId' => $this->getMerchantRequestId(),
            'hppOrderId' => $this->getHppOrderId(),
            'merchantId' => $this->getMerchantId(),
            'coinAmount' => $this->getCoinAmount(),
            'hppPayType' => $this->getHppPayType(),
            'orderStatus' => $this->getOrderStatus(),
            'paymentMethods' => $this->getPaymentMethods(),
            'createDate' => $this->getCreateDate(),
            'updatedAt' => $this->getUpdatedAt(),
            'operationId' => $this->getOperationId(),
            'ecomOrderId' => $this->getEcomOrderId(),
            'isCallbackReturned' => $this->isCallbackReturned(),
            'callbackData' => $this->getCallbackData(),
            'expiredOrderDate' => $this->getExpiredOrderDate()
        ];
    }
}
