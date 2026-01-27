<?php
/**
 * CRE8.pw Migration Runner
 * 
 * TICKET T2.1: Schema migrations for all entities
 * 
 * Runs database migrations in order.
 * Tracks applied migrations in a migrations table.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Get database connection
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_PORT'] ?? 3306,
    $_ENV['DB_NAME']
);

$pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Create migrations tracking table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        migration VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin
");

// Get list of migration files
$migrationDir = __DIR__ . '/../migrations';
$migrations = glob($migrationDir . '/*.php');
sort($migrations);

// Get applied migrations
$stmt = $pdo->query("SELECT migration FROM migrations");
$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

$action = $argv[1] ?? 'up';

if ($action === 'up') {
    echo "Running migrations...\n";
    
    foreach ($migrations as $migrationFile) {
        $migrationName = basename($migrationFile);
        
        if (in_array($migrationName, $applied, true)) {
            echo "  ✓ {$migrationName} (already applied)\n";
            continue;
        }
        
        echo "  → {$migrationName}\n";
        
        $migration = require $migrationFile;
        
        if (!isset($migration['up'])) {
            throw new RuntimeException("Migration {$migrationName} missing 'up' function");
        }
        
        try {
            $pdo->beginTransaction();
            $migration['up']($pdo);
            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
            $pdo->commit();
            echo "    ✓ Applied\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "    ✗ Failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "All migrations applied.\n";
} elseif ($action === 'down') {
    echo "Rolling back migrations...\n";
    
    // Roll back in reverse order
    $migrations = array_reverse($migrations);
    
    foreach ($migrations as $migrationFile) {
        $migrationName = basename($migrationFile);
        
        if (!in_array($migrationName, $applied, true)) {
            continue;
        }
        
        echo "  → {$migrationName}\n";
        
        $migration = require $migrationFile;
        
        if (!isset($migration['down'])) {
            throw new RuntimeException("Migration {$migrationName} missing 'down' function");
        }
        
        try {
            $pdo->beginTransaction();
            $migration['down']($pdo);
            $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
            $stmt->execute([$migrationName]);
            $pdo->commit();
            echo "    ✓ Rolled back\n";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "    ✗ Failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "All migrations rolled back.\n";
} else {
    echo "Usage: php migrate.php [up|down]\n";
    exit(1);
}
