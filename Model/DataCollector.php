<?php
/**
 * Data Collector
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * DataCollector
 *
 * Queries Magento's sales and inventory tables to gather the raw data
 * required by ForecastEngine for demand forecasting calculations.
 */
class DataCollector
{
    /** @var string[] Order statuses considered as completed sales */
    private const COMPLETED_STATUSES = ['complete', 'processing'];

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection Database resource connection
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Get aggregated sales data for given SKUs over a historical period
     *
     * Queries sales_order_item joined with sales_order for completed orders.
     * If $skus is empty, returns data for all SKUs with at least 1 sale in the period.
     *
     * @param array $skus List of SKUs to query (empty = all SKUs)
     * @param int $days Number of historical days to include
     * @return array Keyed by SKU: [total_qty_sold, last_30d, last_90d, last_365d,
     *               avg_daily, orders_count, product_name]
     */
    public function getSalesData(array $skus = [], int $days = 365): array
    {
        try {
            $connection   = $this->resourceConnection->getConnection();
            $orderTable   = $this->resourceConnection->getTableName('sales_order');
            $orderItem    = $this->resourceConnection->getTableName('sales_order_item');

            $cutoff = (new \DateTime("-{$days} days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $cutoff30  = (new \DateTime('-30 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $cutoff90  = (new \DateTime('-90 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $cutoff365 = (new \DateTime('-365 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $select = $connection->select()
                ->from(
                    ['oi' => $orderItem],
                    [
                        'sku'           => 'oi.sku',
                        'product_name'  => 'oi.name',
                        'total_qty_sold' => new \Zend_Db_Expr('SUM(oi.qty_ordered)'),
                        'orders_count'  => new \Zend_Db_Expr('COUNT(DISTINCT oi.order_id)'),
                        'last_30d'      => new \Zend_Db_Expr(
                            'SUM(IF(o.created_at >= ' . $connection->quote($cutoff30) . ', oi.qty_ordered, 0))'
                        ),
                        'last_90d'      => new \Zend_Db_Expr(
                            'SUM(IF(o.created_at >= ' . $connection->quote($cutoff90) . ', oi.qty_ordered, 0))'
                        ),
                        'last_365d'     => new \Zend_Db_Expr(
                            'SUM(IF(o.created_at >= ' . $connection->quote($cutoff365) . ', oi.qty_ordered, 0))'
                        ),
                    ]
                )
                ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
                ->where('o.status IN (?)', self::COMPLETED_STATUSES)
                ->where('o.created_at >= ?', $cutoff)
                ->where('oi.parent_item_id IS NULL')
                ->group('oi.sku');

            if (!empty($skus)) {
                $select->where('oi.sku IN (?)', $skus);
            }

            $rows   = $connection->fetchAll($select);
            $result = [];

            foreach ($rows as $row) {
                $totalQty   = (float)$row['total_qty_sold'];
                $avgDaily   = $days > 0 ? round($totalQty / $days, 4) : 0.0;

                $result[$row['sku']] = [
                    'total_qty_sold' => $totalQty,
                    'last_30d'       => (int)$row['last_30d'],
                    'last_90d'       => (int)$row['last_90d'],
                    'last_365d'      => (int)$row['last_365d'],
                    'avg_daily'      => $avgDaily,
                    'orders_count'   => (int)$row['orders_count'],
                    'product_name'   => (string)$row['product_name'],
                ];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][DATACOLLECTOR] getSalesData failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current stock quantities for given SKUs
     *
     * Queries cataloginventory_stock_item joined with catalog_product_entity.
     *
     * @param array $skus List of SKUs to look up
     * @return array Keyed by SKU: [qty, is_in_stock, product_name]
     */
    public function getCurrentStock(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        try {
            $connection  = $this->resourceConnection->getConnection();
            $stockTable  = $this->resourceConnection->getTableName('cataloginventory_stock_item');
            $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

            $select = $connection->select()
                ->from(
                    ['si' => $stockTable],
                    [
                        'qty'         => 'si.qty',
                        'is_in_stock' => 'si.is_in_stock',
                    ]
                )
                ->join(
                    ['cpe' => $productTable],
                    'si.product_id = cpe.entity_id',
                    ['sku' => 'cpe.sku']
                )
                ->where('cpe.sku IN (?)', $skus);

            $rows   = $connection->fetchAll($select);
            $result = [];

            foreach ($rows as $row) {
                $result[$row['sku']] = [
                    'qty'         => (float)$row['qty'],
                    'is_in_stock' => (bool)$row['is_in_stock'],
                ];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][DATACOLLECTOR] getCurrentStock failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top SKUs by sales volume over a given period
     *
     * Returns an array of SKU strings ordered by total_qty_sold descending.
     *
     * @param int $limit Maximum number of SKUs to return
     * @param int $days Number of historical days to analyze
     * @return array List of SKU strings
     */
    public function getTopSkusByVolume(int $limit = 500, int $days = 365): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $orderTable = $this->resourceConnection->getTableName('sales_order');
            $orderItem  = $this->resourceConnection->getTableName('sales_order_item');

            $cutoff = (new \DateTime("-{$days} days", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            $select = $connection->select()
                ->from(
                    ['oi' => $orderItem],
                    [
                        'sku'            => 'oi.sku',
                        'total_qty_sold' => new \Zend_Db_Expr('SUM(oi.qty_ordered)'),
                    ]
                )
                ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
                ->where('o.status IN (?)', self::COMPLETED_STATUSES)
                ->where('o.created_at >= ?', $cutoff)
                ->where('oi.parent_item_id IS NULL')
                ->group('oi.sku')
                ->order('total_qty_sold DESC')
                ->limit($limit);

            $rows = $connection->fetchAll($select);
            return array_column($rows, 'sku');

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][DATACOLLECTOR] getTopSkusByVolume failed: ' . $e->getMessage());
            return [];
        }
    }
}
