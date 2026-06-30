<?php
/**
 * Forecast Resource Model
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Forecast Resource Model
 *
 * Handles database operations for Forecast entity.
 */
class Forecast extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('ai_stockpredict_forecast', 'forecast_id');
    }
}
