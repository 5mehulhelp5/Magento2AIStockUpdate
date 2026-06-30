<?php
/**
 * Forecasts List Controller
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

/**
 * StockPredict Forecasts list controller
 *
 * Renders the forecast grid UI component.
 */
class Index extends Action
{
    /** @var string ACL resource required to access this controller */
    public const ADMIN_RESOURCE = 'AI_StockPredict::forecasts';

    /**
     * Constructor
     *
     * @param Context $context Backend context
     * @param PageFactory $pageFactory Page result factory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute controller action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('AI_StockPredict::forecasts');
        $page->getConfig()->getTitle()->prepend(__('StockPredict - Forecasts'));
        return $page;
    }
}
