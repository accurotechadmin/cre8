<?php
/**
 * CRE8.pw Migration: Add label column to keys table
 * 
 * Adds label column to support optional key labeling.
 * 
 * This migration adds the label column that was missing from the initial schema.
 * Keys can have optional labels for better organization and identification.
 */

declare(strict_types=1);

return [
    'up' => function (PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE keys 
ADD COLUMN label VARCHAR(255) NULL AFTER device_limit;
SQL;
        $pdo->exec($sql);
    },
    'down' => function (PDO $pdo): void {
        $sql = <<<'SQL'
ALTER TABLE keys 
DROP COLUMN label;
SQL;
        $pdo->exec($sql);
    },
];
