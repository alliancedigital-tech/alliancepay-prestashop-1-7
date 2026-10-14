<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace AlliancePay\Install;

/**
 * CREATE TABLE statements of the subscription module.
 * Generated together with the Doctrine entities, keep both in sync.
 */
class SubscriptionSchema
{
    /**
     * @return string[] table name (without prefix) => SQL
     */
    public static function getCreateStatements(string $prefix): array
    {
        return [
            'alliance_plan_template' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_plan_template` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`code` VARCHAR(100) NOT NULL, '
                . '`name` VARCHAR(255) NOT NULL, '
                . '`category_indicator` VARCHAR(100) NOT NULL, '
                . '`rules_template` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`status` VARCHAR(20) NOT NULL DEFAULT \'active\', '
                . '`created_at` DATETIME NOT NULL, '
                . '`updated_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_PLAN_TEMPLATE_CODE (`code`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_subscription_plan' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_subscription_plan` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`provider_plan_id` VARCHAR(255) NOT NULL, '
                . '`product_id` INT NOT NULL, '
                . '`template_id` INT DEFAULT NULL, '
                . '`plan_key` VARCHAR(64) NOT NULL, '
                . '`is_active` TINYINT(1) NOT NULL DEFAULT 1, '
                . '`active_subscriptions_count` INT NOT NULL DEFAULT 0, '
                . '`sync_status` VARCHAR(20) NOT NULL DEFAULT \'synced\', '
                . '`sync_error` LONGTEXT DEFAULT NULL, '
                . '`version_id` VARCHAR(255) DEFAULT NULL, '
                . '`merchant_id` VARCHAR(255) NOT NULL, '
                . '`name` VARCHAR(255) NOT NULL, '
                . '`description` LONGTEXT DEFAULT NULL, '
                . '`status` VARCHAR(20) NOT NULL DEFAULT \'ACTIVE\', '
                . '`category_indicator` VARCHAR(100) NOT NULL, '
                . '`rules` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`currency_code` VARCHAR(10) NOT NULL, '
                . '`applies_from` DATETIME DEFAULT NULL, '
                . '`applies_to` DATETIME DEFAULT NULL, '
                . '`created_at` DATETIME NOT NULL, '
                . '`updated_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_SUB_PLAN_PROVIDER_PLAN_ID (`provider_plan_id`), '
                . 'UNIQUE INDEX ALLIANCE_SUB_PLAN_PLAN_KEY (`plan_key`), '
                . 'INDEX ALLIANCE_SUB_PLAN_PRODUCT_ID (`product_id`), '
                . 'INDEX ALLIANCE_SUB_PLAN_STATUS (`status`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_subscription' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_subscription` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`order_id` INT NOT NULL, '
                . '`customer_id` INT NOT NULL, '
                . '`plan_id` INT NOT NULL, '
                . '`rules_snapshot` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`provider_order_id` VARCHAR(255) NOT NULL, '
                . '`merchant_request_id` VARCHAR(255) NOT NULL, '
                . '`status` VARCHAR(20) NOT NULL DEFAULT \'PENDING\', '
                . '`callback_data` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`created_at` DATETIME NOT NULL, '
                . '`updated_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_SUB_PROVIDER_ORDER_ID (`provider_order_id`), '
                . 'INDEX ALLIANCE_SUB_ORDER_ID (`order_id`), '
                . 'INDEX ALLIANCE_SUB_CUSTOMER_ID (`customer_id`), '
                . 'INDEX ALLIANCE_SUB_PLAN_ID (`plan_id`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_scheduled_payment' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_scheduled_payment` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`subscription_id` INT NOT NULL, '
                . '`provider_order_id` VARCHAR(255) NOT NULL, '
                . '`scheduled_payment_id` VARCHAR(255) DEFAULT NULL, '
                . '`payment_number` INT DEFAULT NULL, '
                . '`status` VARCHAR(20) NOT NULL DEFAULT \'WAITING\', '
                . '`scheduled_date` DATETIME DEFAULT NULL, '
                . '`executed_at` DATETIME DEFAULT NULL, '
                . '`operation_id` VARCHAR(255) DEFAULT NULL, '
                . '`operation_status` VARCHAR(50) DEFAULT NULL, '
                . '`operation_ids` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`coin_amount` INT DEFAULT NULL, '
                . '`retry_count` INT NOT NULL DEFAULT 0, '
                . '`needs_review` TINYINT(1) NOT NULL DEFAULT 0, '
                . '`review_note` LONGTEXT DEFAULT NULL, '
                . '`created_at` DATETIME NOT NULL, '
                . '`updated_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_SCHED_PAY_SUB_SCHED_ID (`subscription_id`, `scheduled_payment_id`), '
                . 'INDEX ALLIANCE_SCHED_PAY_SUBSCRIPTION_ID (`subscription_id`), '
                . 'INDEX ALLIANCE_SCHED_PAY_OPERATION_ID (`operation_id`), '
                . 'INDEX ALLIANCE_SCHED_PAY_PROVIDER_ORDER_ID (`provider_order_id`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_plan_version_history' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_plan_version_history` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`plan_id` INT NOT NULL, '
                . '`old_version_id` VARCHAR(255) DEFAULT NULL, '
                . '`new_version_id` VARCHAR(255) DEFAULT NULL, '
                . '`old_rules` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`new_rules` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`applies_from` DATETIME DEFAULT NULL, '
                . '`reason` LONGTEXT DEFAULT NULL, '
                . '`created_by` INT DEFAULT NULL, '
                . '`created_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'INDEX ALLIANCE_PLAN_VERSION_PLAN_ID (`plan_id`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_subscription_audit_log' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_subscription_audit_log` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`action` VARCHAR(50) NOT NULL, '
                . '`plan_id` INT DEFAULT NULL, '
                . '`product_id` INT DEFAULT NULL, '
                . '`subscription_id` INT DEFAULT NULL, '
                . '`user_id` INT DEFAULT NULL, '
                . '`details` LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', '
                . '`created_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'INDEX ALLIANCE_SUB_AUDIT_PLAN_ID (`plan_id`), '
                . 'INDEX ALLIANCE_SUB_AUDIT_ACTION (`action`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
            'alliance_cart_subscription' => 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'alliance_cart_subscription` ('
                . '`id` INT AUTO_INCREMENT NOT NULL, '
                . '`cart_id` INT NOT NULL, '
                . '`product_id` INT NOT NULL, '
                . '`product_attribute_id` INT NOT NULL DEFAULT 0, '
                . '`plan_id` INT NOT NULL, '
                . '`created_at` DATETIME NOT NULL, '
                . 'PRIMARY KEY (`id`), '
                . 'UNIQUE INDEX ALLIANCE_CART_SUB_LINE (`cart_id`, `product_id`, `product_attribute_id`)'
                . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB',
        ];
    }
}
