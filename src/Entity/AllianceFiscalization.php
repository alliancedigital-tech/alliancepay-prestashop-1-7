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
 * Fiscalization of one Ecom operation (PURCHASE or REFUND), keep in sync with AlliancePay\Install\FiscalizationSchema.
 *
 * @ORM\Table(
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="ALLIANCE_FISCAL_OPERATION_ID", columns={"operation_id"}),
 *         @ORM\UniqueConstraint(name="ALLIANCE_FISCAL_MERCHANT_REQUEST_ID", columns={"merchant_request_id"})
 *     },
 *     indexes={
 *         @ORM\Index(name="ALLIANCE_FISCAL_ORDER_ID", columns={"id_order"}),
 *         @ORM\Index(name="ALLIANCE_FISCAL_BUCKET_ID", columns={"bucket_id"}),
 *         @ORM\Index(name="ALLIANCE_FISCAL_STATUS", columns={"status"})
 *     }
 * )
 * @ORM\Entity(repositoryClass="AlliancePay\Repository\FiscalizationRepository")
 */
class AllianceFiscalization
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
     * @ORM\Column(name="id_order", type="integer", nullable=false)
     */
    private $idOrder;

    /**
     * @var int|null
     *
     * @ORM\Column(name="id_shop", type="integer", nullable=true)
     */
    private $idShop = null;

    /**
     * @var string
     *
     * @ORM\Column(name="operation_id", type="string", length=64, nullable=false)
     */
    private $operationId;

    /**
     * @var string
     *
     * @ORM\Column(name="operation_type", type="string", length=16, nullable=false)
     */
    private $operationType;

    /**
     * @var string
     *
     * @ORM\Column(name="merchant_request_id", type="string", length=64, nullable=false)
     */
    private $merchantRequestId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="bucket_id", type="string", length=64, nullable=true)
     */
    private $bucketId = null;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=16, nullable=false)
     */
    private $status;

    /**
     * @var string|null
     *
     * @ORM\Column(name="receipt_comment", type="string", length=255, nullable=true)
     */
    private $receiptComment = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="fiscal_number", type="string", length=64, nullable=true)
     */
    private $fiscalNumber = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="receipt_url", type="text", nullable=true)
     */
    private $receiptUrl = null;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="fiscalization_date_time", type="datetime_immutable", nullable=true)
     */
    private $fiscalizationDateTime = null;

    /**
     * @var DateTimeImmutable|null
     *
     * @ORM\Column(name="fiscalization_modification_date_time", type="datetime_immutable", nullable=true)
     */
    private $fiscalizationModificationDateTime = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="error_code", type="string", length=32, nullable=true)
     */
    private $errorCode = null;

    /**
     * @var string|null
     *
     * @ORM\Column(name="error_description", type="text", nullable=true)
     */
    private $errorDescription = null;

    /**
     * @var array
     *
     * @ORM\Column(name="request_payload", type="json", nullable=false)
     */
    private $requestPayload = [];

    /**
     * @var array|null
     *
     * @ORM\Column(name="response_payload", type="json", nullable=true)
     */
    private $responsePayload = null;

    /**
     * @var array|null
     *
     * @ORM\Column(name="callback_payload", type="json", nullable=true)
     */
    private $callbackPayload = null;

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

    public function getIdOrder(): int
    {
        return (int) $this->idOrder;
    }

    public function setIdOrder(int $idOrder): self
    {
        $this->idOrder = $idOrder;

        return $this;
    }

    public function getIdShop(): ?int
    {
        return $this->idShop === null ? null : (int) $this->idShop;
    }

    public function setIdShop(?int $idShop): self
    {
        $this->idShop = $idShop;

        return $this;
    }

    public function getOperationId(): string
    {
        return $this->operationId;
    }

    public function setOperationId(string $operationId): self
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function setOperationType(string $operationType): self
    {
        $this->operationType = $operationType;

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

    public function getBucketId(): ?string
    {
        return $this->bucketId;
    }

    public function setBucketId(?string $bucketId): self
    {
        $this->bucketId = $bucketId;

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

    public function getReceiptComment(): ?string
    {
        return $this->receiptComment;
    }

    public function setReceiptComment(?string $receiptComment): self
    {
        $this->receiptComment = $receiptComment;

        return $this;
    }

    public function getFiscalNumber(): ?string
    {
        return $this->fiscalNumber;
    }

    public function setFiscalNumber(?string $fiscalNumber): self
    {
        $this->fiscalNumber = $fiscalNumber;

        return $this;
    }

    public function getReceiptUrl(): ?string
    {
        return $this->receiptUrl;
    }

    public function setReceiptUrl(?string $receiptUrl): self
    {
        $this->receiptUrl = $receiptUrl;

        return $this;
    }

    public function getFiscalizationDateTime(): ?DateTimeImmutable
    {
        return $this->fiscalizationDateTime;
    }

    public function setFiscalizationDateTime(?DateTimeImmutable $fiscalizationDateTime): self
    {
        $this->fiscalizationDateTime = $fiscalizationDateTime;

        return $this;
    }

    public function getFiscalizationModificationDateTime(): ?DateTimeImmutable
    {
        return $this->fiscalizationModificationDateTime;
    }

    public function setFiscalizationModificationDateTime(?DateTimeImmutable $fiscalizationModificationDateTime): self
    {
        $this->fiscalizationModificationDateTime = $fiscalizationModificationDateTime;

        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;

        return $this;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function setErrorDescription(?string $errorDescription): self
    {
        $this->errorDescription = $errorDescription;

        return $this;
    }

    public function getRequestPayload(): array
    {
        return $this->requestPayload;
    }

    public function setRequestPayload(array $requestPayload): self
    {
        $this->requestPayload = $requestPayload;

        return $this;
    }

    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(?array $responsePayload): self
    {
        $this->responsePayload = $responsePayload;

        return $this;
    }

    public function getCallbackPayload(): ?array
    {
        return $this->callbackPayload;
    }

    public function setCallbackPayload(?array $callbackPayload): self
    {
        $this->callbackPayload = $callbackPayload;

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
