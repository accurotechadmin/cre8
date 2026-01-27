<?php
/**
 * CRE8.pw Logging Service
 * 
 * Provides structured JSON logging with Monolog, channel separation,
 * and automatic secret sanitization.
 * 
 * @see docs/canon/11-Logging-Audit-and-Observability.md
 */

declare(strict_types=1);

namespace App\Services;

use App\Utilities\SensitiveDataSanitizer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Psr\Log\LoggerInterface;

/**
 * Logging Service Factory
 * 
 * Creates and configures Monolog loggers with JSON formatting and channel separation.
 */
class LoggingService
{
    /**
     * Create a logger for a specific channel
     * 
     * @param string $channel Channel name (api, auth, security, db, guzzle.http)
     * @param string $logPath Base log directory path
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
     * @return LoggerInterface Configured logger instance
     */
    public static function createLogger(string $channel, string $logPath, string $level = 'INFO'): LoggerInterface
    {
        $logger = new Logger($channel);
        
        // Create log file path (channel-specific)
        $logFile = rtrim($logPath, '/') . '/' . $channel . '.log';
        
        // Create stream handler with JSON formatter
        $handler = new StreamHandler($logFile, Logger::toMonologLevel($level));
        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(false); // Never log stack traces in production
        $handler->setFormatter($formatter);
        
        $logger->pushHandler($handler);
        
        return $logger;
    }

    /**
     * Sanitize context data before logging
     * 
     * Ensures sensitive data is redacted before being logged.
     * 
     * @param array<string, mixed> $context Log context data
     * @return array<string, mixed> Sanitized context
     */
    public static function sanitizeContext(array $context): array
    {
        return SensitiveDataSanitizer::sanitize($context);
    }

    /**
     * Log with automatic secret sanitization
     * 
     * Log with automatic secret sanitization
     * 
     * Wrapper around logger methods that automatically sanitizes context.
     * 
     * @param LoggerInterface $logger Logger instance
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Log context (will be sanitized)
     * @return void
     */
    public static function log(LoggerInterface $logger, string $level, string $message, array $context = []): void
    {
        $sanitizedContext = self::sanitizeContext($context);
        
        switch (strtoupper($level)) {
            case 'DEBUG':
                $logger->debug($message, $sanitizedContext);
                break;
            case 'INFO':
                $logger->info($message, $sanitizedContext);
                break;
            case 'WARNING':
            case 'WARN':
                $logger->warning($message, $sanitizedContext);
                break;
            case 'ERROR':
                $logger->error($message, $sanitizedContext);
                break;
            case 'CRITICAL':
                $logger->critical($message, $sanitizedContext);
                break;
            default:
                $logger->info($message, $sanitizedContext);
        }
    }
}
