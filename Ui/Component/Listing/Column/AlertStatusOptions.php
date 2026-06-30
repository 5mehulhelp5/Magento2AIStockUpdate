<?php
/**
 * Alert Status Options for Grid Filter
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use AI\StockPredict\Model\Alert;

/**
 * AlertStatusOptions
 *
 * Provides status filter options for the alerts grid column filter.
 */
class AlertStatusOptions implements OptionSourceInterface
{
    /**
     * Get alert status options for select filter
     *
     * @return array Option array for select filter
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Alert::STATUS_OPEN,         'label' => __('Open')],
            ['value' => Alert::STATUS_ACKNOWLEDGED, 'label' => __('Acknowledged')],
            ['value' => Alert::STATUS_RESOLVED,     'label' => __('Resolved')],
        ];
    }
}
