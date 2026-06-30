<?php
/**
 * AI Provider Source Model
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * AI provider dropdown options for system configuration
 *
 * Used by etc/adminhtml/system.xml for the AI provider select field.
 */
class AiProvider implements OptionSourceInterface
{
    /**
     * Get available AI provider options
     *
     * @return array Option array for select field
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'gemini', 'label' => __('Google Gemini Flash (Free)')],
            ['value' => 'openai', 'label' => __('OpenAI GPT-4o-mini')],
            ['value' => 'claude', 'label' => __('Anthropic Claude Sonnet (Best)')],
        ];
    }
}
