<?php
/**
 * Forecast Model
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Forecast model representing a per-SKU demand forecast record
 *
 * Stores calculated demand forecasts, stockout predictions, and AI recommendations
 * for a single product SKU.
 */
class Forecast extends AbstractModel
{
    /** @var string Critical severity — stockout predicted within critical_days */
    public const SEVERITY_CRITICAL = 'critical';

    /** @var string Warning severity — stockout predicted within warning_days */
    public const SEVERITY_WARNING = 'warning';

    /** @var string Watch severity — stockout predicted within watch_days */
    public const SEVERITY_WATCH = 'watch';

    /** @var string OK severity — sufficient stock, no immediate concern */
    public const SEVERITY_OK = 'ok';

    /** @var string Rising sales trend */
    public const TREND_RISING = 'rising';

    /** @var string Stable sales trend */
    public const TREND_STABLE = 'stable';

    /** @var string Declining sales trend */
    public const TREND_DECLINING = 'declining';

    /**
     * Initialize model with resource model binding
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\AI\StockPredict\Model\ResourceModel\Forecast::class);
    }
}
