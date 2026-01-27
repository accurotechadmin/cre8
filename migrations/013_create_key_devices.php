<?php
/**
 * CRE8.pw Migration: Create key_devices table
 * 
 * Creates the key_devices table for device fingerprint tracking.
 * Used for enforcing device limits on Use Keys.
 * 
 * NOTE: This table is optional - device limit enforcement will be skipped
 * if the table doesn't exist (graceful degradation).
 */

declare(strict_types=1);

return [
    'up' => function (\PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS key_devices (
    id BINARY(16) PRIMARY KEY,
    key_id BINARY(16) NOT NULL,
    device_fingerprint VARCHAR(64) NOT NULL,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_key_device_unique (key_id, device_fingerprint),
    INDEX idx_key_devices_key (key_id),
    FOREIGN KEY (key_id) REFERENCES keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (\PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS key_devices');
    },
];
