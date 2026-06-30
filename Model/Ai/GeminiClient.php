<?php
/**
 * Gemini AI Client
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
 * Google Gemini API client for inventory demand forecasting
 *
 * Sends SKU sales data to Gemini Flash and returns JSON forecast recommendations.
 */
class GeminiClient implements AiClientInterface
{
    /** @var string Gemini API endpoint template (key appended as query param) */
    private const API_URL_TEMPLATE =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=%s';

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
     * Send prompt to Gemini and return text response
     *
     * @param string $prompt The forecast prompt with SKU context
     * @return string AI-generated text or empty string on error
     */
    public function analyze(string $prompt): string
    {
        $apiKey = $this->settings->getAiApiKey();
        if (!$apiKey) {
            $this->logger->warning('[STOCKPREDICT][GEMINI] No API key configured');
            return '';
        }

        try {
            $timeoutSeconds = (int)max(5, (int)ceil($this->settings->getAiTimeoutMs() / 1000));
            $payload = json_encode([
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => 256
                ]
            ]);

            if ($payload === false) {
                $this->logger->error('[STOCKPREDICT][GEMINI] Failed to encode payload: ' . json_last_error_msg());
                return '';
            }

            $url = sprintf(self::API_URL_TEMPLATE, $apiKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => $timeoutSeconds,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logger->error('[STOCKPREDICT][GEMINI] cURL error: ' . $error);
                return '';
            }

            $response = json_decode((string)$body, true);

            if (!is_array($response)) {
                $this->logger->error(sprintf(
                    '[STOCKPREDICT][GEMINI] Invalid JSON (HTTP %d): %s',
                    $status,
                    substr((string)$body, 0, 300)
                ));
                return '';
            }

            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return $response['candidates'][0]['content']['parts'][0]['text'];
            }

            $errorMsg = $response['error']['message'] ?? 'Unknown error';
            $this->logger->error('[STOCKPREDICT][GEMINI] API error: ' . $errorMsg);
            return '';

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][GEMINI] Exception: ' . $e->getMessage());
            return '';
        }
    }
}
