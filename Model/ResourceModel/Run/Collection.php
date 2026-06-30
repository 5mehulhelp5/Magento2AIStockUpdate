<?php
/**
 * Run Collection
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model\ResourceModel\Run;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Run Collection
 *
 * Collection of Run models.
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
            \AI\StockPredict\Model\Run::class,
            \AI\StockPredict\Model\ResourceModel\Run::class
        );
    }
}
