<?php
/**
 * Forecast CLI Command
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use AI\StockPredict\Model\ForecastEngine;
use AI\StockPredict\Model\Run;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * StockPredict Forecast CLI Command
 *
 * Usage: bin/magento ai:stockpredict:forecast [--sku=SKU-CODE] [--triggered-by=cli]
 * Triggers a demand forecast run and outputs the summary statistics.
 */
class ForecastCommand extends Command
{
    /** @var string CLI option name for single SKU targeting */
    private const OPTION_SKU = 'sku';

    /** @var string CLI option name for triggered-by source override */
    private const OPTION_TRIGGERED_BY = 'triggered-by';

    /**
     * Constructor
     *
     * @param ForecastEngine $forecastEngine Forecast orchestration engine
     * @param State $appState Magento application state
     */
    public function __construct(
        private readonly ForecastEngine $forecastEngine,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    /**
     * Configure command name, description, and options
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('ai:stockpredict:forecast');
        $this->setDescription('Run StockPredict inventory demand forecast');
        $this->addOption(
            self::OPTION_SKU,
            null,
            InputOption::VALUE_OPTIONAL,
            'Analyze a single SKU only (optional)'
        );
        $this->addOption(
            self::OPTION_TRIGGERED_BY,
            null,
            InputOption::VALUE_OPTIONAL,
            'Override triggered-by value (default: cli)',
            Run::TRIGGERED_CLI
        );
    }

    /**
     * Execute command: run forecast and output summary
     *
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
            // Area already set — safe to ignore
        }

        $triggeredBy = (string)($input->getOption(self::OPTION_TRIGGERED_BY) ?: Run::TRIGGERED_CLI);
        $skuFilter   = $input->getOption(self::OPTION_SKU) ? (string)$input->getOption(self::OPTION_SKU) : null;

        $output->writeln('');
        $output->writeln('<info>=== StockPredict - Inventory Forecast ===</info>');
        if ($skuFilter !== null) {
            $output->writeln(sprintf('<comment>Mode:</comment>        Single SKU — %s', $skuFilter));
        }
        $output->writeln('');

        try {
            $run = $this->forecastEngine->run($triggeredBy, $skuFilter);

            $statusColor = $run->getData('status') === Run::STATUS_COMPLETED ? 'info' : 'error';

            $output->writeln(sprintf(
                '<comment>Run ID:</comment>      #%s',
                $run->getId()
            ));
            $output->writeln(sprintf(
                '<comment>Status:</comment>      <%1$s>%2$s</%1$s>',
                $statusColor,
                strtoupper((string)$run->getData('status'))
            ));
            $output->writeln(sprintf(
                '<comment>SKUs Analyzed:</comment> %d',
                (int)$run->getData('skus_analyzed')
            ));
            $output->writeln(sprintf(
                '<comment>Critical:</comment>    <error>%d</error>',
                (int)$run->getData('skus_critical')
            ));
            $output->writeln(sprintf(
                '<comment>Warning:</comment>     %d',
                (int)$run->getData('skus_warning')
            ));
            $output->writeln(sprintf(
                '<comment>Watch:</comment>       %d',
                (int)$run->getData('skus_watch')
            ));
            $output->writeln(sprintf(
                '<comment>Duration:</comment>    %dms',
                (int)$run->getData('duration_ms')
            ));

            if ($run->getData('error_message')) {
                $output->writeln('');
                $output->writeln('<error>Error: ' . $run->getData('error_message') . '</error>');
                return Command::FAILURE;
            }

            $output->writeln('');
            $output->writeln('<info>Forecast complete.</info>');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Fatal error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
