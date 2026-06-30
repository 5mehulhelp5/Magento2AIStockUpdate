<?php
/**
 * Alert Actions Column
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use AI\StockPredict\Model\Alert;

/**
 * AlertActions column
 *
 * Renders Acknowledge and Resolve action links per alert row in the admin grid.
 * Actions shown depend on the current alert status.
 */
class AlertActions extends Column
{
    /**
     * Constructor
     *
     * @param ContextInterface $context UI component context
     * @param UiComponentFactory $uiComponentFactory UI component factory
     * @param UrlInterface $urlBuilder URL builder
     * @param array $components Child components
     * @param array $data Component data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare data source with action URLs per row
     *
     * Open alerts: Acknowledge + Resolve
     * Acknowledged alerts: Resolve only
     * Resolved alerts: no actions
     *
     * @param array $dataSource Data source array
     * @return array Modified data source with actions
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['alert_id'])) {
                continue;
            }

            $status = (string)($item['status'] ?? '');

            if ($status === Alert::STATUS_OPEN) {
                $item[$name]['acknowledge'] = [
                    'href'  => $this->urlBuilder->getUrl(
                        'stockpredict/alerts/acknowledge',
                        ['alert_id' => $item['alert_id']]
                    ),
                    'label' => __('Acknowledge'),
                ];
            }

            if (in_array($status, [Alert::STATUS_OPEN, Alert::STATUS_ACKNOWLEDGED], true)) {
                $item[$name]['resolve'] = [
                    'href'  => $this->urlBuilder->getUrl(
                        'stockpredict/alerts/resolve',
                        ['alert_id' => $item['alert_id']]
                    ),
                    'label' => __('Resolve'),
                ];
            }
        }

        return $dataSource;
    }
}
