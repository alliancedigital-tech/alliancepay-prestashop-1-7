<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\Encryption\Factory;

use SimpleJWT\Keys\KeySet;

/**
 * Class KeySetFactory.
 */
class KeySetFactory
{
    public function create(): KeySet
    {
        return new KeySet();
    }
}
