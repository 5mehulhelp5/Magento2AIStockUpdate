<?php
/**
 * Forecast View Controller
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Controller\Adminhtml\Forecasts;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use AI\StockPredict\Model\ForecastFactory;
use AI\StockPredict\Model\ResourceModel\Forecast as ForecastResourceModel;

/**
 * Forecast detail view controller
 *
 * Loads the forecast by ID and renders the detail page.
 * Redirects to the forecasts grid if the ID is missing or not found.
 */
class View extends Action
{
    /** @var string ACL resource required to access this controller */
    public const ADMIN_RESOURCE = 'AI_StockPredict::forecasts';

    /**
     * Constructor
     *
     * @param Context $context Backend context
     * @param PageFactory $resultPageFactory Page result factory
     * @param ForecastFactory $forecastFactory Forecast model factory
     * @param ForecastResourceModel $forecastResource Forecast resource model
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ForecastFactory $forecastFactory,
        private readonly ForecastResourceModel $forecastResource
    ) {
        parent::__construct($context);
    }

    /**
     * Execute: load forecast and render detail page
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $forecastId = (int)$this->getRequest()->getParam('forecast_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$forecastId) {
            $this->messageManager->addErrorMessage(__('Invalid forecast ID.'));
            return $resultRedirect->setPath('stockpredict/forecasts/index');
        }

        $forecast = $this->forecastFactory->create();
        $this->forecastResource->load($forecast, $forecastId);

        if (!$forecast->getId()) {
            $this->messageManager->addErrorMessage(__('Forecast #%1 not found.', $forecastId));
            return $resultRedirect->setPath('stockpredict/forecasts/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('StockPredict'));
        $resultPage->getConfig()->getTitle()->prepend(__('Forecast: %1', $forecast->getData('sku')));

        return $resultPage;
    }
}
