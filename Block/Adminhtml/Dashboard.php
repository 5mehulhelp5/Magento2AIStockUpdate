<?php
/**
 * Dashboard Block
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use AI\StockPredict\Model\Alert;
use AI\StockPredict\Model\Forecast;
use AI\StockPredict\Model\ResourceModel\Alert\CollectionFactory as AlertCollectionFactory;
use AI\StockPredict\Model\ResourceModel\Forecast\CollectionFactory as ForecastCollectionFactory;
use AI\StockPredict\Model\ResourceModel\Run\CollectionFactory as RunCollectionFactory;

/**
 * Dashboard block — provides data for the admin dashboard template
 *
 * All collection queries are memoized per request so that calling a method
 * multiple times from the template never issues more than one DB query.
 */
class Dashboard extends Template
{
    /** @var array|null Memoized summary stats */
    private ?array $summaryStats = null;

    /**
     * Constructor
     *
     * @param Context $context Block context
     * @param ForecastCollectionFactory $forecastCollectionFactory Forecast collection factory
     * @param AlertCollectionFactory $alertCollectionFactory Alert collection factory
     * @param RunCollectionFactory $runCollectionFactory Run collection factory
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        private readonly ForecastCollectionFactory $forecastCollectionFactory,
        private readonly AlertCollectionFactory $alertCollectionFactory,
        private readonly RunCollectionFactory $runCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get top critical/warning forecasts for the dashboard table (max 10)
     *
     * Ordered by days_until_stockout ascending so most urgent appear first.
     *
     * @return \AI\StockPredict\Model\ResourceModel\Forecast\Collection
     */
    public function getCriticalForecasts()
    {
        $collection = $this->forecastCollectionFactory->create();
        $collection->addFieldToFilter(
            'severity',
            ['in' => [Forecast::SEVERITY_CRITICAL, Forecast::SEVERITY_WARNING]]
        );
        $collection->setOrder('days_until_stockout', 'ASC');
        $collection->setPageSize(10);
        return $collection;
    }

    /**
     * Get summary statistics for the dashboard cards
     *
     * Returns associative array with keys: total, critical, warning, watch.
     * Results are memoized after first call.
     *
     * @return array Summary stats
     */
    public function getSummaryStats(): array
    {
        if ($this->summaryStats === null) {
            $total    = $this->forecastCollectionFactory->create()->getSize();
            $critical = $this->forecastCollectionFactory->create()
                ->addFieldToFilter('severity', Forecast::SEVERITY_CRITICAL)
                ->getSize();
            $warning  = $this->forecastCollectionFactory->create()
                ->addFieldToFilter('severity', Forecast::SEVERITY_WARNING)
                ->getSize();
            $watch    = $this->forecastCollectionFactory->create()
                ->addFieldToFilter('severity', Forecast::SEVERITY_WATCH)
                ->getSize();

            $this->summaryStats = [
                'total'    => $total,
                'critical' => $critical,
                'warning'  => $warning,
                'watch'    => $watch,
            ];
        }

        return $this->summaryStats;
    }

    /**
     * Get the most recent forecast run record
     *
     * @return \AI\StockPredict\Model\Run|null Most recent Run or null if none exist
     */
    public function getLastRun()
    {
        $collection = $this->runCollectionFactory->create();
        $collection->setOrder('run_id', 'DESC');
        $collection->setPageSize(1);

        $run = $collection->getFirstItem();
        return $run->getId() ? $run : null;
    }

    /**
     * Get the URL for the forecasts grid page
     *
     * @return string Forecasts index URL
     */
    public function getForecastsUrl(): string
    {
        return $this->getUrl('stockpredict/forecasts/index');
    }

    /**
     * Get count of open (unacknowledged) alerts
     *
     * @return int Open alert count
     */
    public function getOpenAlertsCount(): int
    {
        return $this->alertCollectionFactory->create()
            ->addFieldToFilter('status', Alert::STATUS_OPEN)
            ->getSize();
    }

    /**
     * Get the URL for the alerts page
     *
     * @return string Alerts index URL
     */
    public function getAlertsUrl(): string
    {
        return $this->getUrl('stockpredict/alerts/index');
    }
}
