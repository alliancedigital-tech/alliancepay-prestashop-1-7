<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Config;

/**
 * Constants of the subscription (recurring payments) feature.
 */
class SubscriptionConfig
{
    public const ENDPOINT_PLAN_CREATE = '/ecom/jws/scheduled_payments/plan/create_v1';
    public const ENDPOINT_PLAN_UPDATE = '/ecom/jws/scheduled_payments/plan/update_v1';
    public const ENDPOINT_PLAN_STATUS_UPDATE = '/ecom/jws/scheduled_payments/plan/status_update_v1';
    public const ENDPOINT_PLAN_VERSION_UPDATE = '/ecom/jws/scheduled_payments/plan/version/update_v1';
    public const ENDPOINT_VERSION_DELETE = '/ecom/jws/scheduled_payments/version/delete_v1';
    public const ENDPOINT_SUBSCRIPTION_ORDER_CREATE = '/ecom/jws/scheduled_payments/subscription-orders/create_v1';
    public const ENDPOINT_SUBSCRIPTION_ORDER_CANCEL = '/ecom/jws/scheduled_payments/subscription-orders/cancel_v1';
    public const ENDPOINT_INITIAL_PAYMENT = '/ecom/jws/scheduled_payments/payments/initial_subscription_v1';
    public const ENDPOINT_RECURRING_PAYMENT = '/ecom/jws/scheduled_payments/payments/recurring_subscription_v1';

    /**
     * Content-Type of the scheduled_payments/* group. The source integration used application/jwt for it,
     * the rest of this module uses application/jws: confirm on the sandbox and change here if needed.
     */
    public const CONTENT_TYPE = 'application/jws';

    public const HPP_PAY_TYPE_SUBSCRIPTION = 'SUBSCRIPTION';
    public const DIRECT_TYPE_REDIRECT = 'REDIRECT';
    public const HPP_PAYMENT_METHODS = ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'];

    public const PLAN_STATUS_ACTIVE = 'ACTIVE';
    public const PLAN_STATUS_PAUSED = 'PAUSED';
    public const PLAN_STATUS_CANCELED = 'CANCELED';
    public const PLAN_STATUS_ERROR = 'ERROR';

    public const SYNC_STATUS_SYNCED = 'synced';
    public const SYNC_STATUS_ERROR = 'error';

    public const TEMPLATE_STATUS_ACTIVE = 'active';
    public const TEMPLATE_STATUS_ARCHIVED = 'archived';

    public const SUBSCRIPTION_STATUS_PENDING = 'PENDING';
    public const SUBSCRIPTION_STATUS_ACTIVE = 'ACTIVE';
    public const SUBSCRIPTION_STATUS_CANCELED = 'CANCELED';
    public const SUBSCRIPTION_STATUS_COMPLETED = 'COMPLETED';
    public const SUBSCRIPTION_STATUS_FAILED = 'FAILED';

    public const SUBSCRIPTION_RUNNING_STATUSES = [
        self::SUBSCRIPTION_STATUS_PENDING,
        self::SUBSCRIPTION_STATUS_ACTIVE,
    ];

    public const PAYMENT_STATUS_WAITING = 'WAITING';
    public const PAYMENT_STATUS_PROCESSING = 'PROCESSING';
    public const PAYMENT_STATUS_SUCCESS = 'SUCCESS';
    public const PAYMENT_STATUS_FAIL = 'FAIL';
    public const PAYMENT_STATUS_RETRY = 'RETRY';
    public const PAYMENT_STATUS_SKIPPED_PAUSED = 'SKIPPED_PAUSED';
    public const PAYMENT_STATUS_INVALIDATED = 'INVALIDATED';

    public const PAYMENT_TERMINAL_STATUSES = [
        self::PAYMENT_STATUS_SUCCESS,
        self::PAYMENT_STATUS_FAIL,
        self::PAYMENT_STATUS_SKIPPED_PAUSED,
        self::PAYMENT_STATUS_INVALIDATED,
    ];

    /**
     * Discriminator of the callback payload: absent or a value matching the pattern = first payment,
     * any other value = recurring payment. The exact provider values are unconfirmed (open question to the bank).
     */
    public const RECURRING_TRANSACTION_TYPE_FIELD = 'recurringTransactionType';
    public const RECURRING_TRANSACTION_TYPE_INITIAL_PATTERN = '/INITIAL|FIRST/i';

    /** Minimal notice (days) between a mass repricing and the moment the new rules take effect. */
    public const REPRICING_MIN_LEAD_DAYS = 30;

    public const RULE_REQUIRED_FIELDS = [
        'paymentNumberFrom',
        'paymentNumberTo',
        'intervalValue',
        'intervalUnit',
        'coinAmount',
        'currency',
    ];

    public const INTERVAL_UNITS = ['DAY', 'WEEK', 'MONTH', 'YEAR'];

    public const AUDIT_PLAN_CREATED = 'plan_created';
    public const AUDIT_PLAN_SYNC_FAILED = 'plan_sync_failed';
    public const AUDIT_PLAN_SYNC_RETRIED = 'plan_sync_retried';
    public const AUDIT_PLAN_REPRICED = 'plan_repriced';
    public const AUDIT_PLAN_DEACTIVATED = 'plan_deactivated';
    public const AUDIT_PLAN_ACTIVATED = 'plan_activated';
    public const AUDIT_PLAN_ARCHIVED = 'plan_archived';
    public const AUDIT_TEMPLATE_SAVED = 'template_saved';
    public const AUDIT_TEMPLATE_ARCHIVED = 'template_archived';
    public const AUDIT_SUBSCRIPTION_CREATED = 'subscription_created';
    public const AUDIT_SUBSCRIPTION_CANCELED = 'subscription_canceled';
    public const AUDIT_MANUAL_RECURRING = 'manual_recurring_trigger';
    public const AUDIT_AMOUNT_MISMATCH = 'amount_mismatch';
}
