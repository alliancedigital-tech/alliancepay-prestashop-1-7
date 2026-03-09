<?php
/**
 * Copyright © 2025 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Service\ConvertData;

/**
 * Class MappingDataService.
 */
class ConvertDataService
{
    /**
     * @param array $data
     * @return array
     */
    public function camelToSnakeArrayKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (!is_numeric($key)) {
                $snakeKey = strtolower(
                    preg_replace(
                        '/([a-z])([A-Z])/',
                        '$1_$2',
                        $key
                    )
                );
            }

            $result[$snakeKey] = $value;
        }

        return $result;
    }
}
