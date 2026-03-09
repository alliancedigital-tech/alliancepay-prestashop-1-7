<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity\Factory;

use AlliancePay\Entity\AllianceOrder;
use AlliancePay\Model\DateTime\DateTimeImmutableProvider;

/**
 * Class AllianceOrderFactory.
 */
class AllianceOrderFactory
{
    /**
     * @var DateTimeImmutableProvider
     */
    private $dateTimeProvider;

    public function __construct(
        DateTimeImmutableProvider $dateTimeProvider
    ) {
        $this->dateTimeProvider = $dateTimeProvider;
    }

    public function create(): AllianceOrder
    {
        return new AllianceOrder($this->dateTimeProvider);
    }
}
