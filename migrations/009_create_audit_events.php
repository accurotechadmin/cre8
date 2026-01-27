<?php
/**
 * CRE8.pw Migration: Create audit_events table
 * 
 * Creates the audit_events table for audit logging.
 * Tracks all lifecycle actions across the system.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS audit_events (
    id BINARY(16) PRIMARY KEY,
    actor_type ENUM('owner', 'key') NOT NULL,
    actor_id BINARY(16) NOT NULL,
    action VARCHAR(100) NOT NULL,
    subject_type VARCHAR(50) NULL,
    subject_id BINARY(16) NULL,
    metadata_json JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_type, actor_id, created_at),
    INDEX idx_audit_subject (subject_type, subject_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS audit_events');
    },
];
