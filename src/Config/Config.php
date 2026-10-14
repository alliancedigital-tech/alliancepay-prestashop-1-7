<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Config;

use AlliancePay\Logger\AllianceLogger;
use Currency;
use Exception;
use InvalidArgumentException;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\Language\LanguageDataProvider;
use PrestaShop\PrestaShop\Core\Localization\Locale;
use PrestaShop\PrestaShop\Adapter\LegacyContext as ContextAdapter;
use PrestaShop\PrestaShop\Adapter\OrderState\OrderStateDataProvider;

/**
 * Class Config.
 */
class Config
{
    public const MODULE_NAME = 'alliancepay';
    public const MODULE_TAB_NAME = 'payments_gateways';
    public const PAYMENT_NAME = 'AlliancePay';
    public const PAYMENT_METHODS = ['CARD', 'APPLE_PAY', 'GOOGLE_PAY'];
    public const HPP_PAY_TYPE_PURCHASE = 'PURCHASE';
    public const HPP_PAY_TYPE_A2A = 'A2A';
    public const HPP_PAY_TYPE_PREAUTH = 'PREAUTH';
    public const OPERATION_TYPE_A2A = 'ACCOUNT_2_ACCOUNT';
    public const OPERATION_TYPE_PURCHASE = 'PURCHASE';
    public const OPERATION_TYPE_PREAUTH = 'PREAUTH';
    public const OPERATION_TYPE_COMPLETION = 'COMPLETION';
    public const DIRECT_TYPE_BANK_LINK = 'BANK_LINK';
    public const PRIORITY_BANK_CODE = 'ALL_BANKS';
    public const PAYMENT_ENABLED_CONFIG_NAME = 'ALLIANCE_PAY_ENABLED';
    public const PAYMENT_NAME_CONFIG_NAME = 'ALLIANCE_PAY_NAME';
    public const PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME = 'ALLIANCE_PAY_STATUS_PAGE_TYPE';
    public const PAYMENT_HPP_PAY_TYPE = 'ALLIANCE_HPP_PAY_TYPE';
    public const PAYMENT_API_URL_CONFIG_NAME = 'ALLIANCE_PAY_API_URL';
    public const PAYMENT_MERCHANT_ID_CONFIG_NAME = 'ALLIANCE_PAY_MERCHANT_ID';
    public const PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME = 'ALLIANCE_PAY_AUTHORIZATION_KEY';
    public const PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME = 'ALLIANCE_PAY_JWS_PRIVATE_KEY';
    public const PAYMENT_JWS_KEY_ID_CONFIG_NAME = 'ALLIANCE_PAY_JWS_KEY_ID';
    public const PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_SUCCESS_ORDER';
    public const PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_FAIL_ORDER';
    public const PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_SUCCESS_REFUND_ORDER_STATE';
    public const PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_FAIL_REFUND_ORDER_STATE';
    public const PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME = 'ALLIANCE_PAY_PREAUTH_EXP_DATE';
    public const PAYMENT_PREAUTH_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_PREAUTH_ORDER_STATE';
    public const PAYMENT_COMPLETION_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_COMPLETION_ORDER_STATE';
    public const SUBSCRIPTIONS_ENABLED_CONFIG_NAME = 'ALLIANCE_PAY_SUBSCRIPTIONS_ENABLED';

    public const PAYMENT_ALL_CONFIG_NAMES = [
        self::PAYMENT_ENABLED_CONFIG_NAME,
        self::PAYMENT_NAME_CONFIG_NAME,
        self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME,
        self::PAYMENT_HPP_PAY_TYPE,
        self::PAYMENT_API_URL_CONFIG_NAME,
        self::PAYMENT_MERCHANT_ID_CONFIG_NAME,
        self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME,
        self::PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME,
        self::PAYMENT_JWS_KEY_ID_CONFIG_NAME,
        self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME,
        self::PAYMENT_PREAUTH_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_COMPLETION_ORDER_STATE_CONFIG_NAME,
        self::SUBSCRIPTIONS_ENABLED_CONFIG_NAME,
    ];

    public const CACHE_KEY_BANK_PUBLIC_KEY_PREFIX = 'ALLIANCE_PAY_BANK_PUBKEY_';

    public const BANK_PUBLIC_KEY_CACHE_TTL = 3600;

    public const PAYMENT_HOOKS = [
        'paymentOptions',
        'paymentReturn'
    ];

    public const DEFAULT_STATUS_PAGE_TYPE = 'STATUS_TIMER_PAGE';
    public const PAYMENT_STATUS_PAGE_TYPES = [
        'STATUS_TIMER_PAGE',
        'STATUS_REDIRECT_MERCHANT_PAGE',
        'STATUS_PAGE'
    ];

    public const HPP_PAY_TYPES = [
        self::HPP_PAY_TYPE_PURCHASE,
        self::HPP_PAY_TYPE_A2A,
        self::HPP_PAY_TYPE_PREAUTH,
    ];

    public const PREAUTH_EXP_DATE_OPTIONS = [
        '2h',
        '4h',
        '6h',
        '12h',
        '1d',
        '2d',
        '7d',
        '14d',
        '28d',
    ];

    public const SUCCESS_ORDER_STATUS = 'SUCCESS';
    public const FAIL_ORDER_STATUS = 'FAIL';

    public const REFUND_STATUS_SUCCESS = 'SUCCESS';
    public const REFUND_STATUS_PENDING = 'PENDING';
    public const REFUND_STATUS_FAIL = 'FAIL';

    public const TRANSACTION_TYPE_A2A = 102;

    public const CURRENCY_CODES = [
        'UAH' => 980,
        'USD' => 840,
        'EUR' => 978,
    ];

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var ContextAdapter
     */
    private $context;

    /**
     * @var OrderStateDataProvider
     */
    private $orderStateDataProvider;

    /**
     * @var Locale
     */
    private $locale;

    /**
     * @var LanguageDataProvider
     */
    private $language;


    /**
     * @var AllianceLogger
     */
    private $logger;

    /**
     * @param Configuration $configuration
     * @param ContextAdapter $context
     * @param OrderStateDataProvider $orderStateDataProvider
     * @param LanguageDataProvider $language
     * @param AllianceLogger $logger
     */
    public function __construct(
        Configuration $configuration,
        ContextAdapter $context,
        OrderStateDataProvider $orderStateDataProvider,
        LanguageDataProvider $language,
        AllianceLogger $logger
    ) {
        $this->config = $configuration;
        $this->context = $context;
        $this->orderStateDataProvider = $orderStateDataProvider;
        $this->language = $language;
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config->get(self::PAYMENT_ENABLED_CONFIG_NAME);
    }

    /**
     * @param bool $value
     * @return void
     * @throws Exception
     */
    public function setEnabled(bool $value): void
    {
        $this->config->set(self::PAYMENT_ENABLED_CONFIG_NAME, $value);
    }

    /**
     * @return array
     */
    public function getAllSettings(): array
    {
        return [
            self::PAYMENT_ENABLED_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_ENABLED_CONFIG_NAME),
            self::PAYMENT_NAME_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_NAME_CONFIG_NAME),
            self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME),
            self::PAYMENT_HPP_PAY_TYPE =>
                $this->config->get(self::PAYMENT_HPP_PAY_TYPE),
            self::PAYMENT_API_URL_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_API_URL_CONFIG_NAME),
            self::PAYMENT_MERCHANT_ID_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_MERCHANT_ID_CONFIG_NAME),
            self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME),
            self::PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME),
            self::PAYMENT_JWS_KEY_ID_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_JWS_KEY_ID_CONFIG_NAME),
            self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME),
            self::PAYMENT_PREAUTH_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_PREAUTH_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_COMPLETION_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_COMPLETION_ORDER_STATE_CONFIG_NAME),
            self::SUBSCRIPTIONS_ENABLED_CONFIG_NAME =>
                $this->config->get(self::SUBSCRIPTIONS_ENABLED_CONFIG_NAME),
        ];
    }

    /**
     * @param $configName
     * @param $configValue
     * @return void
     * @throws Exception
     */
    public function saveConfig($configName,$configValue): void
    {
        $this->config->set($configName, $configValue);
    }

    /**
     * @return array
     */
    public function getOrderStates(): array
    {
        $preparedOrderStates[0] = '';
        $orderStates = $this->orderStateDataProvider->getOrderStates(
            (int) $this->context->getLanguage()->id,
        );

        foreach ($orderStates as $orderState) {
            $preparedOrderStates[$orderState['id_order_state']] = $orderState['name'];
        }

        return $preparedOrderStates;
    }

    /**
     * @return string[]
     */
    public function getStatusPageTypes(): array
    {
        return self::PAYMENT_STATUS_PAGE_TYPES;
    }

    /**
     * @return string
     */
    public function getStatusPageType(): string
    {
        return $this->config->get(self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME) ?? self::DEFAULT_STATUS_PAGE_TYPE;
    }

    /**
     * @return string[]
     */
    public function getPaymentTypes(): array
    {
        return self::HPP_PAY_TYPES;
    }

    /**
     * @return string
     */
    public function getPaymentType(): string
    {
        return $this->config->get(self::PAYMENT_HPP_PAY_TYPE) ?? self::HPP_PAY_TYPE_PURCHASE;
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return $this->config->get(self::PAYMENT_API_URL_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getAuthorizationKey(): string
    {
        return $this->config->get(self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getJwsPrivateKey(): string
    {
        return $this->config->get(self::PAYMENT_JWS_PRIVATE_KEY_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getJwsKeyId(): string
    {
        return $this->config->get(self::PAYMENT_JWS_KEY_ID_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->config->get(self::PAYMENT_MERCHANT_ID_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getSuccessOrderState(): string
    {
        return $this->config->get(self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getFailOrderState(): string
    {
        return $this->config->get(self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getSuccessRefundState(): string
    {
        return $this->config->get(self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getFailRefundState(): string
    {
        return $this->config->get(self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getPreAuthExpDate(): string
    {
        return $this->config->get(self::PAYMENT_PREAUTH_EXP_DATE_CONFIG_NAME) ?? '2h';
    }

    /**
     * @return string[]
     */
    public function getPreAuthExpDateOptions(): array
    {
        return self::PREAUTH_EXP_DATE_OPTIONS;
    }

    /**
     * @return string
     */
    public function getPreAuthOrderState(): string
    {
        return $this->config->get(self::PAYMENT_PREAUTH_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return string
     */
    public function getCompletionOrderState(): string
    {
        return $this->config->get(self::PAYMENT_COMPLETION_ORDER_STATE_CONFIG_NAME) ?? '';
    }

    /**
     * @return bool
     */
    public function isSubscriptionsEnabled(): bool
    {
        return (bool) $this->config->get(self::SUBSCRIPTIONS_ENABLED_CONFIG_NAME);
    }

    /**
     * @param string $kid
     * @return array|null
     */
    public function getCachedBankPublicKey(string $kid): ?array
    {
        $raw = $this->config->get(self::CACHE_KEY_BANK_PUBLIC_KEY_PREFIX . md5($kid));

        if (empty($raw)) {
            return null;
        }

        $cached = json_decode($raw, true);

        if (!is_array($cached) || empty($cached['jwk']) || empty($cached['expires_at'])) {
            return null;
        }

        if ($cached['expires_at'] < time()) {
            return null;
        }

        return $cached['jwk'];
    }

    /**
     * @param string $kid
     * @param array $jwk
     * @param int $ttl
     * @return void
     * @throws Exception
     */
    public function setCachedBankPublicKey(string $kid, array $jwk, int $ttl = self::BANK_PUBLIC_KEY_CACHE_TTL): void
    {
        $payload = json_encode([
            'jwk' => $jwk,
            'expires_at' => time() + $ttl,
        ]);

        $this->config->set(self::CACHE_KEY_BANK_PUBLIC_KEY_PREFIX . md5($kid), $payload);
    }

    /**
     * @param string $code ISO alpha code, e.g. 'UAH', 'USD', 'EUR'
     * @return int
     * @throws InvalidArgumentException when $code is not one of the supported currencies
     */
    public function getCurrencyCode(string $code): int
    {
        if (!array_key_exists($code, self::CURRENCY_CODES)) {
            throw new InvalidArgumentException(
                'Unsupported currency code: "' . $code
                . '". Supported currencies: ' . implode(', ', array_keys(self::CURRENCY_CODES)) . '.'
            );
        }

        return self::CURRENCY_CODES[$code];
    }

    /**
     * @return string
     */
    public function getShopCurrencyIsoCode(): string
    {
        $defaultId = (int) $this->config->get('PS_CURRENCY_DEFAULT');
        return strtoupper(Currency::getIsoCodeById($defaultId));
    }

}
