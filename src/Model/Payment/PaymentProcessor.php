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
use AlliancePay\Service\ConvertData\ConvertDataService;
use AlliancePay\Service\Country\CountryCodeProvider;
use AlliancePay\Service\Gateway\HttpClient;
use AlliancePay\Service\Url\UrlProvider;
use Exception;
use AlliancePay\Model\Payment\Processor\AbstractProcessor;
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

    public function __construct(
        CountryCodeProvider $countryCodeProvider,
        HttpClient $httpClient,
        EntityHydrator $entityHydrator,
        AllianceOrderFactory $allianceOrderFactory,
        ConvertDataService $convertDataService,
        Config $config,
        UrlProvider $urlProvider,
        AllianceLogger $allianceLogger
    ) {
        $this->countryCodeProvider = $countryCodeProvider;
        $this->httpClient = $httpClient;
        $this->entityHydrator = $entityHydrator;
        $this->allianceOrderFactory = $allianceOrderFactory;
        $this->convertDataService = $convertDataService;
        $this->config = $config;
        $this->urlProvider = $urlProvider;
        $this->logger = $allianceLogger;
    }

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

                if (isset($resultRequest['message']) && !!$resultRequest['success']) {
                    throw new Exception($resultRequest['message']);
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
                $em->persist($allianceOrder);
                $em->flush();

                return $resultRequest;
            }
        } catch (Exception $e) {
            $this->logger->error('Create order service error: ' . $e->getMessage());
            return [];
        }

        return [];
    }

    private function preparePlaceOrderData($order, $context): array
    {
        $precision = $context->getComputingPrecision();
        $coinAmount = $this->prepareCoinAmount((float) $order->getTotalPaid(), $precision);
        $customer = $order->getCustomer();
        $confirmationUrl = $this->urlProvider->getConfirmationUrl(
            (int) $order->id_cart,
            (int) $order->id,
            $customer->secure_key
        );

        $data = [
            'coinAmount' => $coinAmount,
            'hppPayType' => Config::HPP_PAY_TYPE,
            'paymentMethods' => Config::PAYMENT_METHODS,
            'language' => $order->getAssociatedLanguage()->getIsoCode(),
            'successUrl' => $confirmationUrl,
            'failUrl' => $confirmationUrl,
            'notificationUrl' => $this->urlProvider->getCallbackUrl(),
            'merchantId' => $this->config->getMerchantId(),
            'statusPageType' => $this->config->getStatusPageType(),
            'merchantRequestId'=> $this->generateMerchantRequestId()
        ];

        return $data;
    }
    private function prepareCustomerData($context, $order, $customer): array
    {
        $data = [];

        if (!$customer->isGuest()) {
            if ($customer->birthday !== '0000-00-00') {
                $data['senderBirthday'] = $this->normalizeDob($customer->birthday) ?? '';
            }
            $data['senderCustomerId'] = $customer->id;
        } else {
            $data['senderCustomerId'] = (string) $customer->id ?? $customer->email;
        }
        $customerAddress = $customer->getSimpleAddress($order->id_address_delivery);

        $countryCode = $this->countryCodeProvider->getCountryNumericCodeByAlpha2($context->country->iso_code);
        $data['senderEmail'] = $customer->email ?? '';
        $data['senderFirstName'] = $customer->firstname ?? '';
        $data['senderLastName'] = $customer->lastname ?? '';
        $data['senderRegion'] = $customerAddress['state'] ?? '';
        $data['senderStreet'] = $customerAddress['address1'] ?? '';
        $data['senderCity'] = $customerAddress['city'] ?? '';
        $data['senderZipCode'] = $customerAddress['postcode'] ?? '';
        $data['senderPhone'] = $customerAddress['phone'] ?? '';

        if (!empty($countryCode)) {
            $data['senderCountry'] = $countryCode;
        }

        return $this->validateAndClenUpData($data);
    }

    /**
     * @param array $data
     * @return array
     */
    private function validateAndClenUpData(array $data): array
    {
        $validatedData = [];

        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $validatedData[$key] = $value;
            }
        }

        return $validatedData;
    }

    public function normalizeDob($dob)
    {
        return date('d.m.Y', strtotime($dob));
    }
}
