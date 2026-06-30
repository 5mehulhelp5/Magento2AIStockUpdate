<?php
/**
 * AI Client Factory
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\Ai;

use Magento\Framework\App\ObjectManager;
use AI\StockPredict\Api\AiClientInterface;
use AI\StockPredict\Model\Settings;

/**
 * AI Client Factory - Strategy pattern dispatcher
 *
 * Selects the appropriate AI client based on admin configuration.
 *
 * ClaudeClient is resolved via ObjectManager::getInstance() rather than
 * constructor injection. This keeps the constructor signature identical to
 * the compiled DI metadata (which cannot be regenerated due to file-ownership
 * constraints on generated/metadata/). ObjectManager use is intentional and
 * acceptable here: the factory itself exists to hide object creation, and
 * ClaudeClient is a shared singleton so there is no performance penalty.
 */
class ClientFactory
{
    /**
     * Constructor
     *
     * @param GeminiClient $geminiClient Gemini client implementation
     * @param OpenAiClient $openAiClient OpenAI client implementation
     * @param Settings $settings Module configuration
     */
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly OpenAiClient $openAiClient,
        private readonly Settings $settings
    ) {}

    /**
     * Create the configured AI client based on admin settings
     *
     * @return AiClientInterface Active AI client for the configured provider
     */
    public function create(): AiClientInterface
    {
        return match ($this->settings->getAiProvider()) {
            'gemini' => $this->geminiClient,
            'claude' => ObjectManager::getInstance()->get(ClaudeClient::class),
            default  => $this->openAiClient,
        };
    }
}
