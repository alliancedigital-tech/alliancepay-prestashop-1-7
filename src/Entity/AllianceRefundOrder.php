<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity;

use AlliancePay\Model\DateTime\DateTimeImmutableProvider;
use Doctrine\ORM\Mapping as ORM;
use DateTimeInterface;

/**
 * @ORM\Table(
 *     indexes={
 *          @ORM\Index(
 *               name="ALLIANCE_INTEGRATION_ORDER_REFUND_MERCHANT_REQUEST_ID",
 *               columns={"merchant_request_id"}
 *          ),
 *          @ORM\Index(
 *               name="ALLIANCE_INTEGRATION_ORDER_REFUND_MERCHANT_ID",
 *               columns={"merchant_id"}
 *          )
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\RefundRepository")
 */
class AllianceRefundOrder
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="refund_id", type="integer", nullable=false)
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
     * @ORM\Column(name="type", type="string", nullable=false, length=255)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="rrn", type="string", nullable=false, length=255)
     */
    private $rrn;

    /**
     * @var string
     *
     * @ORM\Column(name="purpose", type="string", nullable=true, length=255)
     */
    private $purpose;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="string", nullable=true, length=255)
     */
    private $comment;

    /**
     * @var int
     *
     * @ORM\Column(name="coin_amount", type="integer", nullable=false)
     */
    private $coinAmount;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_id", type="string", length=255, nullable=false)
     */
    private $merchantId;

    /**
     * @var string
     *
     * @ORM\Column(name="operation_id", type="string", length=255, nullable=false)
     */
    private $operationId;

    /**
     * @var string
     *
     * @ORM\Column(name="ecom_operation_id", type="string", length=255, nullable=false)
     */
    private $ecomOperationId;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_name", type="string", length=255, nullable=true)
     */
    private $merchantName;

    /**
     * @var string
     *
     * @ORM\Column(name="approval_code", type="string", length=255, nullable=true)
     */
    private $approvalCode;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255, nullable=false)
     */
    private $status;

    /**
     * @var int
     *
     * @ORM\Column(name="transaction_type", type="integer", nullable=false)
     */
    private $transactionType;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_request_id", type="string", length=255, nullable=false)
     */
    private $merchantRequestId;

    /**
     * @var string
     *
     * @ORM\Column(name="transaction_currency", type="string", length=255, nullable=false)
     */
    private $transactionCurrency;

    /**
     * @var int
     *
     * @ORM\Column(name="merchant_commission", type="integer", nullable=true)
     */
    private $merchantCommission;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="creation_date_time", type="datetime_immutable", nullable=false)
     */
    private $creationDateTime;

    /**
     * @var DateTimeInterface
     *
     * @ORM\Column(name="modification_date_time", type="datetime_immutable", nullable=false)
     */
    private $modificationDateTime;

    /**
     * @var string
     *
     * @ORM\Column(name="action_code", type="string", length=255, nullable=true)
     */
    private $actionCode;

    /**
     * @var string
     *
     * @ORM\Column(name="response_code", type="string", length=255, nullable=true)
     */
    private $responseCode;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="processing_merchant_id", type="string", length=255, nullable=false)
     */
    private $processingMerchantId;

    /**
     * @var string
     *
     * @ORM\Column(name="processing_terminal_id", type="string", length=255, nullable=false)
     */
    private $processingTerminalId;

    /**
     * @var array
     *
     * @ORM\Column(name="transaction_response_info", type="json", nullable=false)
     */
    private $transactionResponseInfo;

    /**
     * @var string
     *
     * @ORM\Column(name="payment_system", type="string", length=255, nullable=true)
     */
    private $paymentSystem;

    /**
     * @var string
     *
     * @ORM\Column(name="product_type", type="string", length=255, nullable=false)
     */
    private $productType;

    /**
     * @var string
     *
     * @ORM\Column(name="notification_url", type="string", length=255, nullable=false)
     */
    private $notificationUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="payment_service_type", type="string", length=255, nullable=true)
     */
    private $paymentServiceType;

    /**
     * @var string
     *
     * @ORM\Column(name="notification_encryption", type="string", length=255, nullable=false)
     */
    private $notificationEncryption;

    /**
     * @var string
     *
     * @ORM\Column(name="original_operation_id", type="string", length=255, nullable=false)
     */
    private $originalOperationId;

    /**
     * @var int
     *
     * @ORM\Column(name="original_coin_amount", type="integer")
     */
    private $originalCoinAmount;

    /**
     * @var string
     *
     * @ORM\Column(name="original_ecom_operation_id", type="string", length=255, nullable=false)
     */
    private $originalEcomOperationId;

    /**
     * @var string
     *
     * @ORM\Column(name="rrn_original", type="string", length=255, nullable=false)
     */
    private $rrnOriginal;

    public function __construct(
        DateTimeImmutableProvider $dateTimeImmutableProvider
    ) {
        $this->creationDateTime = $dateTimeImmutableProvider->defaultDate();
        $this->modificationDateTime = $dateTimeImmutableProvider->defaultDate();
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
     */
    public function setMerchantId(string $merchantId): void
    {
        $this->merchantId = $merchantId;
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
     */
    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getRrn(): string
    {
        return $this->rrn;
    }

    /**
     * @param string $rrn
     */
    public function setRrn(string $rrn): void
    {
        $this->rrn = $rrn;
    }

    /**
     * @return string
     */
    public function getPurpose(): string
    {
        return $this->purpose;
    }

    /**
     * @param string $purpose
     */
    public function setPurpose(string $purpose): void
    {
        $this->purpose = $purpose;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment(string $comment): void
    {
        $this->comment = $comment;
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
     */
    public function setCoinAmount(int $coinAmount): void
    {
        $this->coinAmount = $coinAmount;
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
     */
    public function setOperationId(string $operationId): void
    {
        $this->operationId = $operationId;
    }

    /**
     * @return string
     */
    public function getEcomOperationId(): string
    {
        return $this->ecomOperationId;
    }

    /**
     * @param string $ecomOperationId
     */
    public function setEcomOperationId(string $ecomOperationId): void
    {
        $this->ecomOperationId = $ecomOperationId;
    }

    /**
     * @return string
     */
    public function getMerchantName(): string
    {
        return $this->merchantName;
    }

    /**
     * @param string $merchantName
     */
    public function setMerchantName(string $merchantName): void
    {
        $this->merchantName = $merchantName;
    }

    /**
     * @return string
     */
    public function getApprovalCode(): string
    {
        return $this->approvalCode;
    }

    /**
     * @param string $approvalCode
     */
    public function setApprovalCode(string $approvalCode): void
    {
        $this->approvalCode = $approvalCode;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getTransactionType(): int
    {
        return $this->transactionType;
    }

    /**
     * @param int $transactionType
     */
    public function setTransactionType(int $transactionType): void
    {
        $this->transactionType = $transactionType;
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
     */
    public function setMerchantRequestId(string $merchantRequestId): void
    {
        $this->merchantRequestId = $merchantRequestId;
    }

    /**
     * @return string
     */
    public function getTransactionCurrency(): string
    {
        return $this->transactionCurrency;
    }

    /**
     * @param string $transactionCurrency
     */
    public function setTransactionCurrency(string $transactionCurrency): void
    {
        $this->transactionCurrency = $transactionCurrency;
    }

    /**
     * @return int
     */
    public function getMerchantCommission(): int
    {
        return $this->merchantCommission;
    }

    /**
     * @param int $merchantCommission
     */
    public function setMerchantCommission(int $merchantCommission): void
    {
        $this->merchantCommission = $merchantCommission;
    }

    /**
     * @return DateTimeInterface
     */
    public function getCreationDateTime(): DateTimeInterface
    {
        return $this->creationDateTime;
    }

    /**
     * @param DateTimeInterface $creationDateTime
     */
    public function setCreationDateTime(DateTimeInterface $creationDateTime): void
    {
        $this->creationDateTime = $creationDateTime;
    }

    /**
     * @return DateTimeInterface
     */
    public function getModificationDateTime(): DateTimeInterface
    {
        return $this->modificationDateTime;
    }

    /**
     * @param DateTimeInterface $modificationDateTime
     */
    public function setModificationDateTime(DateTimeInterface $modificationDateTime): void
    {
        $this->modificationDateTime = $modificationDateTime;
    }

    /**
     * @return string
     */
    public function getActionCode(): string
    {
        return $this->actionCode;
    }

    /**
     * @param string $actionCode
     */
    public function setActionCode(string $actionCode): void
    {
        $this->actionCode = $actionCode;
    }

    /**
     * @return string
     */
    public function getResponseCode(): string
    {
        return $this->responseCode;
    }

    /**
     * @param string $responseCode
     */
    public function setResponseCode(string $responseCode): void
    {
        $this->responseCode = $responseCode;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getProcessingMerchantId(): string
    {
        return $this->processingMerchantId;
    }

    /**
     * @param string $processingMerchantId
     */
    public function setProcessingMerchantId(string $processingMerchantId): void
    {
        $this->processingMerchantId = $processingMerchantId;
    }

    /**
     * @return string
     */
    public function getProcessingTerminalId(): string
    {
        return $this->processingTerminalId;
    }

    /**
     * @param string $processingTerminalId
     * @return void
     */
    public function setProcessingTerminalId(string $processingTerminalId): void
    {
        $this->processingTerminalId = $processingTerminalId;
    }

    /**
     * @return array
     */
    public function getTransactionResponseInfo(): array
    {
        return $this->transactionResponseInfo;
    }

    /**
     * @param array $transactionResponseInfo
     */
    public function setTransactionResponseInfo(array $transactionResponseInfo): void
    {
        $this->transactionResponseInfo = $transactionResponseInfo;
    }

    /**
     * @return string
     */
    public function getPaymentSystem(): string
    {
        return $this->paymentSystem;
    }

    /**
     * @param string $paymentSystem
     */
    public function setPaymentSystem(string $paymentSystem): void
    {
        $this->paymentSystem = $paymentSystem;
    }

    /**
     * @return string
     */
    public function getProductType(): string
    {
        return $this->productType;
    }

    /**
     * @param string $productType
     */
    public function setProductType(string $productType): void
    {
        $this->productType = $productType;
    }

    /**
     * @return string
     */
    public function getNotificationUrl(): string
    {
        return $this->notificationUrl;
    }

    /**
     * @param string $notificationUrl
     */
    public function setNotificationUrl(string $notificationUrl): void
    {
        $this->notificationUrl = $notificationUrl;
    }

    /**
     * @return string
     */
    public function getPaymentServiceType(): string
    {
        return $this->paymentServiceType;
    }

    /**
     * @param string $paymentServiceType
     */
    public function setPaymentServiceType(string $paymentServiceType): void
    {
        $this->paymentServiceType = $paymentServiceType;
    }

    /**
     * @return bool
     */
    public function getNotificationEncryption(): bool
    {
        return !!$this->notificationEncryption;
    }

    /**
     * @param bool $notificationEncryption
     */
    public function setNotificationEncryption(bool $notificationEncryption): void
    {
        $this->notificationEncryption = $notificationEncryption;
    }

    /**
     * @return string
     */
    public function getOriginalOperationId(): string
    {
        return $this->originalOperationId;
    }

    /**
     * @param string $originalOperationId
     */
    public function setOriginalOperationId(string $originalOperationId): void
    {
        $this->originalOperationId = $originalOperationId;
    }

    /**
     * @return int
     */
    public function getOriginalCoinAmount(): int
    {
        return $this->originalCoinAmount;
    }

    /**
     * @param int $originalCoinAmount
     */
    public function setOriginalCoinAmount(int $originalCoinAmount): void
    {
        $this->originalCoinAmount = $originalCoinAmount;
    }

    /**
     * @return string
     */
    public function getOriginalEcomOperationId(): string
    {
        return $this->originalEcomOperationId;
    }

    /**
     * @param string $originalEcomOperationId
     */
    public function setOriginalEcomOperationId(string $originalEcomOperationId): void
    {
        $this->originalEcomOperationId = $originalEcomOperationId;
    }

    /**
     * @return string
     */
    public function getRrnOriginal(): string
    {
        return $this->rrnOriginal;
    }

    /**
     * @param string $rrnOriginal
     */
    public function setRrnOriginal(string $rrnOriginal): void
    {
        $this->rrnOriginal = $rrnOriginal;
    }

    public function toArray(): array
    {
        return [
            'refund_id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'type' => $this->getType(),
            'rrn' => $this->getRrn(),
            'purpose' => $this->getPurpose(),
            'comment' => $this->getComment(),
            'coin_amount' => $this->getCoinAmount(),
            'merchant_id' => $this->getMerchantId(),
            'operation_id' => $this->getOperationId(),
            'ecom_operation_id' => $this->getEcomOperationId(),
            'merchant_name' => $this->getMerchantName(),
            'approval_code' => $this->getApprovalCode(),
            'status' => $this->getStatus(),
            'transaction_type' => $this->getTransactionType(),
            'merchant_request_id' => $this->getMerchantRequestId(),
            'transaction_currency' => $this->getTransactionCurrency(),
            'merchant_commission' => $this->getMerchantCommission(),
            'creation_date_time' => $this->getCreationDateTime(),
            'modification_date_time' => $this->getModificationDateTime(),
            'action_code' => $this->getActionCode(),
            'response_code' => $this->getResponseCode(),
            'description' => $this->getDescription(),
            'processing_merchant_id' => $this->getProcessingMerchantId(),
            'processing_terminal_id' => $this->getProcessingTerminalId(),
            'transaction_response_info' => $this->getTransactionResponseInfo(),
            'payment_system' => $this->getPaymentSystem(),
            'product_type' => $this->getProductType(),
            'notification_url' => $this->getNotificationUrl(),
            'payment_service_type' => $this->getPaymentServiceType(),
            'notification_encryption' => $this->getNotificationEncryption(),
            'original_operation_id' => $this->getOriginalOperationId(),
            'original_coin_amount' => $this->getOriginalCoinAmount(),
            'original_ecom_operation_id' => $this->getOriginalEcomOperationId(),
            'rrn_original' => $this->getRrnOriginal()
        ];
    }
}
