<?php
/**
 * Run Forecast Cron Job
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Cron;

use AI\StockPredict\Model\ForecastEngine;
use AI\StockPredict\Model\Run;
use AI\StockPredict\Model\Settings;
use Psr\Log\LoggerInterface;

/**
 * RunForecast Cron
 *
 * Scheduled daily at 2 AM to run the full inventory demand forecast.
 * Skips execution if the module is disabled.
 */
class RunForecast
{
    /**
     * Constructor
     *
     * @param ForecastEngine $forecastEngine Forecast orchestration engine
     * @param Settings $settings Module configuration
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly ForecastEngine $forecastEngine,
        private readonly Settings $settings,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->settings->isEnabled()) {
            return;
        }

        try {
            $this->logger->info('[STOCKPREDICT][CRON] Starting scheduled forecast run');
            $run = $this->forecastEngine->run(Run::TRIGGERED_CRON);
            $this->logger->info(sprintf(
                '[STOCKPREDICT][CRON] Forecast run #%s completed with status: %s',
                $run->getId(),
                $run->getData('status')
            ));
        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][CRON] Forecast run failed: ' . $e->getMessage());
        }
    }
}
