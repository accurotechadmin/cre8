<?php
/**
 * CRE8.pw Bootstrap Validator
 * 
 * Validates environment configuration on application startup.
 * Fails fast with clear error messages if configuration is invalid.
 * 
 * CRITICAL: Never log secrets in error messages.
 * 
 * @see docs/APPENDIX/B-Environment-Configuration.md Section 11
 */

declare(strict_types=1);

namespace App\Utilities;

/**
 * Bootstrap Validator
 * 
 * Validates all required environment configuration and fails fast
 * if any critical configuration is missing or invalid.
 */
class BootstrapValidator
{
    /**
     * Validate all bootstrap requirements
     * 
     * @throws \RuntimeException If validation fails
     */
    public static function validate(): void
    {
        self::validateDatabaseConfig();
        self::validateJwtConfig();
        self::validateCorsConfig();
        self::validateRateLimitConfig();
        self::validateLogPath();
        self::validateRequiredEnvVars();
    }

    /**
     * Validate database configuration
     * 
     * @throws \RuntimeException If DB config is invalid
     */
    private static function validateDatabaseConfig(): void
    {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                throw new \RuntimeException("Missing required environment variable: {$var}");
            }
        }

        // Validate DB connection (will be fully tested in M2 when PDO is configured)
        // For now, just ensure variables are set
    }

    /**
     * Validate JWT configuration
     * 
     * @throws \RuntimeException If JWT config is invalid
     */
    private static function validateJwtConfig(): void
    {
        $required = [
            'JWT_PRIVATE_KEY_PATH',
            'JWT_PUBLIC_KEY_PATH',
            'JWT_ISSUER',
            'JWT_AUDIENCE',
        ];

        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                throw new \RuntimeException("Missing required environment variable: {$var}");
            }
        }

        // Validate JWT key files exist and are readable
        $privateKeyPath = $_ENV['JWT_PRIVATE_KEY_PATH'];
        $publicKeyPath = $_ENV['JWT_PUBLIC_KEY_PATH'];

        if (!file_exists($privateKeyPath)) {
            throw new \RuntimeException("JWT private key file not found: {$privateKeyPath}");
        }

        if (!is_readable($privateKeyPath)) {
            throw new \RuntimeException("JWT private key file is not readable: {$privateKeyPath}");
        }

        if (!file_exists($publicKeyPath)) {
            throw new \RuntimeException("JWT public key file not found: {$publicKeyPath}");
        }

        if (!is_readable($publicKeyPath)) {
            throw new \RuntimeException("JWT public key file is not readable: {$publicKeyPath}");
        }

        // Validate key format (basic PEM check)
        $privateKeyContent = file_get_contents($privateKeyPath);
        if ($privateKeyContent === false || !str_contains($privateKeyContent, '-----BEGIN')) {
            throw new \RuntimeException("JWT private key file appears to be invalid PEM format");
        }

        $publicKeyContent = file_get_contents($publicKeyPath);
        if ($publicKeyContent === false || !str_contains($publicKeyContent, '-----BEGIN')) {
            throw new \RuntimeException("JWT public key file appears to be invalid PEM format");
        }
    }

    /**
     * Validate CORS configuration
     * 
     * @throws \RuntimeException If CORS config is invalid
     */
    private static function validateCorsConfig(): void
    {
        // CORS_ALLOWED_ORIGINS is optional but if set, must be parseable
        if (isset($_ENV['CORS_ALLOWED_ORIGINS']) && !empty($_ENV['CORS_ALLOWED_ORIGINS'])) {
            $origins = self::parseCommaSeparated($_ENV['CORS_ALLOWED_ORIGINS']);
            if (empty($origins)) {
                throw new \RuntimeException("CORS_ALLOWED_ORIGINS is set but contains no valid origins");
            }
        }
    }

    /**
     * Validate rate limit configuration
     * 
     * @throws \RuntimeException If rate limit config is invalid
     */
    private static function validateRateLimitConfig(): void
    {
        $rateLimitVars = [
            'RATE_LIMIT_GENERAL',
            'RATE_LIMIT_AUTH',
            'RATE_LIMIT_API',
        ];

        foreach ($rateLimitVars as $var) {
            if (isset($_ENV[$var]) && !empty($_ENV[$var])) {
                // Format: "100 per minute" or "60 per hour"
                if (!preg_match('/^\d+\s+per\s+(minute|hour|second)$/i', $_ENV[$var])) {
                    throw new \RuntimeException("Invalid rate limit format for {$var}: '{$_ENV[$var]}'. Expected format: '100 per minute'");
                }
            }
        }

        // Validate backing store
        if (isset($_ENV['RATE_LIMIT_BACKING']) && !in_array($_ENV['RATE_LIMIT_BACKING'], ['memory', 'database'], true)) {
            throw new \RuntimeException("RATE_LIMIT_BACKING must be 'memory' or 'database', got: '{$_ENV['RATE_LIMIT_BACKING']}'");
        }
    }

    /**
     * Validate log path exists and is writable
     * 
     * @throws \RuntimeException If log path is invalid
     */
    private static function validateLogPath(): void
    {
        if (empty($_ENV['LOG_PATH'])) {
            throw new \RuntimeException("Missing required environment variable: LOG_PATH");
        }

        $logPath = $_ENV['LOG_PATH'];

        // Create directory if it doesn't exist
        if (!is_dir($logPath)) {
            if (!mkdir($logPath, 0755, true)) {
                throw new \RuntimeException("Cannot create log directory: {$logPath}");
            }
        }

        // Check if writable
        if (!is_writable($logPath)) {
            throw new \RuntimeException("Log directory is not writable: {$logPath}");
        }
    }

    /**
     * Validate required environment variables
     * 
     * @throws \RuntimeException If required vars are missing
     */
    private static function validateRequiredEnvVars(): void
    {
        $required = [
            'APP_NAME',
            'APP_ENV',
            'APP_URL',
        ];

        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                throw new \RuntimeException("Missing required environment variable: {$var}");
            }
        }

        // Validate APP_ENV value
        $validEnvs = ['production', 'development', 'testing'];
        if (!in_array($_ENV['APP_ENV'] ?? '', $validEnvs, true)) {
            throw new \RuntimeException("APP_ENV must be one of: " . implode(', ', $validEnvs));
        }
    }

    /**
     * Parse comma-separated string into array
     * 
     * @param string $value Comma-separated string
     * @return array<string> Trimmed values
     */
    private static function parseCommaSeparated(string $value): array
    {
        $values = explode(',', $value);
        return array_map('trim', array_filter($values));
    }
}
