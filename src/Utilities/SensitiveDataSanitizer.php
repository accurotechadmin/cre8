<?php
/**
 * CRE8.pw Sensitive Data Sanitization Utility
 * 
 * Provides utilities to sanitize sensitive data before logging.
 * Ensures secrets are never accidentally logged.
 * 
 * @see docs/canon/11-Logging-Audit-and-Observability.md Section 1.2
 */

declare(strict_types=1);

namespace App\Utilities;

/**
 * Sensitive Data Sanitization Utility
 * 
 * Provides methods to sanitize sensitive data before logging or serialization.
 */
class SensitiveDataSanitizer
{
    /**
     * List of keys that contain sensitive data and should be redacted
     * 
     * Sensitive keys that should never be logged.
     * 
     * @var array<string>
 */
    private const SENSITIVE_KEYS = [
        'key_secret',
        'password',
        'password_hash',
        'refresh_token',
        'access_token',
        'private_key',
        'secret',
        'apikey_secret', // ApiKey secret part
        'api_key_secret', // Alternative naming
    ];

    /**
     * Sanitize an array by redacting sensitive keys
     * 
     * Sanitize sensitive data
     * 
     * Replaces sensitive values with "[REDACTED]" to prevent accidental logging.
     * 
     * @param array<string, mixed> $data Data to sanitize
     * @return array<string, mixed> Sanitized data
     */
    public static function sanitize(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            
            // Check if key matches any sensitive pattern
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($keyLower, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Check if a key contains sensitive data
     * 
     * @param string $key Key name to check
     * @return bool True if key is sensitive
     */
    public static function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($keyLower, $sensitiveKey)) {
                return true;
            }
        }
        return false;
    }
}
