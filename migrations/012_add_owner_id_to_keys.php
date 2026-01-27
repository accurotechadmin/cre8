<?php
/**
 * CRE8.pw Migration: Add owner_id to keys table
 * 
 * Adds owner_id column to link primary keys to owners.
 * Only primary keys have owner_id; secondary and use keys inherit ownership via initial_author_key_id.
 */

declare(strict_types=1);

return [
    'up' => function (\PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE keys 
ADD COLUMN owner_id BINARY(16) NULL AFTER id,
ADD INDEX idx_keys_owner (owner_id);
SQL;
        $pdo->exec($sql);
    },
    'down' => function (\PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE keys 
DROP INDEX idx_keys_owner,
DROP COLUMN owner_id;
SQL;
        $pdo->exec($sql);
    },
];
