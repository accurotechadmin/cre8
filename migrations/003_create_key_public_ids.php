<?php
/**
 * CRE8.pw Migration: Create key_public_ids table
 * 
 * Creates the key_public_ids table for ApiKey exchange.
 * Stores apub_... format identifiers separately from binary IDs.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS key_public_ids (
    id BINARY(16) PRIMARY KEY,
    key_id BINARY(16) NOT NULL UNIQUE,
    key_public_id VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key_public_ids_key_id (key_id),
    INDEX idx_key_public_ids_public_id (key_public_id),
    FOREIGN KEY (key_id) REFERENCES keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS key_public_ids');
    },
];
