CREATE DATABASE IF NOT EXISTS `deshappriya_motors` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `deshappriya_motors`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `role` ENUM('administrator', 'cashier') NOT NULL DEFAULT 'cashier',
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`name`, `email`, `role`, `password`)
VALUES
('Garage Administrator', 'admin@deshappriyamotors.test', 'administrator', '$2y$10$KKscRXRVulvlZBPWhNV/QukjXYSIk8VdwHTpN9Nx35TgA2pjmJlYG'),
('Garage Cashier', 'cashier@deshappriyamotors.test', 'cashier', '$2y$10$jdWdnfuqI75ylEBcenK9LOQGnbzV9x/KLwr7UwjJaUxezCrBX9IHq')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `role` = VALUES(`role`);
