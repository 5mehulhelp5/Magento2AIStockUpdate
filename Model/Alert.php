<?php
/**
 * Alert Model
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Alert model representing a stockout alert for a single SKU
 *
 * Tracks open/acknowledged/resolved lifecycle for stockout notifications.
 */
class Alert extends AbstractModel
{
    /** @var string Open status — alert is new and unaddressed */
    public const STATUS_OPEN = 'open';

    /** @var string Acknowledged status — admin has seen the alert */
    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    /** @var string Resolved status — alert has been resolved (reordered or otherwise handled) */
    public const STATUS_RESOLVED = 'resolved';

    /**
     * Initialize model with resource model binding
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\AI\StockPredict\Model\ResourceModel\Alert::class);
    }
}
