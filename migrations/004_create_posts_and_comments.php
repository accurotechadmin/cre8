<?php
/**
 * CRE8.pw Migration: Create posts and comments tables
 * 
 * Creates the posts and comments tables for content.
 * Posts include lineage tracking (initial_author_key_id).
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS posts (
    id BINARY(16) PRIMARY KEY,
    author_key_id BINARY(16) NOT NULL,
    initial_author_key_id BINARY(16) NOT NULL,
    title VARCHAR(255) NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_posts_author (author_key_id),
    INDEX idx_posts_initial_author (initial_author_key_id),
    INDEX idx_posts_created (created_at DESC),
    FOREIGN KEY (author_key_id) REFERENCES keys(id) ON DELETE RESTRICT,
    FOREIGN KEY (initial_author_key_id) REFERENCES keys(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS comments (
    id BINARY(16) PRIMARY KEY,
    post_id BINARY(16) NOT NULL,
    created_by_key_id BINARY(16) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comments_post (post_id),
    INDEX idx_comments_author (created_by_key_id),
    INDEX idx_comments_created (created_at DESC),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_key_id) REFERENCES keys(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS comments');
        $pdo->exec('DROP TABLE IF EXISTS posts');
    },
];
