<?php
/**
 * Claude AI Client
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\Ai;

use AI\StockPredict\Api\AiClientInterface;
use AI\StockPredict\Model\Settings;
use Psr\Log\LoggerInterface;

/**
 * Anthropic Claude API client for inventory demand forecasting
 *
 * Sends SKU sales data to Claude Sonnet and returns JSON forecast recommendations.
 * Uses the Messages API (POST /v1/messages).
 */
class ClaudeClient implements AiClientInterface
{
    /** @var string Anthropic Messages API endpoint */
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    /** @var string Anthropic API version header value */
    private const API_VERSION = '2024-06-01';

    /** @var string Claude model identifier */
    private const MODEL = 'claude-sonnet-4-6';

    /**
     * Constructor
     *
     * @param Settings $settings Module configuration
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Send prompt to Claude and return text response
     *
     * @param string $prompt The forecast prompt with SKU context
     * @return string AI-generated text or empty string on error
     */
    public function analyze(string $prompt): string
    {
        $apiKey = $this->settings->getAiApiKey();
        if (!$apiKey) {
            $this->logger->warning('[STOCKPREDICT][CLAUDE] No API key configured');
            return '';
        }

        try {
            $timeoutSeconds = (int)max(5, (int)ceil($this->settings->getAiTimeoutMs() / 1000));
            $payload = json_encode([
                'model'      => self::MODEL,
                'max_tokens' => 256,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]);

            if ($payload === false) {
                $this->logger->error('[STOCKPREDICT][CLAUDE] Failed to encode payload: ' . json_last_error_msg());
                return '';
            }

            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => $timeoutSeconds,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: ' . self::API_VERSION,
                ],
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logger->error('[STOCKPREDICT][CLAUDE] cURL error: ' . $error);
                return '';
            }

            $response = json_decode((string)$body, true);

            if (!is_array($response)) {
                $this->logger->error(sprintf(
                    '[STOCKPREDICT][CLAUDE] Invalid JSON (HTTP %d): %s',
                    $status,
                    substr((string)$body, 0, 300)
                ));
                return '';
            }

            if (isset($response['content'][0]['text'])) {
                return $response['content'][0]['text'];
            }

            $errorMsg = $response['error']['message'] ?? 'Unknown error';
            $this->logger->error('[STOCKPREDICT][CLAUDE] API error: ' . $errorMsg);
            return '';

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][CLAUDE] Exception: ' . $e->getMessage());
            return '';
        }
    }
}
