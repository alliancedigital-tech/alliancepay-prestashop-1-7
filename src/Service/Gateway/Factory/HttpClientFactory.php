<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Gateway\Factory;

use GuzzleHttp\Client;

/**
 * Class HttpClientFactory.
 */
class HttpClientFactory
{
    public function create(string $baseUri, int $timeout = 10): Client
    {
        return new Client([
            'base_uri' => $baseUri,
            'timeout'  => $timeout,
        ]);
    }
}
