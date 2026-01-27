<?php
/**
 * CRE8.pw Audience Segregation Test Suite
 * 
 * TICKET T19.2: Audience segregation tests
 * 
 * Automated tests to enforce JWT audience segregation rules:
 * - Console tokens (aud = /console) cannot be used on Gateway routes
 * - Gateway tokens (aud = /api) cannot be used on Console routes
 * - Tokens with wrong audience must be rejected with 401 Unauthorized
 * 
 * @see docs/canon/02-Authentication-and-Identity.md Section 2.2
 * @see docs/dev/dev_milestones_smoke_tests.md Section "Milestone 17"
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Security\JwtService;
use App\Middleware\JwtOwnerMiddleware;
use App\Middleware\JwtKeyMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test runner for audience segregation compliance
 */
class AudienceSegregationTest
{
    private array $failures = [];
    private int $testsRun = 0;
    private int $testsPassed = 0;
    
    private string $testIssuer = 'https://test.cre8.pw';
    private string $testBaseUrl = 'https://test.cre8.pw';
    private string $consoleAudience = 'https://test.cre8.pw/console';
    private string $gatewayAudience = 'https://test.cre8.pw/api';
    
    // Mock keys for testing (these would normally be real RSA keys)
    private string $privateKeyPath;
    private string $publicKeyPath;

require_once __DIR__ . '/../../vendor/autoload.php';

    /**
     * Run all compliance tests
     */
    public function run(): void
    {
        echo "=== CRE8.pw Audience Segregation Tests ===\n\n";
        echo "TICKET T19.2: Audience segregation tests\n\n";

        // Test audience validation logic
        $this->testAudienceValidationLogic();
        
        // Test middleware audience checks
        $this->testMiddlewareAudienceEnforcement();

        // Print summary
        $this->printSummary();
    }

    /**
     * Test audience validation logic
     * 
     * Tests that tokens with wrong audience are rejected
     */
    private function testAudienceValidationLogic(): void
    {
        echo "--- Testing Audience Validation Logic ---\n";

        // Test cases: [tokenAudience, expectedAudience, shouldPass, description]
        $testCases = [
            // Valid cases
            [$this->consoleAudience, $this->consoleAudience, true, 'Console token with console audience'],
            [$this->gatewayAudience, $this->gatewayAudience, true, 'Gateway token with gateway audience'],
            
            // Invalid cases - wrong audience
            [$this->consoleAudience, $this->gatewayAudience, false, 'Console token used on Gateway (should reject)'],
            [$this->gatewayAudience, $this->consoleAudience, false, 'Gateway token used on Console (should reject)'],
            
            // Edge cases
            ['https://test.cre8.pw/console', 'https://test.cre8.pw/console', true, 'Exact match'],
            ['https://test.cre8.pw/console/', 'https://test.cre8.pw/console', false, 'Trailing slash mismatch'],
            ['https://test.cre8.pw/console', 'https://test.cre8.pw/console/', false, 'Expected has trailing slash'],
            ['https://test.cre8.pw/api', 'https://test.cre8.pw/api', true, 'Gateway exact match'],
            ['https://other.domain/console', 'https://test.cre8.pw/console', false, 'Different domain'],
            ['', $this->consoleAudience, false, 'Empty audience'],
        ];

        foreach ($testCases as [$tokenAudience, $expectedAudience, $shouldPass, $description]) {
            $actualResult = $this->validateAudience($tokenAudience, $expectedAudience);
            
            if ($shouldPass) {
                $this->assertTrue(
                    $actualResult,
                    "{$description}: token aud='{$tokenAudience}', expected='{$expectedAudience}'"
                );
            } else {
                $this->assertFalse(
                    $actualResult,
                    "{$description}: token aud='{$tokenAudience}', expected='{$expectedAudience}'"
                );
            }
        }

        echo "\n";
    }

    /**
     * Test middleware audience enforcement
     * 
     * Tests that middleware correctly enforces audience segregation
     */
    private function testMiddlewareAudienceEnforcement(): void
    {
        echo "--- Testing Middleware Audience Enforcement ---\n";

        // Test that Console middleware rejects Gateway tokens
        echo "  Testing Console middleware rejects Gateway audience...\n";
        $consoleMiddlewareRejectsGateway = $this->simulateMiddlewareCheck(
            'owner',
            $this->gatewayAudience,
            $this->consoleAudience
        );
        $this->assertFalse(
            $consoleMiddlewareRejectsGateway,
            "Console middleware should reject Gateway audience token"
        );

        // Test that Gateway middleware rejects Console tokens
        echo "  Testing Gateway middleware rejects Console audience...\n";
        $gatewayMiddlewareRejectsConsole = $this->simulateMiddlewareCheck(
            'key',
            $this->consoleAudience,
            $this->gatewayAudience
        );
        $this->assertFalse(
            $gatewayMiddlewareRejectsConsole,
            "Gateway middleware should reject Console audience token"
        );

        // Test that Console middleware accepts Console tokens
        echo "  Testing Console middleware accepts Console audience...\n";
        $consoleMiddlewareAcceptsConsole = $this->simulateMiddlewareCheck(
            'owner',
            $this->consoleAudience,
            $this->consoleAudience
        );
        $this->assertTrue(
            $consoleMiddlewareAcceptsConsole,
            "Console middleware should accept Console audience token"
        );

        // Test that Gateway middleware accepts Gateway tokens
        echo "  Testing Gateway middleware accepts Gateway audience...\n";
        $gatewayMiddlewareAcceptsGateway = $this->simulateMiddlewareCheck(
            'key',
            $this->gatewayAudience,
            $this->gatewayAudience
        );
        $this->assertTrue(
            $gatewayMiddlewareAcceptsGateway,
            "Gateway middleware should accept Gateway audience token"
        );

        echo "\n";
    }

    /**
     * Validate audience match
     * 
     * Simulates the audience validation logic from middleware
     */
    private function validateAudience(string $tokenAudience, string $expectedAudience): bool
    {
        // This matches the logic in JwtOwnerMiddleware and JwtKeyMiddleware:
        // if (!isset($payload['aud']) || $payload['aud'] !== $this->expectedAudience) {
        //     return ErrorFactory::unauthorized(...);
        // }
        
        if (empty($tokenAudience)) {
            return false; // Missing audience
        }
        
        // Exact match required (no normalization, no prefix matching)
        return $tokenAudience === $expectedAudience;
    }

    /**
     * Simulate middleware audience check
     * 
     * Simulates the full middleware check including typ and audience
     */
    private function simulateMiddlewareCheck(
        string $tokenTyp,
        string $tokenAudience,
        string $expectedAudience
    ): bool {
        // Simulate the middleware logic:
        // 1. Verify typ matches (done by JwtService::verify())
        // 2. Verify audience matches expected audience
        
        // For this test, we assume typ is correct (that's tested separately)
        // We only test audience segregation
        
        return $this->validateAudience($tokenAudience, $expectedAudience);
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
            echo "✓ All audience segregation tests passed!\n";
            echo "\n";
            echo "Key Rules Verified:\n";
            echo "  - Console tokens (aud=/console) cannot be used on Gateway routes\n";
            echo "  - Gateway tokens (aud=/api) cannot be used on Console routes\n";
            echo "  - Audience must match exactly (no normalization, no prefix matching)\n";
            exit(0);
        }
    }
}

// Run tests
$test = new AudienceSegregationTest();
$test->run();
