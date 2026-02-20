CREATE DATABASE IF NOT EXISTS `url_shortener`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `url_shortener`;

CREATE TABLE IF NOT EXISTS `urls` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(20)     NOT NULL UNIQUE COMMENT 'Código corto único',
    `original_url` TEXT            NOT NULL       COMMENT 'URL original',
    `creator_ip`   VARCHAR(45)     NOT NULL DEFAULT '',
    `visit_count`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `max_uses`     INT UNSIGNED    NULL           COMMENT 'NULL = sin límite',
    `expires_at`   DATETIME        NULL           COMMENT 'NULL = sin expiración',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visits` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url_id`     BIGINT UNSIGNED NOT NULL,
    `ip`         VARCHAR(45)     NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255)    NOT NULL DEFAULT '',
    `visited_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_visits_url`
        FOREIGN KEY (`url_id`) REFERENCES `urls`(`id`) ON DELETE CASCADE,
    INDEX `idx_url_id`     (`url_id`),
    INDEX `idx_visited_at` (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limit` (
    `ip`           VARCHAR(45) NOT NULL,
    `requests`     SMALLINT    NOT NULL DEFAULT 1,
    `window_start` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;