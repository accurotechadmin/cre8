<?php
/**
 * CRE8.pw Migration: Create refresh_tokens table
 * 
 * Creates the refresh_tokens table for JWT refresh token rotation.
 * Supports both Owner and Key principals.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BINARY(16) PRIMARY KEY,
    subject_type ENUM('owner', 'key') NOT NULL,
    subject_id BINARY(16) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL,
    rotated_at TIMESTAMP NULL,
    replaced_by_id BINARY(16) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    INDEX idx_refresh_token_hash (token_hash),
    INDEX idx_refresh_subject (subject_type, subject_id),
    INDEX idx_refresh_expires (expires_at),
    FOREIGN KEY (replaced_by_id) REFERENCES refresh_tokens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS refresh_tokens');
    },
];
