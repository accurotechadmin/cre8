<?php
/**
 * CRE8.pw ID Encoding Utilities
 * 
 * Provides ID encoding/decoding functions for BINARY(16) ↔ hex32 conversion.
 * Used throughout the application for ID format conversion.
 * 
 * @see docs/APPENDIX/A-Identifier-Encoding-Matrix.md
 * @see docs/canon/06-Data-Model.md Section 2
 */

declare(strict_types=1);

namespace App\Utilities;

/**
 * ID Encoding Utilities
 * 
 * Static utility functions for converting between binary and hex32 ID formats.
 */
class Ids
{
    /**
     * Convert hex32 string to binary ID (BINARY(16))
     * 
     * @param string $hex32 32-character lowercase hex string
     * @return string Binary ID (16 bytes)
     * @throws \InvalidArgumentException If hex32 is invalid
     */
    public static function hex32ToBinary(string $hex32): string
    {
        // Normalize to lowercase
        $hex32 = strtolower($hex32);
        
        // Validate format: exactly 32 hex characters
        if (!preg_match('/^[0-9a-f]{32}$/', $hex32)) {
            throw new \InvalidArgumentException("Invalid hex32 format: {$hex32}. Expected 32-character lowercase hex string.");
        }
        
        $binary = hex2bin($hex32);
        if ($binary === false) {
            throw new \InvalidArgumentException("Failed to convert hex32 to binary: {$hex32}");
        }
        
        return $binary;
    }

    /**
     * Convert binary ID (BINARY(16)) to hex32 string
     * 
     * @param string $binary Binary ID (16 bytes)
     * @return string hex32 string (32-character lowercase hex)
     * @throws \InvalidArgumentException If binary is invalid
     */
    public static function binaryToHex32(string $binary): string
    {
        if (strlen($binary) !== 16) {
            throw new \InvalidArgumentException("Binary ID must be exactly 16 bytes, got " . strlen($binary) . " bytes");
        }
        
        return bin2hex($binary);
    }

    /**
     * Generate a new random binary ID (BINARY(16))
     * 
     * @return string Binary ID (16 bytes)
     */
    public static function generateBinaryId(): string
    {
        return random_bytes(16);
    }

    /**
     * Generate a new random hex32 ID
     * 
     * @return string hex32 string (32-character lowercase hex)
     */
    public static function generateHex32Id(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Validate hex32 format
     * 
     * @param string $hex32 String to validate
     * @return bool True if valid hex32 format
     */
    public static function isValidHex32(string $hex32): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', strtolower($hex32)) === 1;
    }

    /**
     * Validate key public ID format (apub_...)
     * 
     * @param string $publicId String to validate
     * @return bool True if valid apub_ format
     */
    public static function isValidKeyPublicId(string $publicId): bool
    {
        return preg_match('/^apub_[a-zA-Z0-9_-]+$/', $publicId) === 1;
    }
}
