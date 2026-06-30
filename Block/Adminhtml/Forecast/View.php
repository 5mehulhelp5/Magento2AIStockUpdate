<?php
/**
 * Forecast Detail View Block
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Block\Adminhtml\Forecast;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use AI\StockPredict\Model\Forecast;
use AI\StockPredict\Model\ForecastFactory;
use AI\StockPredict\Model\ResourceModel\Forecast as ForecastResourceModel;

/**
 * Forecast detail block
 *
 * Loads a single Forecast model by the forecast_id request parameter
 * and provides it to the view template.
 */
class View extends Template
{
    /** @var Forecast|null Memoized forecast model */
    private ?Forecast $forecast = null;

    /**
     * Constructor
     *
     * @param Context $context Block context
     * @param ForecastFactory $forecastFactory Forecast model factory
     * @param ForecastResourceModel $forecastResource Forecast resource model
     * @param array $data Additional data
     */
    public function __construct(
        Context $context,
        private readonly ForecastFactory $forecastFactory,
        private readonly ForecastResourceModel $forecastResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the forecast model for the current request
     *
     * @return Forecast|null Forecast model or null if not found
     */
    public function getForecast(): ?Forecast
    {
        if ($this->forecast === null) {
            $forecastId = (int)$this->getRequest()->getParam('forecast_id');
            if ($forecastId) {
                $forecast = $this->forecastFactory->create();
                $this->forecastResource->load($forecast, $forecastId);
                $this->forecast = $forecast->getId() ? $forecast : null;
            }
        }
        return $this->forecast;
    }

    /**
     * Get back URL for the forecasts grid
     *
     * @return string Forecasts grid URL
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('stockpredict/forecasts/index');
    }
}
