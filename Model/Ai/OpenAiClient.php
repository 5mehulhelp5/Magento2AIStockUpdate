<?php
/**
 * OpenAI Client
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
 * OpenAI API client for inventory demand forecasting
 *
 * Sends SKU sales data to GPT-4o-mini and returns JSON forecast recommendations.
 */
class OpenAiClient implements AiClientInterface
{
    /** @var string OpenAI chat completions endpoint */
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** @var string Model to use for forecasting */
    private const MODEL = 'gpt-4o-mini';

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
     * Send prompt to OpenAI and return text response
     *
     * @param string $prompt The forecast prompt with SKU context
     * @return string AI-generated text or empty string on error
     */
    public function analyze(string $prompt): string
    {
        $apiKey = $this->settings->getAiApiKey();
        if (!$apiKey) {
            $this->logger->warning('[STOCKPREDICT][OPENAI] No API key configured');
            return '';
        }

        try {
            $timeoutSeconds = (int)max(5, (int)ceil($this->settings->getAiTimeoutMs() / 1000));
            $payload = json_encode([
                'model'    => self::MODEL,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an inventory forecasting expert. Always respond with valid JSON only.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens'  => 256,
                'temperature' => 0.2
            ]);

            if ($payload === false) {
                $this->logger->error('[STOCKPREDICT][OPENAI] Failed to encode payload: ' . json_last_error_msg());
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
                    'Authorization: Bearer ' . $apiKey,
                ],
            ]);

            $body   = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logger->error('[STOCKPREDICT][OPENAI] cURL error: ' . $error);
                return '';
            }

            $response = json_decode((string)$body, true);

            if (!is_array($response)) {
                $this->logger->error(sprintf(
                    '[STOCKPREDICT][OPENAI] Invalid JSON (HTTP %d): %s',
                    $status,
                    substr((string)$body, 0, 300)
                ));
                return '';
            }

            if (isset($response['choices'][0]['message']['content'])) {
                return $response['choices'][0]['message']['content'];
            }

            $errorMsg = $response['error']['message'] ?? 'Unknown error';
            $this->logger->error('[STOCKPREDICT][OPENAI] API error: ' . $errorMsg);
            return '';

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][OPENAI] Exception: ' . $e->getMessage());
            return '';
        }
    }
}
