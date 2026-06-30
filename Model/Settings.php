<?php
/**
 * Settings - Configuration access
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Settings class
 *
 * Provides access to module configuration with encryption support.
 * Handles all system configuration values for StockPredict.
 */
class Settings
{
    /** @var string Configuration path for module enable/disable */
    private const XML_PATH_ENABLED = 'ai_stockpredict/general/enabled';

    /** @var string Configuration path for logging enable/disable */
    private const XML_PATH_LOGGING = 'ai_stockpredict/general/enable_logging';

    /** @var string Configuration path for data retention days */
    private const XML_PATH_RETENTION_DAYS = 'ai_stockpredict/general/retention_days';

    /** @var string Configuration path for sales history days */
    private const XML_PATH_HISTORY_DAYS = 'ai_stockpredict/forecasting/history_days';

    /** @var string Configuration path for forecast horizon days */
    private const XML_PATH_FORECAST_HORIZON = 'ai_stockpredict/forecasting/forecast_horizon';

    /** @var string Configuration path for minimum order threshold */
    private const XML_PATH_MIN_ORDER_THRESHOLD = 'ai_stockpredict/forecasting/min_order_threshold';

    /** @var string Configuration path for critical days threshold */
    private const XML_PATH_CRITICAL_DAYS = 'ai_stockpredict/alerts/critical_days';

    /** @var string Configuration path for warning days threshold */
    private const XML_PATH_WARNING_DAYS = 'ai_stockpredict/alerts/warning_days';

    /** @var string Configuration path for watch days threshold */
    private const XML_PATH_WATCH_DAYS = 'ai_stockpredict/alerts/watch_days';

    /** @var string Configuration path for email alerts enable/disable */
    private const XML_PATH_EMAIL_ENABLED = 'ai_stockpredict/alerts/email_enabled';

    /** @var string Configuration path for alert email recipient */
    private const XML_PATH_EMAIL_RECIPIENT = 'ai_stockpredict/alerts/email_recipient';

    /** @var string Configuration path for AI enable/disable */
    private const XML_PATH_AI_ENABLED = 'ai_stockpredict/ai/enabled';

    /** @var string Configuration path for AI provider selection */
    private const XML_PATH_AI_PROVIDER = 'ai_stockpredict/ai/provider';

    /** @var string Configuration path for encrypted AI API key */
    private const XML_PATH_AI_KEY = 'ai_stockpredict/ai/api_key';

    /** @var string Configuration path for AI API timeout */
    private const XML_PATH_AI_TIMEOUT = 'ai_stockpredict/ai/timeout_ms';

    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig Magento configuration reader
     * @param EncryptorInterface $encryptor Encryption service for API keys
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {}

    /**
     * Check if module is enabled
     *
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_ENABLED);
    }

    /**
     * Check if logging is enabled
     *
     * @return bool True if logging enabled
     */
    public function isLoggingEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_LOGGING);
    }

    /**
     * Get data retention period in days
     *
     * @return int Retention days (default: 90)
     */
    public function getRetentionDays(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_RETENTION_DAYS) ?: 90);
    }

    /**
     * Get number of historical days to use for forecasting
     *
     * @return int History days (default: 365)
     */
    public function getHistoryDays(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_HISTORY_DAYS) ?: 365);
    }

    /**
     * Get forecast horizon in days
     *
     * @return int Forecast horizon (default: 30)
     */
    public function getForecastHorizon(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_FORECAST_HORIZON) ?: 30);
    }

    /**
     * Get minimum orders required before forecasting a SKU
     *
     * @return int Minimum order threshold (default: 5)
     */
    public function getMinOrderThreshold(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_MIN_ORDER_THRESHOLD) ?: 5);
    }

    /**
     * Get days until stockout threshold for critical severity
     *
     * @return int Critical days threshold (default: 7)
     */
    public function getCriticalDays(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_CRITICAL_DAYS) ?: 7);
    }

    /**
     * Get days until stockout threshold for warning severity
     *
     * @return int Warning days threshold (default: 14)
     */
    public function getWarningDays(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_WARNING_DAYS) ?: 14);
    }

    /**
     * Get days until stockout threshold for watch severity
     *
     * @return int Watch days threshold (default: 30)
     */
    public function getWatchDays(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_WATCH_DAYS) ?: 30);
    }

    /**
     * Check if email alerts are enabled
     *
     * @return bool True if email alerts enabled
     */
    public function isEmailEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_EMAIL_ENABLED);
    }

    /**
     * Get alert email recipient address
     *
     * @return string Email address (may be empty if not configured)
     */
    public function getEmailRecipient(): string
    {
        return (string)($this->scopeConfig->getValue(self::XML_PATH_EMAIL_RECIPIENT) ?: '');
    }

    /**
     * Check if AI analysis is enabled
     *
     * @return bool True if AI enabled
     */
    public function isAiEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_AI_ENABLED);
    }

    /**
     * Get AI provider name
     *
     * @return string Provider name (gemini|openai|claude, default: gemini)
     */
    public function getAiProvider(): string
    {
        return (string)($this->scopeConfig->getValue(self::XML_PATH_AI_PROVIDER) ?: 'gemini');
    }

    /**
     * Get decrypted AI API key
     *
     * @return string Decrypted API key or empty string if not set
     */
    public function getAiApiKey(): string
    {
        $encrypted = $this->scopeConfig->getValue(self::XML_PATH_AI_KEY);
        return $encrypted ? (string)$this->encryptor->decrypt($encrypted) : '';
    }

    /**
     * Get AI API timeout in milliseconds
     *
     * @return int Timeout in milliseconds (default: 30000)
     */
    public function getAiTimeoutMs(): int
    {
        return (int)($this->scopeConfig->getValue(self::XML_PATH_AI_TIMEOUT) ?: 30000);
    }
}
