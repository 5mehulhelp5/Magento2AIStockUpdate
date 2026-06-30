<?php
/**
 * Alerts Acknowledge Controller (grid action — redirect)
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Controller\Adminhtml\Alerts;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use AI\StockPredict\Model\Alert;
use AI\StockPredict\Model\AlertFactory;
use AI\StockPredict\Model\ResourceModel\Alert as AlertResourceModel;

/**
 * Acknowledge an alert from the grid — sets status to acknowledged and redirects back.
 */
class Acknowledge extends Action
{
    /** @var string ACL resource required to access this controller */
    public const ADMIN_RESOURCE = 'AI_StockPredict::alerts';

    /**
     * Constructor
     *
     * @param Context $context Backend context
     * @param AlertFactory $alertFactory Alert model factory
     * @param AlertResourceModel $alertResource Alert resource model
     */
    public function __construct(
        Context $context,
        private readonly AlertFactory $alertFactory,
        private readonly AlertResourceModel $alertResource
    ) {
        parent::__construct($context);
    }

    /**
     * Execute: load alert, set acknowledged, redirect to alerts grid
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $alertId = (int)$this->getRequest()->getParam('alert_id');

        if (!$alertId) {
            $this->messageManager->addErrorMessage(__('Invalid alert ID.'));
            return $resultRedirect->setPath('stockpredict/alerts/index');
        }

        try {
            /** @var Alert $alert */
            $alert = $this->alertFactory->create();
            $this->alertResource->load($alert, $alertId);

            if (!$alert->getId()) {
                $this->messageManager->addErrorMessage(__('Alert #%1 not found.', $alertId));
                return $resultRedirect->setPath('stockpredict/alerts/index');
            }

            if ($alert->getData('status') !== Alert::STATUS_OPEN) {
                $this->messageManager->addNoticeMessage(
                    __('Alert #%1 is already %2.', $alertId, $alert->getData('status'))
                );
                return $resultRedirect->setPath('stockpredict/alerts/index');
            }

            $alert->setData('status', Alert::STATUS_ACKNOWLEDGED);
            $this->alertResource->save($alert);

            $this->messageManager->addSuccessMessage(
                __('Alert #%1 (%2) has been acknowledged.', $alertId, $alert->getData('sku'))
            );

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Could not acknowledge alert: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('stockpredict/alerts/index');
    }
}
