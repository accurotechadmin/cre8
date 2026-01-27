<?php
/**
 * CRE8.pw Migration: Create owners table
 * 
 * Creates the owners table for human principals.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS owners (
    id BINARY(16) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owners_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS owners');
    },
];
