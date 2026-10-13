-- =============================================================
-- Campus Lost and Found Portal — Schema
-- MySQL 8 compatible  |  utf8mb4_unicode_ci throughout
-- Import: mysql -u root lostfound < sql/lostfound.sql
-- Then seed:  php sql/seed.php
-- =============================================================

CREATE DATABASE IF NOT EXISTS lostfound
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lostfound;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------
-- users
-- ------------------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(150)  NOT NULL UNIQUE,
    password    VARCHAR(255)  NOT NULL,
    phone       VARCHAR(20)   DEFAULT NULL,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_blocked  TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- items
-- ------------------------------------------------------------------
CREATE TABLE items (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT           NOT NULL,
    type             ENUM('lost','found') NOT NULL,
    category         ENUM('id_card','electronics','books','bottle','bag','keys','other') NOT NULL,
    title            VARCHAR(200)  NOT NULL,
    description      TEXT          DEFAULT NULL,
    location         VARCHAR(200)  DEFAULT NULL,
    item_date        DATE          DEFAULT NULL,
    photo            VARCHAR(255)  DEFAULT NULL,
    status           ENUM('open','claimed','returned') NOT NULL DEFAULT 'open',
    verify_question  VARCHAR(255)  DEFAULT NULL,
    verify_answer    VARCHAR(255)  DEFAULT NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type_status (type, status),
    INDEX idx_category    (category),
    INDEX idx_item_date   (item_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- matches
-- ------------------------------------------------------------------
CREATE TABLE matches (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    lost_item_id  INT           NOT NULL,
    found_item_id INT           NOT NULL,
    score         DECIMAL(4,2)  NOT NULL,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id)  REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES items(id) ON DELETE CASCADE,
    UNIQUE KEY uq_match (lost_item_id, found_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- claims
-- ------------------------------------------------------------------
CREATE TABLE claims (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    match_id     INT           NOT NULL,
    claimant_id  INT           NOT NULL,
    answer_given VARCHAR(255)  DEFAULT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by  INT           DEFAULT NULL,
    reviewed_at  TIMESTAMP     NULL DEFAULT NULL,
    admin_note   TEXT          DEFAULT NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id)    REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)   ON DELETE SET NULL,
    UNIQUE KEY uq_pending_claim (claimant_id, match_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
