<?php
/**
 * CRE8.pw Schema Contract Check Script
 * 
 * TICKET T2.4: Schema contract checks
 * 
 * Command-line script to verify database schema matches expected contract.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Utilities\SchemaContractVerifier;

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

echo "Verifying schema contract...\n\n";

$results = SchemaContractVerifier::verify($pdo);

$allPassed = true;
foreach ($results as $check => $passed) {
    $status = $passed ? '✓' : '✗';
    echo "  {$status} {$check}\n";
    if (!$passed) {
        $allPassed = false;
    }
}

echo "\n";

if ($allPassed) {
    echo "All schema contract checks passed.\n";
    exit(0);
} else {
    echo "Some schema contract checks failed.\n";
    exit(1);
}
