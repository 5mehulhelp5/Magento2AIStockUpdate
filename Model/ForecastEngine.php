<?php
/**
 * Forecast Engine
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use AI\StockPredict\Model\Ai\ClientFactory as AiClientFactory;
use AI\StockPredict\Model\ResourceModel\Alert as AlertResourceModel;
use AI\StockPredict\Model\ResourceModel\Alert\CollectionFactory as AlertCollectionFactory;
use AI\StockPredict\Model\ResourceModel\Forecast as ForecastResourceModel;
use AI\StockPredict\Model\ResourceModel\Forecast\CollectionFactory as ForecastCollectionFactory;
use AI\StockPredict\Model\ResourceModel\Run as RunResourceModel;
use Psr\Log\LoggerInterface;
use AI\StockPredict\Model\EmailNotifier;

/**
 * ForecastEngine
 *
 * Orchestrates the full inventory forecasting pipeline:
 * data collection → per-SKU analysis → AI recommendations → alert creation.
 */
class ForecastEngine
{
    /** @var string Event dispatched after a successful forecast run */
    public const EVENT_FORECAST_COMPLETE = 'ai_stockpredict_forecast_complete';

    /**
     * Constructor
     *
     * @param DataCollector $dataCollector Sales and inventory data provider
     * @param AiClientFactory $aiClientFactory AI client factory
     * @param Settings $settings Module configuration
     * @param ForecastFactory $forecastFactory Forecast model factory
     * @param AlertFactory $alertFactory Alert model factory
     * @param RunFactory $runFactory Run model factory
     * @param ForecastResourceModel $forecastResource Forecast resource model
     * @param AlertResourceModel $alertResource Alert resource model
     * @param RunResourceModel $runResource Run resource model
     * @param ForecastCollectionFactory $forecastCollectionFactory Forecast collection factory
     * @param AlertCollectionFactory $alertCollectionFactory Alert collection factory
     * @param EmailNotifier $emailNotifier Email notification service
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly DataCollector $dataCollector,
        private readonly AiClientFactory $aiClientFactory,
        private readonly Settings $settings,
        private readonly ForecastFactory $forecastFactory,
        private readonly AlertFactory $alertFactory,
        private readonly RunFactory $runFactory,
        private readonly ForecastResourceModel $forecastResource,
        private readonly AlertResourceModel $alertResource,
        private readonly RunResourceModel $runResource,
        private readonly ForecastCollectionFactory $forecastCollectionFactory,
        private readonly AlertCollectionFactory $alertCollectionFactory,
        private readonly EmailNotifier $emailNotifier,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute a full forecast run
     *
     * Creates a Run record, processes all top SKUs, creates alerts, and
     * records completion stats and duration. On fatal error the Run is
     * marked as failed with the exception message.
     *
     * @param string $triggeredBy Source of the trigger (cron|cli|admin)
     * @param string|null $skuFilter When set, analyze only this single SKU
     * @return Run The completed (or failed) Run model
     */
    public function run(string $triggeredBy = Run::TRIGGERED_CRON, ?string $skuFilter = null): Run
    {
        /** @var Run $run */
        $run = $this->runFactory->create();
        $run->setData([
            'status'       => Run::STATUS_RUNNING,
            'triggered_by' => $triggeredBy,
            'started_at'   => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->runResource->save($run);
        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][ENGINE] Failed to save Run record: ' . $e->getMessage());
        }

        $startTime = microtime(true);

        try {
            $this->logger->info(sprintf(
                '[STOCKPREDICT][ENGINE] Starting forecast run #%s triggered by %s',
                $run->getId(),
                $triggeredBy
            ));

            $mergedData = $this->collectData($skuFilter);

            $forecasts = [];
            $criticalCount = 0;
            $warningCount  = 0;
            $watchCount    = 0;

            foreach ($mergedData as $sku => $skuData) {
                try {
                    $forecast = $this->analyzeSku(
                        $sku,
                        $skuData['sales'] ?? [],
                        $skuData['stock'] ?? []
                    );
                    $forecasts[] = $forecast;

                    switch ($forecast->getData('severity')) {
                        case Forecast::SEVERITY_CRITICAL:
                            $criticalCount++;
                            break;
                        case Forecast::SEVERITY_WARNING:
                            $warningCount++;
                            break;
                        case Forecast::SEVERITY_WATCH:
                            $watchCount++;
                            break;
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf(
                        '[STOCKPREDICT][ENGINE] analyzeSku failed for %s: %s',
                        $sku,
                        $e->getMessage()
                    ));
                }
            }

            $this->createAlerts($forecasts);

            $durationMs = (int)round((microtime(true) - $startTime) * 1000);

            $run->setData('status', Run::STATUS_COMPLETED);
            $run->setData('skus_analyzed', count($mergedData));
            $run->setData('skus_critical', $criticalCount);
            $run->setData('skus_warning', $warningCount);
            $run->setData('skus_watch', $watchCount);
            $run->setData('duration_ms', $durationMs);
            $run->setData('completed_at', (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));

            $this->logger->info(sprintf(
                '[STOCKPREDICT][ENGINE] Run #%s completed. SKUs: %d, Critical: %d, Warning: %d, Watch: %d, Time: %dms',
                $run->getId(),
                count($mergedData),
                $criticalCount,
                $warningCount,
                $watchCount,
                $durationMs
            ));

        } catch (\Exception $e) {
            $durationMs = (int)round((microtime(true) - $startTime) * 1000);
            $this->logger->error('[STOCKPREDICT][ENGINE] Run failed: ' . $e->getMessage());
            $run->setData('status', Run::STATUS_FAILED);
            $run->setData('error_message', $e->getMessage());
            $run->setData('duration_ms', $durationMs);
            $run->setData('completed_at', (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'));
        }

        try {
            $this->runResource->save($run);
        } catch (\Exception $e) {
            $this->logger->error('[STOCKPREDICT][ENGINE] Failed to update Run record: ' . $e->getMessage());
        }

        return $run;
    }

    /**
     * Collect merged sales and stock data for top SKUs
     *
     * @param string|null $skuFilter When set, analyze only this single SKU (skips volume ranking and min-order check)
     * @return array Keyed by SKU: ['sales' => [...], 'stock' => [...]]
     */
    public function collectData(?string $skuFilter = null): array
    {
        $historyDays = $this->settings->getHistoryDays();

        if ($skuFilter !== null) {
            $skus = [$skuFilter];
        } else {
            $skus = $this->dataCollector->getTopSkusByVolume(500, $historyDays);
        }

        if (empty($skus)) {
            $this->logger->info('[STOCKPREDICT][ENGINE] No SKUs found in sales history');
            return [];
        }

        $salesData = $this->dataCollector->getSalesData($skus, $historyDays);
        $stockData = $this->dataCollector->getCurrentStock($skus);

        $merged = [];
        foreach ($skus as $sku) {
            if (!isset($salesData[$sku])) {
                continue;
            }
            // Skip min-order threshold only for full runs (not single-SKU)
            if ($skuFilter === null) {
                $orders = $salesData[$sku]['orders_count'] ?? 0;
                if ($orders < $this->settings->getMinOrderThreshold()) {
                    continue;
                }
            }
            $merged[$sku] = [
                'sales' => $salesData[$sku],
                'stock' => $stockData[$sku] ?? ['qty' => 0.0, 'is_in_stock' => false],
            ];
        }

        return $merged;
    }

    /**
     * Analyze a single SKU and persist/update its Forecast record
     *
     * Calculates avg daily sales, projects stockout date, determines severity,
     * optionally fetches AI recommendations, and saves the Forecast.
     *
     * @param string $sku Product SKU
     * @param array $salesData Sales metrics for this SKU
     * @param array $stockData Current stock data for this SKU
     * @return Forecast The saved Forecast model
     */
    public function analyzeSku(string $sku, array $salesData, array $stockData): Forecast
    {
        $currentQty   = (float)($stockData['qty'] ?? 0.0);
        $avgDaily     = (float)($salesData['avg_daily'] ?? 0.0);
        $last30d      = (int)($salesData['last_30d'] ?? 0);
        $last90d      = (int)($salesData['last_90d'] ?? 0);
        $last365d     = (int)($salesData['last_365d'] ?? 0);
        $productName  = (string)($salesData['product_name'] ?? '');

        // Calculate projected demand
        $demand7d  = round($avgDaily * 7, 4);
        $demand30d = round($avgDaily * 30, 4);
        $demand90d = round($avgDaily * 90, 4);

        // Determine trend by comparing recent 30d rate vs 90d rate
        $avg30dRate = $last30d > 0 ? $last30d / 30.0 : 0.0;
        $avg90dRate = $last90d > 0 ? $last90d / 90.0 : 0.0;

        if ($avg90dRate > 0 && $avg30dRate > $avg90dRate * 1.15) {
            $trend = Forecast::TREND_RISING;
        } elseif ($avg90dRate > 0 && $avg30dRate < $avg90dRate * 0.85) {
            $trend = Forecast::TREND_DECLINING;
        } else {
            $trend = Forecast::TREND_STABLE;
        }

        // Calculate stockout date
        $daysUntilStockout = null;
        $stockoutDate      = null;

        if ($avgDaily > 0 && $currentQty >= 0) {
            $daysUntilStockout = (int)floor($currentQty / $avgDaily);
            $stockoutDateTime  = new \DateTime('now', new \DateTimeZone('UTC'));
            $stockoutDateTime->modify("+{$daysUntilStockout} days");
            $stockoutDate = $stockoutDateTime->format('Y-m-d');
        }

        $severity = $this->calculateSeverity($daysUntilStockout);

        // Confidence: based on how much data we have (more orders = higher confidence)
        $ordersCount = (int)($salesData['orders_count'] ?? 0);
        $confidence  = min(1.0, round($ordersCount / 100.0, 2));

        // Recommended reorder quantity: 2x monthly demand
        $recommendedReorderQty = round($demand30d * 2, 4);

        // AI recommendation if enabled
        $aiRecommendation = null;
        $aiForecast       = [];

        if ($this->settings->isAiEnabled()) {
            $aiForecast = $this->getAiRecommendation([
                'sku'          => $sku,
                'product_name' => $productName,
                'current_qty'  => $currentQty,
                'avg_daily'    => $avgDaily,
                'last_30d'     => $last30d,
                'last_90d'     => $last90d,
                'trend'        => $trend,
            ]);

            if (!empty($aiForecast)) {
                if (isset($aiForecast['recommendation'])) {
                    $aiRecommendation = (string)$aiForecast['recommendation'];
                }
                if (isset($aiForecast['forecast_30d']) && (int)$aiForecast['forecast_30d'] > 0) {
                    $demand30d = (float)$aiForecast['forecast_30d'];
                }
                if (isset($aiForecast['trend'])) {
                    $trend = (string)$aiForecast['trend'];
                }
                if (isset($aiForecast['confidence'])) {
                    $confidence = min(1.0, (float)$aiForecast['confidence']);
                }
            }
        }

        return $this->upsertForecast([
            'sku'                      => $sku,
            'product_name'             => $productName,
            'current_qty'              => $currentQty,
            'avg_daily_sales'          => $avgDaily,
            'forecast_demand_7d'       => $demand7d,
            'forecast_demand_30d'      => $demand30d,
            'forecast_demand_90d'      => $demand90d,
            'trend'                    => $trend,
            'confidence_score'         => $confidence,
            'predicted_stockout_date'  => $stockoutDate,
            'days_until_stockout'      => $daysUntilStockout,
            'recommended_reorder_qty'  => $recommendedReorderQty,
            'ai_recommendation'        => $aiRecommendation,
            'severity'                 => $severity,
            'last_30d_sales'           => $last30d,
            'last_90d_sales'           => $last90d,
            'last_365d_sales'          => $last365d,
        ]);
    }

    /**
     * Determine alert severity based on days until stockout
     *
     * Uses configured thresholds from Settings. If daysUntilStockout is null
     * (no avg sales data), returns ok.
     *
     * @param int|null $daysUntilStockout Days until predicted stockout
     * @return string Severity constant: critical|warning|watch|ok
     */
    public function calculateSeverity(?int $daysUntilStockout): string
    {
        if ($daysUntilStockout === null) {
            return Forecast::SEVERITY_OK;
        }

        $critical = $this->settings->getCriticalDays();
        $warning  = $this->settings->getWarningDays();
        $watch    = $this->settings->getWatchDays();

        if ($daysUntilStockout <= $critical) {
            return Forecast::SEVERITY_CRITICAL;
        }

        if ($daysUntilStockout <= $warning) {
            return Forecast::SEVERITY_WARNING;
        }

        if ($daysUntilStockout <= $watch) {
            return Forecast::SEVERITY_WATCH;
        }

        return Forecast::SEVERITY_OK;
    }

    /**
     * Get AI-generated demand forecast and reorder recommendation for a SKU
     *
     * Builds a structured prompt, calls the configured AI provider, parses
     * the JSON response. Returns empty array on any error — never blocks.
     *
     * @param array $skuData Associative array with sku context data
     * @return array Parsed AI response: [forecast_30d, trend, confidence, recommendation]
     */
    public function getAiRecommendation(array $skuData): array
    {
        try {
            $prompt = sprintf(
                'Given SKU "%s" (product: %s) with current stock %s units, '
                . 'avg daily sales %s units, last 30d sold %s, last 90d sold %s, trend %s. '
                . 'Forecast demand for next 30 days and recommend reorder quantity. '
                . 'Respond in JSON only with this exact structure: '
                . '{"forecast_30d": integer, "trend": "rising|stable|declining", '
                . '"confidence": float between 0 and 1, '
                . '"recommendation": "string max 100 chars"}',
                $skuData['sku'],
                $skuData['product_name'],
                number_format((float)$skuData['current_qty'], 2),
                number_format((float)$skuData['avg_daily'], 4),
                (int)$skuData['last_30d'],
                (int)$skuData['last_90d'],
                $skuData['trend']
            );

            $rawResponse = $this->aiClientFactory->create()->analyze($prompt);

            if (empty($rawResponse)) {
                return [];
            }

            // Strip markdown code fences if present
            $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $rawResponse);
            $cleaned = preg_replace('/\s*```$/m', '', (string)$cleaned);
            $cleaned = trim((string)$cleaned);

            $decoded = json_decode($cleaned, true);

            if (!is_array($decoded)) {
                $this->logger->warning(sprintf(
                    '[STOCKPREDICT][ENGINE] AI response for SKU %s is not valid JSON: %s',
                    $skuData['sku'],
                    substr($rawResponse, 0, 200)
                ));
                return [];
            }

            return $decoded;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[STOCKPREDICT][ENGINE] getAiRecommendation failed for SKU %s: %s',
                $skuData['sku'] ?? 'unknown',
                $e->getMessage()
            ));
            return [];
        }
    }

    /**
     * Create alert records for forecasts with non-OK severity
     *
     * Skips SKUs that already have an open alert to avoid duplicates.
     *
     * @param Forecast[] $forecasts Array of Forecast models
     * @return void
     */
    public function createAlerts(array $forecasts): void
    {
        foreach ($forecasts as $forecast) {
            $severity = (string)$forecast->getData('severity');

            if ($severity === Forecast::SEVERITY_OK) {
                continue;
            }

            $sku = (string)$forecast->getData('sku');

            try {
                // Check for existing open alert for this SKU
                $existingCollection = $this->alertCollectionFactory->create();
                $existingCollection->addFieldToFilter('sku', $sku);
                $existingCollection->addFieldToFilter('status', Alert::STATUS_OPEN);
                $existingCollection->setPageSize(1);

                if ($existingCollection->getSize() > 0) {
                    continue;
                }

                /** @var Alert $alert */
                $alert = $this->alertFactory->create();
                $alert->setData([
                    'forecast_id'       => $forecast->getId(),
                    'sku'               => $sku,
                    'product_name'      => (string)$forecast->getData('product_name'),
                    'severity'          => $severity,
                    'days_until_stockout' => $forecast->getData('days_until_stockout'),
                    'current_qty'       => (float)$forecast->getData('current_qty'),
                    'status'            => Alert::STATUS_OPEN,
                ]);

                $this->alertResource->save($alert);

                $this->logger->info(sprintf(
                    '[STOCKPREDICT][ENGINE] Created %s alert for SKU %s (days until stockout: %s)',
                    $severity,
                    $sku,
                    $forecast->getData('days_until_stockout') ?? 'unknown'
                ));

                $this->emailNotifier->sendAlert($alert);

            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    '[STOCKPREDICT][ENGINE] Failed to create alert for SKU %s: %s',
                    $sku,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Upsert a Forecast record by SKU — update existing or create new
     *
     * @param array $data Forecast field data
     * @return Forecast The saved Forecast model
     */
    public function upsertForecast(array $data): Forecast
    {
        try {
            $collection = $this->forecastCollectionFactory->create();
            $collection->addFieldToFilter('sku', $data['sku']);
            $collection->setPageSize(1);

            /** @var Forecast $forecast */
            $forecast = $collection->getFirstItem();

            if (!$forecast->getId()) {
                $forecast = $this->forecastFactory->create();
            }

            $forecast->addData($data);
            $this->forecastResource->save($forecast);

            return $forecast;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[STOCKPREDICT][ENGINE] upsertForecast failed for SKU %s: %s',
                $data['sku'] ?? 'unknown',
                $e->getMessage()
            ));
            throw $e;
        }
    }
}
