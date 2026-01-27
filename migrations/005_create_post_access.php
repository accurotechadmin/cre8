<?php
/**
 * CRE8.pw Migration: Create post_access table
 * 
 * Creates the post_access table for access grants.
 * Stores bitmask permissions (VIEW, COMMENT, MANAGE_ACCESS).
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS post_access (
    id BINARY(16) PRIMARY KEY,
    post_id BINARY(16) NOT NULL,
    target_type ENUM('key', 'group') NOT NULL,
    target_id BINARY(16) NOT NULL,
    permission_mask INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_post_access_unique (post_id, target_type, target_id),
    INDEX idx_post_access_post (post_id),
    INDEX idx_post_access_target (target_type, target_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS post_access');
    },
];
