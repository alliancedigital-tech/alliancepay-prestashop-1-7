<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Entity\Hydrator;

use AlliancePay\Model\DateTime\DateTimeImmutableProvider;
use Exception;

/**
 * Class EntityHydrator.
 */
class EntityHydrator
{
    private const DATETIME_PROPERTIES = [
        'create_date',
        'creation_date_time',
        'modification_date_time',
        'updated_at',
        'expired_order_date'
    ];

    private const SETTER_PREFIX = 'set';

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
     * @throws Exception
     */
    public function hydrate(object $entity, array $data): object
    {
        foreach ($data as $property => $value) {
            $method = $this->checkAndGetMethodName($entity, self::SETTER_PREFIX,$property);
            if (!empty($method)) {
                if (in_array($property, self::DATETIME_PROPERTIES) && !empty($value)) {
                    $value = $this->dateTimeProvider->fromString($value);
                }

                $entity->$method($value);
            }
        }

        return $entity;
    }

    private function checkAndGetMethodName(object $entity, string $prefix, string $property): string
    {
        $method = $prefix . str_replace(
                ' ',
                '',
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $property
                    )
                )
            );

        return method_exists($entity, $method) ? $method : '';
    }
}
