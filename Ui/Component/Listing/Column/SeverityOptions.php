<?php
/**
 * Severity Options for Grid Filter
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use AI\StockPredict\Model\Forecast;

/**
 * SeverityOptions
 *
 * Provides severity filter options for the forecast grid column filter.
 */
class SeverityOptions implements OptionSourceInterface
{
    /**
     * Get severity options for select filter
     *
     * @return array Option array for select filter
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Forecast::SEVERITY_CRITICAL, 'label' => __('Critical')],
            ['value' => Forecast::SEVERITY_WARNING,  'label' => __('Warning')],
            ['value' => Forecast::SEVERITY_WATCH,    'label' => __('Watch')],
            ['value' => Forecast::SEVERITY_OK,       'label' => __('OK')],
        ];
    }
}
