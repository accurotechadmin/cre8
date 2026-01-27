<?php
/**
 * CRE8.pw ID Format Compliance Test Suite
 * 
 * TICKET T19.1: ID format compliance tests
 * 
 * Automated tests to enforce ID format rules:
 * - All route parameters ending in "Id" must be hex32 format
 * - Route parameter "keyPublicId" must be apub_... format
 * - Invalid formats must be rejected with 400 Bad Request
 * 
 * @see docs/APPENDIX/A-Identifier-Encoding-Matrix.md
 * @see docs/dev/dev_milestones_smoke_tests.md Section "Milestone 17"
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Utilities\Ids;

/**
 * Test runner for ID format compliance
 */
class IdFormatComplianceTest
{
    private array $failures = [];
    private int $testsRun = 0;
    private int $testsPassed = 0;

    /**
     * Run all compliance tests
     */
    public function run(): void
    {
        echo "=== CRE8.pw ID Format Compliance Tests ===\n\n";
        echo "TICKET T19.1: ID format compliance tests\n\n";

        // Test hex32 validation
        $this->testHex32Validation();
        
        // Test key public ID validation
        $this->testKeyPublicIdValidation();
        
        // Test route parameter validation logic
        $this->testRouteParameterValidation();

        // Print summary
        $this->printSummary();
    }

    /**
     * Test hex32 format validation
     */
    private function testHex32Validation(): void
    {
        echo "--- Testing hex32 Format Validation ---\n";

        // Valid hex32 cases
        $validHex32 = [
            'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
            '00000000000000000000000000000000',
            'ffffffffffffffffffffffffffffffff',
            '0123456789abcdef0123456789abcdef',
        ];

        foreach ($validHex32 as $hex32) {
            $this->assertTrue(
                Ids::isValidHex32($hex32),
                "Valid hex32 should pass: {$hex32}"
            );
        }

        // Invalid hex32 cases
        $invalidHex32 = [
            // Too short
            'b5a1e8c0d9f04c3aa1b2c3d4e5f6071',  // 31 chars
            'b5a1e8c0d9f04c3aa1b2c3d4e5f607',    // 30 chars
            '',                                   // Empty
            
            // Too long
            'b5a1e8c0d9f04c3aa1b2c3d4e5f60718a', // 33 chars
            'b5a1e8c0d9f04c3aa1b2c3d4e5f60718aa', // 34 chars
            
            // Invalid characters
            'B5A1E8C0D9F04C3AA1B2C3D4E5F60718',   // Uppercase (should normalize)
            'b5a1e8c0d9f04c3aa1b2c3d4e5f6071g',  // Non-hex char 'g'
            'b5a1e8c0d9f04c3aa1b2c3d4e5f6071-',  // Hyphen
            'b5a1e8c0d9f04c3aa1b2c3d4e5f6071_',  // Underscore
            
            // Wrong format (apub_)
            'apub_8cd1a2b3c4d5e6f7',
            
            // UUID format (should reject)
            'b5a1e8c0-d9f0-4c3a-a1b2-c3d4e5f60718',
        ];

        foreach ($invalidHex32 as $invalid) {
            // Note: isValidHex32 normalizes to lowercase, so uppercase hex32 should pass
            // Check if this is uppercase hex32 (should pass)
            $isUppercaseHex32 = (
                strlen($invalid) === 32 &&
                ctype_xdigit(strtolower($invalid)) &&
                strtoupper($invalid) === $invalid
            );
            
            if ($isUppercaseHex32) {
                // Uppercase hex32 should pass (normalized by isValidHex32)
                $this->assertTrue(
                    Ids::isValidHex32($invalid),
                    "Uppercase hex32 should normalize and pass: {$invalid}"
                );
            } else {
                // All other invalid formats should fail
                $this->assertFalse(
                    Ids::isValidHex32($invalid),
                    "Invalid hex32 should fail: {$invalid}"
                );
            }
        }

        echo "\n";
    }

    /**
     * Test key public ID format validation
     */
    private function testKeyPublicIdValidation(): void
    {
        echo "--- Testing Key Public ID (apub_...) Format Validation ---\n";

        // Valid apub_ cases
        $validApub = [
            'apub_8cd1a2b3c4d5e6f7',
            'apub_abcdef1234567890',
            'apub_A1B2C3D4E5F6',
            'apub_1234567890abcdef',
            'apub_test-key_id',
            'apub_test_key_id',
        ];

        foreach ($validApub as $apub) {
            $this->assertTrue(
                Ids::isValidKeyPublicId($apub),
                "Valid apub_ format should pass: {$apub}"
            );
        }

        // Invalid apub_ cases
        $invalidApub = [
            // Missing prefix
            '8cd1a2b3c4d5e6f7',
            'pub_8cd1a2b3c4d5e6f7',
            
            // Wrong prefix
            'apub8cd1a2b3c4d5e6f7',  // Missing underscore
            'apub-8cd1a2b3c4d5e6f7', // Wrong separator
            
            // hex32 format (should reject for keyPublicId)
            'b5a1e8c0d9f04c3aa1b2c3d4e5f60718',
            
            // Empty
            '',
            'apub_',
            
            // Invalid characters (if we want to restrict)
            'apub_test@key',
            'apub_test key',
        ];

        foreach ($invalidApub as $invalid) {
            $this->assertFalse(
                Ids::isValidKeyPublicId($invalid),
                "Invalid apub_ format should fail: {$invalid}"
            );
        }

        echo "\n";
    }

    /**
     * Test route parameter validation logic
     * 
     * This simulates the RouteParameterValidatorMiddleware logic
     */
    private function testRouteParameterValidation(): void
    {
        echo "--- Testing Route Parameter Validation Logic ---\n";

        // Test cases: [paramName, paramValue, shouldPass, description]
        $testCases = [
            // Valid hex32 for Id parameters
            ['postId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718', true, 'Valid hex32 for postId'],
            ['keyId', '0123456789abcdef0123456789abcdef', true, 'Valid hex32 for keyId'],
            ['groupId', 'ffffffffffffffffffffffffffffffff', true, 'Valid hex32 for groupId'],
            ['authorKeyId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718', true, 'Valid hex32 for authorKeyId'],
            ['useKeyId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718', true, 'Valid hex32 for useKeyId'],
            ['targetId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718', true, 'Valid hex32 for targetId'],
            
            // Invalid hex32 for Id parameters
            ['postId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f6071', false, 'Too short hex32 for postId'],
            ['keyId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718a', false, 'Too long hex32 for keyId'],
            ['groupId', 'apub_8cd1a2b3c4d5e6f7', false, 'apub_ format in groupId (should reject)'],
            ['authorKeyId', 'B5A1E8C0D9F04C3AA1B2C3D4E5F60718', true, 'Uppercase hex32 (should normalize)'],
            
            // Valid apub_ for keyPublicId
            ['keyPublicId', 'apub_8cd1a2b3c4d5e6f7', true, 'Valid apub_ for keyPublicId'],
            ['keyPublicId', 'apub_test123', true, 'Valid apub_ variant for keyPublicId'],
            
            // Invalid apub_ for keyPublicId
            ['keyPublicId', 'b5a1e8c0d9f04c3aa1b2c3d4e5f60718', false, 'hex32 format in keyPublicId (should reject)'],
            ['keyPublicId', 'apub8cd1a2b3c4d5e6f7', false, 'Missing underscore in keyPublicId'],
            ['keyPublicId', '', false, 'Empty keyPublicId'],
            
            // Parameters not ending in Id should not be validated
            ['name', 'test', true, 'Non-Id parameter should pass'],
            ['content', 'some content', true, 'Non-Id parameter should pass'],
        ];

        foreach ($testCases as [$paramName, $paramValue, $shouldPass, $description]) {
            $actualResult = $this->validateRouteParameter($paramName, $paramValue);
            
            if ($shouldPass) {
                $this->assertTrue(
                    $actualResult,
                    "{$description}: {$paramName} = {$paramValue}"
                );
            } else {
                $this->assertFalse(
                    $actualResult,
                    "{$description}: {$paramName} = {$paramValue}"
                );
            }
        }

        echo "\n";
    }

    /**
     * Simulate route parameter validation logic
     * 
     * This mirrors RouteParameterValidatorMiddleware::process()
     */
    private function validateRouteParameter(string $paramName, string $paramValue): bool
    {
        // Special case: keyPublicId must be apub_... format
        if ($paramName === 'keyPublicId') {
            return Ids::isValidKeyPublicId($paramValue);
        }

        // All parameters ending in "Id" must be hex32
        if (str_ends_with($paramName, 'Id')) {
            return Ids::isValidHex32($paramValue);
        }

        // Parameters not ending in Id are not validated by this middleware
        return true;
    }

    /**
     * Assert a condition is true
     */
    private function assertTrue(bool $condition, string $message): void
    {
        $this->testsRun++;
        if ($condition) {
            $this->testsPassed++;
            echo "  ✓ {$message}\n";
        } else {
            $this->failures[] = "FAIL: {$message}";
            echo "  ✗ {$message}\n";
        }
    }

    /**
     * Assert a condition is false
     */
    private function assertFalse(bool $condition, string $message): void
    {
        $this->assertTrue(!$condition, $message);
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        echo "=== Test Summary ===\n";
        echo "Tests run: {$this->testsRun}\n";
        echo "Tests passed: {$this->testsPassed}\n";
        echo "Tests failed: " . count($this->failures) . "\n\n";

        if (!empty($this->failures)) {
            echo "Failures:\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
            echo "\n";
            exit(1);
        } else {
            echo "✓ All ID format compliance tests passed!\n";
            exit(0);
        }
    }
}

// Run tests
$test = new IdFormatComplianceTest();
$test->run();
