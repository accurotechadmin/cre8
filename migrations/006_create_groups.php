<?php
/**
 * CRE8.pw Migration: Create groups table
 * 
 * Creates the groups and group_members tables for bulk access management.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS groups (
    id BINARY(16) PRIMARY KEY,
    owner_id BINARY(16) NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_groups_owner (owner_id),
    FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS group_members (
    group_id BINARY(16) NOT NULL,
    key_id BINARY(16) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, key_id),
    INDEX idx_group_members_key (key_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (key_id) REFERENCES keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS group_members');
        $pdo->exec('DROP TABLE IF EXISTS groups');
    },
];
