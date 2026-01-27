<?php
/**
 * CRE8.pw Migration: Add lookup_hash to refresh_tokens table
 * 
 * Adds lookup_hash column (SHA-256) for efficient token lookup.
 * Argon2id hashes use random salts, so direct hash lookup never works.
 * Solution: Store SHA-256 hash for lookup, Argon2id hash for verification.
 */

declare(strict_types=1);

return [
    'up' => function (\PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE refresh_tokens 
ADD COLUMN lookup_hash VARCHAR(64) NOT NULL AFTER token_hash,
ADD INDEX idx_refresh_tokens_lookup (lookup_hash);
SQL;
        $pdo->exec($sql);
    },
    'down' => function (\PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE refresh_tokens 
DROP INDEX idx_refresh_tokens_lookup,
DROP COLUMN lookup_hash;
SQL;
        $pdo->exec($sql);
    },
];
