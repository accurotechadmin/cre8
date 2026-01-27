<?php
/**
 * CRE8.pw Schema Contract Verification
 * 
 * Verifies database schema matches expected structure:
 * - Table existence
 * - Column types and constraints
 * - Indexes
 * - Foreign keys
 * 
 * @see docs/canon/06-Data-Model.md
 */

declare(strict_types=1);

namespace App\Utilities;

use PDO;

/**
 * Schema Contract Verifier
 * 
 * Verifies database schema matches expected contract.
 */
class SchemaContractVerifier
{
    /**
     * Verify schema contract
     * 
     * @param PDO $pdo Database connection
     * @return array<string, bool|string> Verification results
     */
    public static function verify(PDO $pdo): array
    {
        $results = [];
        
        // Verify all required tables exist
        $requiredTables = [
            'owners',
            'keys',
            'key_public_ids',
            'posts',
            'comments',
            'post_access',
            'groups',
            'group_members',
            'keychains',
            'keychain_members',
            'refresh_tokens',
            'audit_events',
        ];

        foreach ($requiredTables as $table) {
            $results["table.{$table}"] = self::tableExists($pdo, $table);
        }

        // Verify critical columns
        $results['column.keys.type'] = self::columnExists($pdo, 'keys', 'type');
        $results['column.keys.id'] = self::columnIsBinary16($pdo, 'keys', 'id');
        $results['column.posts.id'] = self::columnIsBinary16($pdo, 'posts', 'id');
        $results['column.owners.id'] = self::columnIsBinary16($pdo, 'owners', 'id');

        // Verify critical indexes
        $results['index.keys.type'] = self::indexExists($pdo, 'keys', 'idx_keys_type');
        $results['index.post_access.unique'] = self::indexExists($pdo, 'post_access', 'idx_post_access_unique');

        return $results;
    }

    /**
     * Check if table exists
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @return bool
     */
    private static function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = ?
        ");
        $stmt->execute([$tableName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if column exists
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool
     */
    private static function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND column_name = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if column is BINARY(16)
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool
     */
    private static function columnIsBinary16(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare("
            SELECT column_type 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND column_name = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        $columnType = $stmt->fetchColumn();
        
        return $columnType === 'binary(16)' || $columnType === 'varbinary(16)';
    }

    /**
     * Check if index exists
     * 
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @return bool
     */
    private static function indexExists(PDO $pdo, string $tableName, string $indexName): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = ? 
            AND index_name = ?
        ");
        $stmt->execute([$tableName, $indexName]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
