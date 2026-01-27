<?php
/**
 * CRE8.pw Migration: Create keys table
 * 
 * Creates the keys table for machine principals (Primary, Secondary, Use Keys).
 * Includes lineage fields for provenance tracking.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS keys (
    id BINARY(16) PRIMARY KEY,
    type ENUM('primary', 'secondary', 'use') NOT NULL,
    key_secret_hash VARCHAR(255) NOT NULL,
    permissions_json JSON NOT NULL,
    active BOOLEAN DEFAULT 1 NOT NULL,
    issued_by_key_id BINARY(16) NULL,
    parent_key_id BINARY(16) NULL,
    initial_author_key_id BINARY(16) NOT NULL,
    rotated_from_id BINARY(16) NULL,
    rotated_to_id BINARY(16) NULL,
    retired_at TIMESTAMP NULL,
    use_count_limit INT NULL,
    use_count_current INT DEFAULT 0 NOT NULL,
    device_limit INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_keys_type (type),
    INDEX idx_keys_active (active),
    INDEX idx_keys_lineage (initial_author_key_id),
    INDEX idx_keys_parent (parent_key_id),
    INDEX idx_keys_issued_by (issued_by_key_id),
    FOREIGN KEY (issued_by_key_id) REFERENCES keys(id) ON DELETE RESTRICT,
    FOREIGN KEY (parent_key_id) REFERENCES keys(id) ON DELETE RESTRICT,
    FOREIGN KEY (initial_author_key_id) REFERENCES keys(id) ON DELETE RESTRICT,
    FOREIGN KEY (rotated_from_id) REFERENCES keys(id) ON DELETE SET NULL,
    FOREIGN KEY (rotated_to_id) REFERENCES keys(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS keys');
    },
];
