<?php
/**
 * Forecast Actions Column
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

/**
 * ForecastActions column
 *
 * Renders a "View" action link per forecast row in the admin grid.
 */
class ForecastActions extends Column
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
            if (isset($item['forecast_id'])) {
                $item[$name]['view'] = [
                    'href'  => $this->urlBuilder->getUrl(
                        'stockpredict/forecasts/view',
                        ['forecast_id' => $item['forecast_id']]
                    ),
                    'label' => __('View'),
                ];
            }
        }

        return $dataSource;
    }
}
