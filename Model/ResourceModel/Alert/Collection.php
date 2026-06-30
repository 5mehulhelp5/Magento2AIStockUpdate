<?php
/**
 * Alert Collection
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\ResourceModel\Alert;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Alert Collection
 *
 * Collection of Alert models.
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
            \AI\StockPredict\Model\Alert::class,
            \AI\StockPredict\Model\ResourceModel\Alert::class
        );
    }
}
