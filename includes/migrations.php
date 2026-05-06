<?php
/**
 * Minimal migrations for this project (mysqli).
 * Idempotent-ish: each step checks table/column existence first.
 */
require_once __DIR__ . '/config.php';

function db_table_exists(mysqli $mysqli, string $table): bool
{
    $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function db_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function db_index_exists(mysqli $mysqli, string $table, string $indexName): bool
{
    $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $indexName);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

function db_exec(mysqli $mysqli, string $sql): void
{
    if (!$mysqli->query($sql)) {
        throw new RuntimeException("DB error: " . $mysqli->error . " | SQL: " . $sql);
    }
}

/**
 * @return array<int, array{name:string, apply:callable(mysqli):void}>
 */
function app_get_migrations(): array
{
    return [
        [
            'name' => 'medicator.price column',
            'apply' => function (mysqli $mysqli): void {
                if (!db_column_exists($mysqli, 'medicator', 'price')) {
                    db_exec($mysqli, "ALTER TABLE `medicator` ADD COLUMN `price` DECIMAL(10,2) NULL DEFAULT NULL AFTER `slug`");
                }
                if (!db_column_exists($mysqli, 'medicator', 'manufacturer')) {
                    db_exec($mysqli, "ALTER TABLE `medicator` ADD COLUMN `manufacturer` VARCHAR(255) NULL DEFAULT NULL AFTER `price`");
                    db_exec($mysqli, "UPDATE `medicator` SET `manufacturer` = `m_case` WHERE (`manufacturer` IS NULL OR `manufacturer` = '') AND `m_case` IS NOT NULL AND `m_case` <> ''");
                }
            }
        ],
        [
            'name' => 'user security/profile fields',
            'apply' => function (mysqli $mysqli): void {
                if (!db_column_exists($mysqli, 'user', 'name')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `name` VARCHAR(255) NULL DEFAULT NULL AFTER `email`");
                }
                if (!db_column_exists($mysqli, 'user', 'unp')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `unp` VARCHAR(32) NULL DEFAULT NULL AFTER `name`");
                }
                if (!db_column_exists($mysqli, 'user', 'created_at')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `unp`");
                }
                if (!db_column_exists($mysqli, 'user', 'account_type')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `account_type` VARCHAR(16) NOT NULL DEFAULT 'individual' AFTER `role`");
                }
                if (!db_column_exists($mysqli, 'user', 'company_name')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `company_name` VARCHAR(255) NULL DEFAULT NULL AFTER `account_type`");
                }
                if (!db_column_exists($mysqli, 'user', 'representative_name')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `representative_name` VARCHAR(255) NULL DEFAULT NULL AFTER `company_name`");
                }
                if (!db_column_exists($mysqli, 'user', 'phone')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `phone` VARCHAR(64) NULL DEFAULT NULL AFTER `representative_name`");
                }
                if (!db_column_exists($mysqli, 'user', 'address')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `address` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`");
                }
                if (!db_column_exists($mysqli, 'user', 'role')) {
                    // role exists in dump, but keep safe in case DB differs
                    db_exec($mysqli, "ALTER TABLE `user` ADD COLUMN `role` VARCHAR(32) NOT NULL DEFAULT 'user' AFTER `password`");
                }
                if (!db_index_exists($mysqli, 'user', 'UX_user_login')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD UNIQUE KEY `UX_user_login` (`login`)");
                }
                if (!db_index_exists($mysqli, 'user', 'UX_user_email')) {
                    db_exec($mysqli, "ALTER TABLE `user` ADD UNIQUE KEY `UX_user_email` (`email`)");
                }
            }
        ],
        [
            'name' => 'password resets table',
            'apply' => function (mysqli $mysqli): void {
                if (!db_table_exists($mysqli, 'password_resets')) {
                    db_exec($mysqli, "CREATE TABLE `password_resets` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `user_id` INT NOT NULL,
                        `token_hash` VARCHAR(255) NOT NULL,
                        `expires_at` DATETIME NOT NULL,
                        `used_at` DATETIME NULL DEFAULT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `IX_password_resets_user_id` (`user_id`),
                        CONSTRAINT `FK_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        ],
        [
            'name' => 'orders expansion + order_items',
            'apply' => function (mysqli $mysqli): void {
                if (!db_table_exists($mysqli, 'orders')) {
                    db_exec($mysqli, "CREATE TABLE `orders` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `customer_id` INT NULL DEFAULT NULL,
                        `user_id` INT NULL DEFAULT NULL,
                        `order_date` DATE NOT NULL,
                        `status` VARCHAR(32) NOT NULL DEFAULT 'new',
                        `delivery_type` VARCHAR(32) NULL DEFAULT NULL,
                        `delivery_address` VARCHAR(1024) NULL DEFAULT NULL,
                        `pickup_point` VARCHAR(255) NULL DEFAULT NULL,
                        `payment_type` VARCHAR(64) NULL DEFAULT NULL,
                        `promo_code_id` INT NULL DEFAULT NULL,
                        `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        `discount_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        `total` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        `customer_name` VARCHAR(255) NULL DEFAULT NULL,
                        `customer_phone` VARCHAR(64) NULL DEFAULT NULL,
                        `customer_email` VARCHAR(255) NULL DEFAULT NULL,
                        `comment` TEXT NULL DEFAULT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NULL DEFAULT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                if (!db_column_exists($mysqli, 'orders', 'status')) {
                    db_exec($mysqli, "ALTER TABLE `orders`
                        ADD COLUMN `user_id` INT NULL DEFAULT NULL AFTER `customer_id`,
                        ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'new' AFTER `order_date`,
                        ADD COLUMN `delivery_type` VARCHAR(32) NULL DEFAULT NULL AFTER `status`,
                        ADD COLUMN `delivery_address` VARCHAR(1024) NULL DEFAULT NULL AFTER `delivery_type`,
                        ADD COLUMN `pickup_point` VARCHAR(255) NULL DEFAULT NULL AFTER `delivery_address`,
                        ADD COLUMN `payment_type` VARCHAR(64) NULL DEFAULT NULL AFTER `pickup_point`,
                        ADD COLUMN `promo_code_id` INT NULL DEFAULT NULL AFTER `payment_type`,
                        ADD COLUMN `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `promo_code_id`,
                        ADD COLUMN `discount_total` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `subtotal`,
                        ADD COLUMN `total` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `discount_total`,
                        ADD COLUMN `customer_name` VARCHAR(255) NULL DEFAULT NULL AFTER `total`,
                        ADD COLUMN `customer_phone` VARCHAR(64) NULL DEFAULT NULL AFTER `customer_name`,
                        ADD COLUMN `customer_email` VARCHAR(255) NULL DEFAULT NULL AFTER `customer_phone`,
                        ADD COLUMN `comment` TEXT NULL DEFAULT NULL AFTER `customer_email`,
                        ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `comment`,
                        ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL AFTER `created_at`");
                }
                if (!db_index_exists($mysqli, 'orders', 'IX_orders_user_id')) {
                    db_exec($mysqli, "ALTER TABLE `orders` ADD KEY `IX_orders_user_id` (`user_id`)");
                }
                if (!db_table_exists($mysqli, 'order_items')) {
                    db_exec($mysqli, "CREATE TABLE `order_items` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `order_id` INT NOT NULL,
                        `product_id` INT NULL DEFAULT NULL,
                        `product_name_snapshot` VARCHAR(255) NOT NULL,
                        `qty` INT NOT NULL DEFAULT 1,
                        `unit_price_snapshot` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        `line_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        PRIMARY KEY (`id`),
                        KEY `IX_order_items_order_id` (`order_id`),
                        CONSTRAINT `FK_order_items_orders` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        ],
        [
            'name' => 'promo codes',
            'apply' => function (mysqli $mysqli): void {
                if (!db_table_exists($mysqli, 'promo_codes')) {
                    db_exec($mysqli, "CREATE TABLE `promo_codes` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `code` VARCHAR(64) NOT NULL,
                        `type` VARCHAR(16) NOT NULL DEFAULT 'percent',
                        `value` DECIMAL(10,2) NOT NULL DEFAULT 0,
                        `active` TINYINT(1) NOT NULL DEFAULT 1,
                        `starts_at` DATETIME NULL DEFAULT NULL,
                        `ends_at` DATETIME NULL DEFAULT NULL,
                        `min_total` DECIMAL(10,2) NULL DEFAULT NULL,
                        `max_uses` INT NULL DEFAULT NULL,
                        `uses_count` INT NOT NULL DEFAULT 0,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `UX_promo_codes_code` (`code`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        ],
        [
            'name' => 'reviews table',
            'apply' => function (mysqli $mysqli): void {
                if (!db_table_exists($mysqli, 'reviews')) {
                    db_exec($mysqli, "CREATE TABLE `reviews` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `order_id` INT NOT NULL,
                        `user_id` INT NOT NULL,
                        `product_id` INT NULL DEFAULT NULL,
                        `rating` INT NOT NULL DEFAULT 5,
                        `text` TEXT NOT NULL,
                        `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `moderated_at` DATETIME NULL DEFAULT NULL,
                        `moderated_by` INT NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `IX_reviews_order_id` (`order_id`),
                        KEY `IX_reviews_user_id` (`user_id`),
                        CONSTRAINT `FK_reviews_orders` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
                        CONSTRAINT `FK_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        ],
        [
            'name' => 'email log table',
            'apply' => function (mysqli $mysqli): void {
                if (!db_table_exists($mysqli, 'email_log')) {
                    db_exec($mysqli, "CREATE TABLE `email_log` (
                        `id` INT NOT NULL AUTO_INCREMENT,
                        `to_email` VARCHAR(255) NOT NULL,
                        `subject` VARCHAR(255) NOT NULL,
                        `body` MEDIUMTEXT NOT NULL,
                        `status` VARCHAR(16) NOT NULL DEFAULT 'queued',
                        `error` TEXT NULL DEFAULT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `IX_email_log_to_email` (`to_email`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        ],
    ];
}

function app_apply_migrations(mysqli $mysqli): array
{
    $out = [];
    foreach (app_get_migrations() as $mig) {
        $name = (string)$mig['name'];
        $apply = $mig['apply'];
        $started = microtime(true);
        try {
            $apply($mysqli);
            $out[] = ['name' => $name, 'ok' => true, 'ms' => (int)round((microtime(true) - $started) * 1000)];
        } catch (Throwable $e) {
            $out[] = ['name' => $name, 'ok' => false, 'ms' => (int)round((microtime(true) - $started) * 1000), 'error' => $e->getMessage()];
            break;
        }
    }
    return $out;
}

