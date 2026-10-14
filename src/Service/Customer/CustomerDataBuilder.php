<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Customer;

use AlliancePay\Model\Validator\CustomerDataValidator;
use AlliancePay\Service\Country\CountryCodeProvider;
use PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException;
use Tools;

/**
 * Class CustomerDataBuilder.
 */
class CustomerDataBuilder
{
    /**
     * @var CountryCodeProvider
     */
    private $countryCodeProvider;

    /**
     * @var CustomerDataValidator
     */
    private $validateCustomerData;

    public function __construct(CountryCodeProvider $countryCodeProvider, CustomerDataValidator $validateCustomerData)
    {
        $this->countryCodeProvider = $countryCodeProvider;
        $this->validateCustomerData = $validateCustomerData;
    }

    /**
     * @param $context
     * @param $order
     * @param $customer
     * @return array
     * @throws LocalizationException
     */
    public function build($context, $order, $customer): array
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
