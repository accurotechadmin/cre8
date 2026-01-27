<?php
/**
 * CRE8.pw Migration: Create keychains table
 * 
 * Creates the keychains and keychain_members tables.
 * Keychains can be owner-managed (owner_id NOT NULL) or external (owner_id NULL).
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS keychains (
    id BINARY(16) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    owner_id BINARY(16) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_keychains_owner (owner_id),
    FOREIGN KEY (owner_id) REFERENCES owners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS keychain_members (
    keychain_id BINARY(16) NOT NULL,
    key_id BINARY(16) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (keychain_id, key_id),
    INDEX idx_keychain_members_key (key_id),
    FOREIGN KEY (keychain_id) REFERENCES keychains(id) ON DELETE CASCADE,
    FOREIGN KEY (key_id) REFERENCES keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS keychain_members');
        $pdo->exec('DROP TABLE IF EXISTS keychains');
    },
];
