<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Country;

use League\ISO3166\ISO3166;

/**
 * Class CountryCodeProvider.
 */
class CountryCodeProvider
{
    private $countryCode;

    public function __construct(
        ISO3166 $countryCode
    ) {
        $this->countryCode = $countryCode;
    }

    /**
     * @param string $alpha2
     * @return string
     */
    public function getCountryNumericCodeByAlpha2(string $alpha2): string
    {
        $countryData = $this->countryCode->alpha2($alpha2);

        return $countryData['numeric'] ?? '';
    }
}
