<?php
/**
 * Forecast Collection
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\ResourceModel\Forecast;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Forecast Collection
 *
 * Collection of Forecast models.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            \AI\StockPredict\Model\Forecast::class,
            \AI\StockPredict\Model\ResourceModel\Forecast::class
        );
    }
}
