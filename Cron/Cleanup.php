<?php
/**
 * Cleanup Cron Job
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Cron;

use Magento\Framework\App\ResourceConnection;
use AI\StockPredict\Model\Alert;
use AI\StockPredict\Model\Settings;
use Psr\Log\LoggerInterface;

/**
 * Cleanup Cron
 *
 * Runs daily at 3 AM to delete old forecast records and resolved alerts.
 * Forecast records older than retention_days are removed.
 * Resolved alerts older than 30 days are removed.
 */
class Cleanup
{
    /** @var int Days after which resolved alerts are deleted */
    private const RESOLVED_ALERT_RETENTION_DAYS = 30;

    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection Database resource connection
     * @param Settings $settings Module configuration
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Settings $settings,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute cleanup of old forecast and alert records
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->settings->isEnabled()) {
            return;
        }

        $retentionDays = $this->settings->getRetentionDays();
        $forecastCutoff = (new \DateTime(
            "-{$retentionDays} days",
            new \DateTimeZone('UTC')
        ))->format('Y-m-d H:i:s');

        $alertCutoff = (new \DateTime(
            '-' . self::RESOLVED_ALERT_RETENTION_DAYS . ' days',
            new \DateTimeZone('UTC')
        ))->format('Y-m-d H:i:s');

        $this->logger->info(sprintf(
            '[STOCKPREDICT][CLEANUP] Starting cleanup. Forecast cutoff: %s, Alert cutoff: %s',
            $forecastCutoff,
            $alertCutoff
        ));

        try {
            $connection = $this->resourceConnection->getConnection();

            // Delete old forecast records
            $forecastDeleted = $connection->delete(
                $this->resourceConnection->getTableName('ai_stockpredict_forecast'),
                ['updated_at < ?' => $forecastCutoff]
            );

            // Delete resolved alerts older than 30 days
            $alertDeleted = $connection->delete(
                $this->resourceConnection->getTableName('ai_stockpredict_alert'),
                [
                    'created_at < ?' => $alertCutoff,
                    'status = ?'     => Alert::STATUS_RESOLVED,
                ]
            );

            $this->logger->info(sprintf(
                '[STOCKPREDICT][CLEANUP] Complete. Forecasts deleted: %d. Alerts deleted: %d.',
                $forecastDeleted,
                $alertDeleted
            ));

        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][CLEANUP] Cleanup failed: ' . $e->getMessage());
        }
    }
}
