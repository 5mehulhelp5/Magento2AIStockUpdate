<?php
/**
 * Log Service
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\Log;

use AI\StockPredict\Model\Settings;
use Psr\Log\LoggerInterface;

/**
 * Logging service with settings-aware conditional logging
 *
 * All log calls are gated behind Settings::isLoggingEnabled() so that
 * verbose debug output can be disabled in production.
 */
class LogService
{
    /**
     * Constructor
     *
     * @param Settings $settings Module configuration
     * @param LoggerInterface $logger Logger instance (AIStockPredictLogger virtual type)
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Log an informational message if logging is enabled
     *
     * @param string $message Log message
     * @param array $context Optional context data
     * @return void
     */
    public function logInfo(string $message, array $context = []): void
    {
        if ($this->settings->isLoggingEnabled()) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Log a warning message if logging is enabled
     *
     * @param string $message Log message
     * @param array $context Optional context data
     * @return void
     */
    public function logWarning(string $message, array $context = []): void
    {
        if ($this->settings->isLoggingEnabled()) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Log an error message (always logged regardless of logging setting)
     *
     * @param string $message Log message
     * @param array $context Optional context data
     * @return void
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
