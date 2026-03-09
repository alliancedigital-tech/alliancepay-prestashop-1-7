<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity\Factory;

use AlliancePay\Entity\AllianceRefundOrder;
use AlliancePay\Model\DateTime\DateTimeImmutableProvider;

/**
 * Class AllianceRefundFactory.
 */
class AllianceRefundFactory
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

    /**
     * @return AllianceRefundOrder
     */
    public function create(): AllianceRefundOrder
    {
        return new AllianceRefundOrder($this->dateTimeProvider);
    }
}
