<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Subscription\Callback;

use AlliancePay\Config\Config;
use AlliancePay\Config\SubscriptionConfig;
use AlliancePay\Entity\AllianceScheduledPayment;
use AlliancePay\Entity\AllianceSubscription;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Repository\ScheduledPaymentRepository;
use AlliancePay\Service\Callback\CallbackProcessor;
use AlliancePay\Service\Subscription\Audit\AuditLogger;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Order;
use Throwable;
use Validate;

/**
 * Class SubscriptionCallbackProcessor.
 */
class SubscriptionCallbackProcessor
{
    private const SCHEDULE_DATE_KEYS = ['scheduledDate', 'scheduledDateTime', 'paymentDate', 'plannedDate', 'date'];

    private const STATUS_MAP = [
        'SUCCESS' => SubscriptionConfig::PAYMENT_STATUS_SUCCESS,
        'SUCCEEDED' => SubscriptionConfig::PAYMENT_STATUS_SUCCESS,
        'PAID' => SubscriptionConfig::PAYMENT_STATUS_SUCCESS,
        'FAIL' => SubscriptionConfig::PAYMENT_STATUS_FAIL,
        'FAILED' => SubscriptionConfig::PAYMENT_STATUS_FAIL,
        'DECLINED' => SubscriptionConfig::PAYMENT_STATUS_FAIL,
        'REJECTED' => SubscriptionConfig::PAYMENT_STATUS_FAIL,
        'ERROR' => SubscriptionConfig::PAYMENT_STATUS_FAIL,
        'PENDING' => SubscriptionConfig::PAYMENT_STATUS_PROCESSING,
        'PROCESSING' => SubscriptionConfig::PAYMENT_STATUS_PROCESSING,
        'IN_PROGRESS' => SubscriptionConfig::PAYMENT_STATUS_PROCESSING,
        'CREATED' => SubscriptionConfig::PAYMENT_STATUS_PROCESSING,
        'RETRY' => SubscriptionConfig::PAYMENT_STATUS_RETRY,
        'RETRYING' => SubscriptionConfig::PAYMENT_STATUS_RETRY,
        'SKIPPED_PAUSED' => SubscriptionConfig::PAYMENT_STATUS_SKIPPED_PAUSED,
        'SKIPPED' => SubscriptionConfig::PAYMENT_STATUS_SKIPPED_PAUSED,
        'INVALIDATED' => SubscriptionConfig::PAYMENT_STATUS_INVALIDATED,
        'WAITING' => SubscriptionConfig::PAYMENT_STATUS_WAITING,
        'SCHEDULED' => SubscriptionConfig::PAYMENT_STATUS_WAITING,
    ];

    /**
     * @var CallbackProcessor
     */
    private $orderCallbackProcessor;

    /**
     * @var AuditLogger
     */
    private $auditLogger;

    /**
     * @var AllianceLogger
     */
    private $logger;

    public function __construct(
        CallbackProcessor $orderCallbackProcessor,
        AuditLogger $auditLogger,
        AllianceLogger $logger
    ) {
        $this->orderCallbackProcessor = $orderCallbackProcessor;
        $this->auditLogger = $auditLogger;
        $this->logger = $logger;
    }

    /**
     * @param EntityManagerInterface $em
     * @param int $subscriptionId
     * @param array $data verified callback payload
     * @return void
     * @throws Throwable
     */
    public function process(EntityManagerInterface $em, int $subscriptionId, array $data): void
    {
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            /** @var AllianceSubscription|null $subscription */
            $subscription = $em->find(AllianceSubscription::class, $subscriptionId, LockMode::PESSIMISTIC_WRITE);

            if ($subscription === null) {
                $connection->rollBack();

                return;
            }

            $this->apply($em, $subscription, $data);
            $em->flush();
            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private function apply(EntityManagerInterface $em, AllianceSubscription $subscription, array $data): void
    {
        $isInitial = $this->isInitial($data);
        $operation = $this->extractOperation($data);

        $subscription->setCallbackData($this->mergeKeepingStored($subscription->getCallbackData(), $data))
            ->setUpdatedAt(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        if ($isInitial) {
            $this->orderCallbackProcessor->processCallback($em, $data);
        }

        $this->applyLifecycle($em, $subscription, $data, $isInitial);
        $this->applyPayment($em, $subscription, $data, $operation, $isInitial);
        $this->syncScheduleList($em, $subscription, $data);
    }

    /**
     * @param array $data
     * @return bool absent discriminator or an "initial" value means the first payment
     */
    private function isInitial(array $data): bool
    {
        $type = $data[SubscriptionConfig::RECURRING_TRANSACTION_TYPE_FIELD] ?? null;

        if ($type === null || $type === '') {
            return true;
        }

        return (bool) preg_match(SubscriptionConfig::RECURRING_TRANSACTION_TYPE_INITIAL_PATTERN, (string) $type);
    }

    /**
     * @param array $data
     * @return array|null operation carrying an operationId, null for a "poor" delivery
     */
    private function extractOperation(array $data): ?array
    {
        if (!empty($data['operation']) && is_array($data['operation']) && !empty($data['operation']['operationId'])) {
            return $data['operation'];
        }

        if (!empty($data['operations']) && is_array($data['operations'])) {
            foreach (array_reverse($data['operations']) as $operation) {
                if (is_array($operation) && !empty($operation['operationId'])) {
                    return $operation;
                }
            }
        }

        return null;
    }

    private function applyLifecycle(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        array $data,
        bool $isInitial
    ): void {
        $orderStatus = strtoupper((string) ($data['orderStatus'] ?? ''));
        $subscriptionStatus = strtoupper((string) ($data['subscriptionStatus'] ?? ''));

        if (!in_array($subscription->getStatus(), SubscriptionConfig::SUBSCRIPTION_RUNNING_STATUSES, true)) {
            return;
        }

        if (in_array($subscriptionStatus, ['CANCELED', 'CANCELLED'], true)) {
            $subscription->setStatus(SubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED);
        } elseif (
            $orderStatus === Config::SUCCESS_ORDER_STATUS
            || in_array($subscriptionStatus, ['COMPLETED', 'FINISHED'], true)
        ) {
            $subscription->setStatus(SubscriptionConfig::SUBSCRIPTION_STATUS_COMPLETED);
        } elseif ($isInitial && $orderStatus === Config::FAIL_ORDER_STATUS) {
            $subscription->setStatus(SubscriptionConfig::SUBSCRIPTION_STATUS_FAILED);
        }
    }

    /**
     * The initial callback may carry the whole future schedule: keep a WAITING row per scheduled payment.
     */
    private function syncScheduleList(EntityManagerInterface $em, AllianceSubscription $subscription, array $data): void
    {
        if (empty($data['scheduledPayments']) || !is_array($data['scheduledPayments'])) {
            return;
        }

        $repository = $this->getRepository($em);

        foreach ($data['scheduledPayments'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $scheduledId = $entry['scheduledPaymentId'] ?? ($entry['id'] ?? null);

            if (empty($scheduledId)) {
                continue;
            }

            $row = $repository->findOneBy([
                'subscriptionId' => $subscription->getId(),
                'scheduledPaymentId' => (string) $scheduledId,
            ]);

            if ($row === null) {
                $row = $this->createRow(
                    $em,
                    $subscription,
                    (string) $scheduledId,
                    isset($entry['paymentNumber']) ? (int) $entry['paymentNumber'] : null
                );
            }

            if ($row->getScheduledDate() === null) {
                $row->setScheduledDate($this->parseDate($entry, self::SCHEDULE_DATE_KEYS));
            }

            if ($row->getCoinAmount() === null && isset($entry['coinAmount']) && is_numeric($entry['coinAmount'])) {
                $row->setCoinAmount((int) $entry['coinAmount']);
            }
        }
    }

    private function applyPayment(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        array $data,
        ?array $operation,
        bool $isInitial
    ): void {
        if ($operation !== null && strtoupper((string) ($operation['type'] ?? '')) === 'REFUND') {
            return;
        }

        $scheduledId = $data['scheduledPaymentId'] ?? ($operation['scheduledPaymentId'] ?? null);
        $operationId = $operation !== null ? (string) $operation['operationId'] : null;
        $rawStatus = $data['scheduledPaymentStatus'] ?? ($operation['status'] ?? ($data['orderStatus'] ?? null));
        $status = $this->mapStatus($rawStatus === null ? null : (string) $rawStatus);

        $row = $this->findRow($em, $subscription, $scheduledId, $operationId, $isInitial);

        if ($row === null) {
            if ($status === null && $operation === null && ($rawStatus === null || $rawStatus === '')) {
                return;
            }

            $row = $this->createRow(
                $em,
                $subscription,
                $scheduledId !== null ? (string) $scheduledId : null,
                $isInitial ? 1 : null
            );
        }

        if ($status === null && $rawStatus !== null && $rawStatus !== '') {
            $this->flagForReview($em, $subscription, $row, 'Unknown payment status "' . $rawStatus . '"');
        }

        if ($operation === null) {
            $this->applyStatusOnly($row, $status);

            return;
        }

        $operationStatus = strtoupper((string) ($operation['status'] ?? ''));
        $known = $row->getOperationIds();

        if ($row->getOperationId() === $operationId && (string) $row->getOperationStatus() === $operationStatus) {
            return;
        }

        if ($row->getOperationId() !== $operationId && in_array($operationId, $known, true)) {
            return;
        }

        if ($row->getOperationId() === $operationId
            && in_array($row->getStatus(), SubscriptionConfig::PAYMENT_TERMINAL_STATUSES, true)
            && ($status === null || !in_array($status, SubscriptionConfig::PAYMENT_TERMINAL_STATUSES, true))
        ) {
            return;
        }

        $this->applyOperation($em, $subscription, $row, $operation, $operationId, $operationStatus, $status, $isInitial);
    }

    private function applyOperation(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        AllianceScheduledPayment $row,
        array $operation,
        string $operationId,
        string $operationStatus,
        ?string $status,
        bool $isInitial
    ): void {
        $ids = $row->getOperationIds();
        $ids[] = $operationId;
        $row->setOperationIds(array_values(array_unique($ids)))
            ->setOperationId($operationId)
            ->setOperationStatus($operationStatus !== '' ? $operationStatus : null);

        if (isset($operation['coinAmount']) && is_numeric($operation['coinAmount'])) {
            $row->setCoinAmount((int) $operation['coinAmount']);
        }

        if ($status !== null) {
            $row->setStatus($status);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $row->setUpdatedAt($now);

        if ($status === SubscriptionConfig::PAYMENT_STATUS_FAIL) {
            $row->setRetryCount($row->getRetryCount() + 1)->setExecutedAt($now);
        }

        if ($status === SubscriptionConfig::PAYMENT_STATUS_SUCCESS) {
            $row->setExecutedAt($now);
            $this->checkAmount($em, $subscription, $row, $operation);

            if (!$isInitial) {
                $this->addRecurringOrderPayment($subscription, $operation, $operationId);
            }
        }
    }

    private function applyStatusOnly(AllianceScheduledPayment $row, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        $isRegression = in_array($row->getStatus(), SubscriptionConfig::PAYMENT_TERMINAL_STATUSES, true)
            && !in_array($status, SubscriptionConfig::PAYMENT_TERMINAL_STATUSES, true);

        if (!$isRegression) {
            $row->setStatus($status)->setUpdatedAt(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        }
    }

    private function findRow(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        $scheduledId,
        ?string $operationId,
        bool $isInitial
    ): ?AllianceScheduledPayment {
        $repository = $this->getRepository($em);
        $row = null;

        if (!empty($scheduledId)) {
            $row = $repository->findOneBy([
                'subscriptionId' => $subscription->getId(),
                'scheduledPaymentId' => (string) $scheduledId,
            ]);
        }

        if ($row === null && $operationId !== null) {
            $row = $repository->findOneBy(['subscriptionId' => $subscription->getId(), 'operationId' => $operationId]);
        }

        if ($row === null && $isInitial && empty($scheduledId)) {
            $row = $repository->findOneBy(['subscriptionId' => $subscription->getId(), 'paymentNumber' => 1]);
        }

        return $row;
    }

    private function createRow(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        ?string $scheduledId,
        ?int $paymentNumber
    ): AllianceScheduledPayment {
        if ($paymentNumber === null) {
            $paymentNumber = max($this->getRepository($em)->getMaxPaymentNumber((int) $subscription->getId()), 1) + 1;
        }

        $row = new AllianceScheduledPayment();
        $row->setSubscriptionId((int) $subscription->getId())
            ->setProviderOrderId($subscription->getProviderOrderId())
            ->setScheduledPaymentId($scheduledId)
            ->setPaymentNumber($paymentNumber);
        $em->persist($row);
        $em->flush($row);

        return $row;
    }

    private function checkAmount(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        AllianceScheduledPayment $row,
        array $operation
    ): void {
        $actual = $row->getCoinAmount();
        $number = $row->getPaymentNumber();
        $rate = isset($operation['conversionRate']) ? (float) $operation['conversionRate'] : 1.0;

        if ($actual === null || $number === null || $rate > 1.0) {
            return;
        }

        foreach ($subscription->getRulesSnapshot() as $rule) {
            if ($number >= (int) ($rule['paymentNumberFrom'] ?? 0) && $number <= (int) ($rule['paymentNumberTo'] ?? 0)) {
                if ((int) $rule['coinAmount'] !== $actual) {
                    $this->flagForReview(
                        $em,
                        $subscription,
                        $row,
                        'Expected ' . (int) $rule['coinAmount'] . ', received ' . $actual . ' (payment #' . $number . ')'
                    );
                }

                return;
            }
        }
    }

    private function flagForReview(
        EntityManagerInterface $em,
        AllianceSubscription $subscription,
        AllianceScheduledPayment $row,
        string $note
    ): void {
        $row->setReviewRequired(true)->setReviewNote($note);
        $this->logger->warning('Subscription payment needs review: ' . $note, ['subscription_id' => $subscription->getId()]);
        $this->auditLogger->log(
            $em,
            SubscriptionConfig::AUDIT_AMOUNT_MISMATCH,
            ['note' => $note, 'scheduled_payment_row' => $row->getId()],
            $subscription->getPlanId(),
            null,
            $subscription->getId()
        );
    }

    /**
     * Books a recurring payment on the initial PS order (no new orders are created for recurring payments).
     */
    private function addRecurringOrderPayment(AllianceSubscription $subscription, array $operation, string $operationId): void
    {
        if (!isset($operation['coinAmount']) || !is_numeric($operation['coinAmount'])) {
            $this->logger->warning('Recurring payment without amount, PS payment not added: ' . $operationId);

            return;
        }

        $psOrder = new Order($subscription->getOrderId());

        if (!Validate::isLoadedObject($psOrder)) {
            $this->logger->error('PS order not found for subscription ' . $subscription->getId());

            return;
        }

        foreach ($psOrder->getOrderPaymentCollection() as $payment) {
            if ($payment->transaction_id === $operationId) {
                return;
            }
        }

        $coinAmount = (int) $operation['coinAmount'];
        $rate = isset($operation['conversionRate']) ? (float) $operation['conversionRate'] : 0.0;

        if ($rate > 1.0) {
            $coinAmount = (int) round($coinAmount / $rate);
        }

        $psOrder->addOrderPayment(round($coinAmount / 100, 2), Config::PAYMENT_NAME, $operationId);
    }

    private function mapStatus(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return self::STATUS_MAP[strtoupper($raw)] ?? null;
    }

    private function parseDate(array $entry, array $keys): ?DateTimeImmutable
    {
        foreach ($keys as $key) {
            if (!empty($entry[$key]) && is_string($entry[$key])) {
                try {
                    return new DateTimeImmutable($entry[$key], new DateTimeZone('UTC'));
                } catch (Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }

    private function mergeKeepingStored(array $stored, array $incoming): array
    {
        return array_replace($stored, array_filter($incoming, function ($value) {
            return $value !== null;
        }));
    }

    private function getRepository(EntityManagerInterface $em): ScheduledPaymentRepository
    {
        return $em->getRepository(AllianceScheduledPayment::class);
    }
}
