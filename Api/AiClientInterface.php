<?php
/**
 * AI Client Interface
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Api;

/**
 * Contract for AI client implementations used by ForecastEngine
 *
 * All AI providers (Gemini, OpenAI, Claude) must implement this interface.
 */
interface AiClientInterface
{
    /**
     * Send a prompt to the AI provider and return the text response
     *
     * @param string $prompt The prompt text to send
     * @return string The AI-generated response text, or empty string on error
     */
    public function analyze(string $prompt): string;
}
