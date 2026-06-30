<?php
/**
 * Run Model
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Run model representing a single forecast execution record
 *
 * Tracks each invocation of ForecastEngine — status, stats, and timing.
 */
class Run extends AbstractModel
{
    /** @var string Pending status — run created but not yet started */
    public const STATUS_PENDING = 'pending';

    /** @var string Running status — forecast is currently executing */
    public const STATUS_RUNNING = 'running';

    /** @var string Completed status — run finished successfully */
    public const STATUS_COMPLETED = 'completed';

    /** @var string Failed status — run encountered a fatal error */
    public const STATUS_FAILED = 'failed';

    /** @var string Cron trigger — run initiated by scheduled cron job */
    public const TRIGGERED_CRON = 'cron';

    /** @var string CLI trigger — run initiated via CLI command */
    public const TRIGGERED_CLI = 'cli';

    /** @var string Admin trigger — run initiated from admin panel */
    public const TRIGGERED_ADMIN = 'admin';

    /**
     * Initialize model with resource model binding
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\AI\StockPredict\Model\ResourceModel\Run::class);
    }
}
