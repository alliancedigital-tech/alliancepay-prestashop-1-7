<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Model\Payment;

use AlliancePay\Entity\Factory\AllianceOrderFactory;
use AlliancePay\Entity\Hydrator\EntityHydrator;
use AlliancePay\Logger\AllianceLogger;
use AlliancePay\Config\Config;
use AlliancePay\Model\Validator\CustomerDataValidator;
use AlliancePay\Service\ConvertData\ConvertDataService;
use AlliancePay\Service\Country\CountryCodeProvider;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\Order\UpdateOrderStatus;
use AlliancePay\Service\Url\UrlProvider;
use DateTime;
use DateTimeZone;
use Exception;
use AlliancePay\Model\Payment\Processor\AbstractProcessor;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use Tools;

/**
 * Class PaymentProcessor.
 */
class PaymentProcessor extends AbstractProcessor
{
    private $countryCodeProvider;

    private $httpClient;

    /**
     * @var AllianceOrderFactory
     */
    private $allianceOrderFactory;

    /**
     * @var EntityHydrator
     */
    private $entityHydrator;

    /**
     * @var ConvertDataService
     */
    private $convertDataService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AllianceLogger
     */
    private $logger;

    /**
     * @var UrlProvider
     */
    private $urlProvider;

    /**
     * @var CustomerDataValidator
     */
    private $validateCustomerData;

    /**
     * @var UpdateOrderStatus
     */
    private $updateOrderStatus;

    public function __construct(
        CountryCodeProvider $countryCodeProvider,
        HttpClient $httpClient,
        EntityHydrator $entityHydrator,
        AllianceOrderFactory $allianceOrderFactory,
        ConvertDataService $convertDataService,
        Config $config,
        UrlProvider $urlProvider,
        AllianceLogger $allianceLogger,
        CustomerDataValidator $validateCustomerData,
        UpdateOrderStatus $updateOrderStatus
    ) {
        $this->countryCodeProvider = $countryCodeProvider;
        $this->httpClient = $httpClient;
        $this->entityHydrator = $entityHydrator;
        $this->allianceOrderFactory = $allianceOrderFactory;
        $this->convertDataService = $convertDataService;
        $this->config = $config;
        $this->urlProvider = $urlProvider;
        $this->logger = $allianceLogger;
        $this->validateCustomerData = $validateCustomerData;
        $this->updateOrderStatus = $updateOrderStatus;
    }

    /**
     * @param $context
     * @param $cart
     * @param $em
     * @return array
     */
    public function processPayment($context, $cart, $em)
    {

        $order = \Order::getByCartId($cart->id);

        try {
            $hppOrderData = $this->preparePlaceOrderData($order, $context);
            $hppOrderData['customerData'] = $this->prepareCustomerData(
                $context,
                $order,
                $order->getCustomer()
            );

            if ($order->id && !empty($hppOrderData)) {
                $resultRequest = $this->httpClient->createOrder($hppOrderData);

                if (isset($resultRequest['msgType'])
                    && ($resultRequest['msgType'] === 'ERROR' || $resultRequest['msgType'] === 'VALIDATION_ERROR')
                ) {
                    $this->logger->error('Create order service error: ', $resultRequest);
                    throw new Exception('Create order service error.');
                }

                $preparedData = $this->convertDataService->camelToSnakeArrayKeys(
                    $resultRequest
                );
                $allianceOrder = $this->allianceOrderFactory->create();
                $this->entityHydrator->hydrate(
                    $allianceOrder,
                    $preparedData
                );

                $allianceOrder->setOrderId($order->id);
                $allianceOrder->setOriginalAuthorizedAmount($hppOrderData['coinAmount']);
                $em->persist($allianceOrder);
                $em->flush();

                if ($this->config->getPaymentType() === Config::HPP_PAY_TYPE_PREAUTH) {
                    $preAuthStateId = (int) $this->config->getPreAuthOrderState();
                    if ($preAuthStateId) {
                        $this->updateOrderStatus->updateOrderStatus((int) $order->id, $preAuthStateId);
                    }
                }

                return $resultRequest;
            }
        } catch (Exception $e) {
            $this->logger->error('Create order service error: ' . $e->getMessage());
            return [];
        }

        return [];
    }

    /**
     * @param $order
     * @param $context
     * @return array
     * @throws Exception
     */
    private function preparePlaceOrderData($order, $context): array
    {
        $precision = $context->getComputingPrecision();
        $coinAmount = $this->prepareCoinAmount((float) $order->total_paid_tax_incl, $precision);
        $customer = $order->getCustomer();
        $confirmationUrl = $this->urlProvider->getConfirmationUrl(
            (int) $order->id_cart,
            (int) $order->id,
            $customer->secure_key
        );

        $data = [
            'coinAmount' => $coinAmount,
            'hppPayType' => $this->config->getPaymentType(),
            'paymentMethods' => Config::PAYMENT_METHODS,
            'language' => $order->getAssociatedLanguage()->getIsoCode(),
            'successUrl' => $confirmationUrl,
            'failUrl' => $confirmationUrl,
            'notificationUrl' => $this->urlProvider->getCallbackUrl(),
            'merchantId' => $this->config->getMerchantId(),
            'statusPageType' => $this->config->getStatusPageType(),
            'merchantRequestId'=> $this->generateMerchantRequestId()
        ];

        if ($data['hppPayType'] === Config::HPP_PAY_TYPE_A2A) {
            $data['directType'] = Config::DIRECT_TYPE_BANK_LINK;
            $data['priorityBankCode'] = Config::PRIORITY_BANK_CODE;
            $data['merchantComment'] = 'Payment for order #' . ($order->id ?? '');
        }

        if ($data['hppPayType'] === Config::HPP_PAY_TYPE_PREAUTH) {
            $data['preAuthExpDate'] = $this->resolvePreAuthExpDate(
                $this->config->getPreAuthExpDate()
            );
        }

        return $data;
    }

    /**
     * @param string $option
     * @return string
     * @throws Exception
     */
    private function resolvePreAuthExpDate(string $option): string
    {
        $value = (int) rtrim($option, 'hd');
        $unit = substr($option, -1);

        $date = new DateTime('now', new DateTimeZone('UTC'));

        if ($unit === 'h') {
            $date->modify('+' . $value . ' hours');
        } else {
            $date->modify('+' . $value . ' days');
        }

        $date->modify('+30 seconds');

        return preg_replace('/(\.\d{2})\d/', '$1', $date->format('Y-m-d H:i:s.vP'));
    }

    /**
     * @param $context
     * @param $order
     * @param $customer
     * @return array
     * @throws LocalizationException
     */
    private function prepareCustomerData($context, $order, $customer): array
    {
        $data = [];

        if (!$customer->isGuest()) {
            if ($customer->birthday !== '0000-00-00') {
                $data['senderBirthday'] = $customer->birthday ?? '';
            }
            $data['senderCustomerId'] = $customer->id;
        } else {
            $data['senderCustomerId'] = (string) $customer->id ?? $customer->email;
        }
        $customerAddress = $customer->getSimpleAddress($order->id_address_delivery);
        if ($customer->id_gender == '1') {
            $customerGender = 'Male';
        } else if ($customer->id_gender == '2') {
            $customerGender = 'Female';
        } else {
            $customerGender = 'Other';
        }

        $countryCode = $this->countryCodeProvider->getCountryNumericCodeByAlpha2($context->country->iso_code);
        $data['senderEmail'] = $customer->email ?? '';
        $data['senderFirstName'] = $customer->firstname ?? '';
        $data['senderLastName'] = $customer->lastname ?? '';
        $data['senderRegion'] = $customerAddress['state'] ?? '';
        $data['senderStreet'] = $customerAddress['address1'] ?? '';
        $data['senderCity'] = $customerAddress['city'] ?? '';
        $data['senderZipCode'] = $customerAddress['postcode'] ?? '';
        $data['senderPhone'] = $customerAddress['phone'] ?? $customerAddress['phone_mobile'] ?? '';
        $data['senderAdditionalAddress'] = $customerAddress['address2'] ?? '';
        $data['senderIp'] = Tools::getRemoteAddr();
        $data['senderGender'] = $customerGender;

        if (!empty($countryCode)) {
            $data['senderCountry'] = $countryCode;
        }

        return $this->validateCustomerData->validate($data);
    }
}
