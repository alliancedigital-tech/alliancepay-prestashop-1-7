<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Install;

/**
 * CREATE TABLE statements of the fiscalization feature, keep in sync with the Doctrine entities
 * AllianceFiscalization and AllianceProductFiscal.
 */
class FiscalizationSchema
{
    /**
     * @return string[] table name (without prefix) => SQL
     */
    public static function getCreateStatements(string $prefix): array
    {
        return [
            'alliance_fiscalization' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_fiscalization` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`id_order` INT NOT NULL, '
                . '`id_shop` INT DEFAULT NULL, '
                . '`operation_id` VARCHAR(64) NOT NULL, '
                . '`operation_type` VARCHAR(16) NOT NULL, '
                . '`merchant_request_id` VARCHAR(64) NOT NULL, '
                . '`bucket_id` VARCHAR(64) DEFAULT NULL, '
                . '`status` VARCHAR(16) NOT NULL, '
                . '`receipt_comment` VARCHAR(255) DEFAULT NULL, '
                . '`fiscal_number` VARCHAR(64) DEFAULT NULL, '
                . '`receipt_url` LONGTEXT DEFAULT NULL, '
                . '`fiscalization_date_time` DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . '`fiscalization_modification_date_time` DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . '`error_code` VARCHAR(32) DEFAULT NULL, '
                . '`error_description` LONGTEXT DEFAULT NULL, '
                . '`request_payload` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`response_payload` LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', '
                . '`callback_payload` LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', '
                . '`created_at` DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . '`updated_at` DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_FISCAL_OPERATION_ID (`operation_id`), '
                . 'UNIQUE INDEX ALLIANCE_FISCAL_MERCHANT_REQUEST_ID (`merchant_request_id`), '
                . 'INDEX ALLIANCE_FISCAL_ORDER_ID (`id_order`), '
                . 'INDEX ALLIANCE_FISCAL_BUCKET_ID (`bucket_id`), '
                . 'INDEX ALLIANCE_FISCAL_STATUS (`status`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_product_fiscal' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_product_fiscal` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`id_product` INT NOT NULL, '
                . '`id_product_attribute` INT NOT NULL DEFAULT 0, '
                . '`tax_code` INT DEFAULT NULL, '
                . '`classification_code` VARCHAR(32) DEFAULT NULL, '
                . '`excise_labels` LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', '
                . '`measurement_code` VARCHAR(16) DEFAULT NULL, '
                . '`created_at` DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . '`updated_at` DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_PRODUCT_FISCAL_PRODUCT (`id_product`, `id_product_attribute`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
        ];
    }
}
