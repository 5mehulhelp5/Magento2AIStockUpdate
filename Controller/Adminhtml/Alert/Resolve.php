<?php
/**
 * Alert Resolve AJAX Controller
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Controller\Adminhtml\Alert;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use AI\StockPredict\Model\Alert;
use AI\StockPredict\Model\AlertFactory;
use AI\StockPredict\Model\ResourceModel\Alert as AlertResourceModel;

/**
 * Alert Resolve AJAX controller
 *
 * Accepts a POST request with alert_id and sets the alert status to resolved.
 * Returns JSON response with success/message.
 */
class Resolve extends Action
{
    /** @var string ACL resource required to access this controller */
    public const ADMIN_RESOURCE = 'AI_StockPredict::alerts';

    /**
     * Constructor
     *
     * @param Context $context Backend context
     * @param JsonFactory $jsonFactory JSON result factory
     * @param AlertFactory $alertFactory Alert model factory
     * @param AlertResourceModel $alertResource Alert resource model
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly AlertFactory $alertFactory,
        private readonly AlertResourceModel $alertResource
    ) {
        parent::__construct($context);
    }

    /**
     * Execute AJAX resolve action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result  = $this->jsonFactory->create();
        $alertId = (int)$this->getRequest()->getParam('alert_id');

        if (!$alertId) {
            return $result->setData(['success' => false, 'message' => 'Missing alert_id parameter']);
        }

        try {
            /** @var Alert $alert */
            $alert = $this->alertFactory->create();
            $this->alertResource->load($alert, $alertId);

            if (!$alert->getId()) {
                return $result->setData(['success' => false, 'message' => 'Alert not found']);
            }

            $alert->setData('status', Alert::STATUS_RESOLVED);
            $this->alertResource->save($alert);

            return $result->setData([
                'success' => true,
                'message' => sprintf('Alert #%d resolved successfully', $alertId),
            ]);

        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
