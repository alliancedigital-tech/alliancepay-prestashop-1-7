<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Config;

use AlliancePay\Logger\AllianceLogger;
use Exception;
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
    public const HPP_PAY_TYPE = 'PURCHASE';
    public const PAYMENT_ENABLED_CONFIG_NAME = 'ALLIANCE_PAY_ENABLED';
    public const PAYMENT_NAME_CONFIG_NAME = 'ALLIANCE_PAY_NAME';
    public const PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME = 'ALLIANCE_PAY_STATUS_PAGE_TYPE';
    public const PAYMENT_API_URL_CONFIG_NAME = 'ALLIANCE_PAY_API_URL';
    public const PAYMENT_SERVICE_CODE_CONFIG_NAME = 'ALLIANCE_PAY_SERVICE_CODE';
    public const PAYMENT_MERCHANT_ID_CONFIG_NAME = 'ALLIANCE_PAY_MERCHANT_ID';
    public const PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME = 'ALLIANCE_PAY_AUTHORIZATION_KEY';
    public const PAYMENT_DEVICE_ID_CONFIG_NAME = 'ALLIANCE_PAY_DEVICE_ID';
    public const PAYMENT_REFRESH_TOKEN_CONFIG_NAME = 'ALLIANCE_PAY_REFRESH_TOKEN';
    public const PAYMENT_AUTH_TOKEN_CONFIG_NAME = 'ALLIANCE_PAY_AUTH_TOKEN';
    public const PAYMENT_SERVER_PUBLIC_KEY_CONFIG_NAME = 'ALLIANCE_PAY_SERVER_PUBLIC_KEY';
    public const PAYMENT_TOKEN_EXPIRATION_DATE_TIME_CONFIG_NAME = 'ALLIANCE_PAY_TOKEN_EXPIRATION_DATE_TIME';
    public const PAYMENT_TOKEN_EXPIRATION_CONFIG_NAME = 'ALLIANCE_PAY_TOKEN_EXPIRATION';
    public const PAYMENT_SESSION_EXPIRATION_CONFIG_NAME = 'ALLIANCE_PAY_SESSION_EXPIRATION';
    public const PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_SUCCESS_ORDER';
    public const PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_FAIL_ORDER';
    public const PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_SUCCESS_REFUND_ORDER_STATE';
    public const PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME = 'ALLIANCE_PAY_FAIL_REFUND_ORDER_STATE';

    public const PAYMENT_ALL_CONFIG_NAMES = [
        self::PAYMENT_ENABLED_CONFIG_NAME,
        self::PAYMENT_NAME_CONFIG_NAME,
        self::PAYMENT_STATUS_PAGE_TYPE_CONFIG_NAME,
        self::PAYMENT_API_URL_CONFIG_NAME,
        self::PAYMENT_SERVICE_CODE_CONFIG_NAME,
        self::PAYMENT_MERCHANT_ID_CONFIG_NAME,
        self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME,
        self::PAYMENT_DEVICE_ID_CONFIG_NAME,
        self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME,
        self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME,
        self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME,
    ];

    public const SENSITIVE_DATA_FIELD_REFRESH_TOKEN = 'refreshToken';
    public const SENSITIVE_DATA_FIELD_AUTH_TOKEN = 'authToken';
    public const SENSITIVE_DATA_FIELD_DEVICE_ID = 'deviceId';
    public const SENSITIVE_DATA_FIELD_SERVER_PUBLIC = 'serverPublic';
    public const SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME = 'tokenExpirationDateTime';
    public const SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION = 'tokenExpiration';
    public const SENSITIVE_DATA_FIELD_SESSION_EXPIRATION = 'sessionExpiration';
    public const SENSITIVE_DATA_FIELDS = [
        self::SENSITIVE_DATA_FIELD_REFRESH_TOKEN,
        self::SENSITIVE_DATA_FIELD_AUTH_TOKEN,
        self::SENSITIVE_DATA_FIELD_DEVICE_ID,
        self::SENSITIVE_DATA_FIELD_SERVER_PUBLIC,
        self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME,
        self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION,
        self::SENSITIVE_DATA_FIELD_SESSION_EXPIRATION,
    ];
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

    public const SUCCESS_ORDER_STATUS = 'SUCCESS';
    public const FAIL_ORDER_STATUS = 'FAIL';

    public const REFUND_STATUS_SUCCESS = 'SUCCESS';
    public const REFUND_STATUS_PENDING = 'PENDING';
    public const REFUND_STATUS_FAIL = 'FAIL';

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

    public function isEnabled(): bool
    {
        return (bool) $this->config->get(self::PAYMENT_ENABLED_CONFIG_NAME);
    }

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
            self::PAYMENT_API_URL_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_API_URL_CONFIG_NAME),
            self::PAYMENT_SERVICE_CODE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_SERVICE_CODE_CONFIG_NAME),
            self::PAYMENT_MERCHANT_ID_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_MERCHANT_ID_CONFIG_NAME),
            self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME),
            self::PAYMENT_DEVICE_ID_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_DEVICE_ID_CONFIG_NAME),
            self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME),
            self::PAYMENT_AUTH_TOKEN_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_AUTH_TOKEN_CONFIG_NAME),
            self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_SUCCESS_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_FAIL_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_SUCCESS_REFUND_ORDER_STATE_CONFIG_NAME),
            self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME =>
                $this->config->get(self::PAYMENT_FAIL_REFUND_ORDER_STATE_CONFIG_NAME),
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

    public function getApiUrl(): string
    {
        return $this->config->get(self::PAYMENT_API_URL_CONFIG_NAME) ?? '';
    }

    public function getDeviceId(): string
    {
        return $this->config->get(self::PAYMENT_DEVICE_ID_CONFIG_NAME) ?? '';
    }

    public function getRefreshToken(): string
    {
        return $this->config->get(self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME) ?? '';
    }

    public function getServiceCode(): string
    {
        return $this->config->get(self::PAYMENT_SERVICE_CODE_CONFIG_NAME) ?? '';
    }

    public function getAuthorizationKey(): string
    {
        return $this->config->get(self::PAYMENT_AUTHORIZATION_KEY_CONFIG_NAME) ?? '';
    }

    public function getServerPublicKey(): string
    {
        return $this->config->get(self::PAYMENT_SERVER_PUBLIC_KEY_CONFIG_NAME) ?? '';
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
     * @param array $authData
     * @return void
     * @throws Exception
     */
    public function saveAuthentificationData(array $authData): void
    {
        if (!$this->hasAllRequiredFields($authData)) {
            return;
        }

        $serverPublic = json_encode($authData[self::SENSITIVE_DATA_FIELD_SERVER_PUBLIC]);

        try {
            $this->config->set(
                self::PAYMENT_REFRESH_TOKEN_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_REFRESH_TOKEN]
            );
            $this->config->set(
                self::PAYMENT_AUTH_TOKEN_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_AUTH_TOKEN]
            );
            $this->config->set(
                self::PAYMENT_DEVICE_ID_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_DEVICE_ID]
            );
            $this->config->set(
                self::PAYMENT_SERVER_PUBLIC_KEY_CONFIG_NAME,
                $serverPublic
            );
            $this->config->set(
                self::PAYMENT_TOKEN_EXPIRATION_DATE_TIME_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION_DATE_TIME]
            );
            $this->config->set(
                self::PAYMENT_TOKEN_EXPIRATION_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_TOKEN_EXPIRATION]
            );
            $this->config->set(
                self::PAYMENT_SESSION_EXPIRATION_CONFIG_NAME,
                $authData[self::SENSITIVE_DATA_FIELD_SESSION_EXPIRATION]
            );
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * @param array $authData
     * @return bool
     * @throws Exception
     */
    private function hasAllRequiredFields(array $authData): bool
    {
        foreach (self::SENSITIVE_DATA_FIELDS as $key) {
            if (empty($authData[$key])) {
                throw new Exception('Alliance payment configuration key ' . $key . ' is missing.');
            }
        }

        return true;
    }
}
