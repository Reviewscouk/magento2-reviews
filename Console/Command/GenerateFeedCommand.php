<?php

namespace Reviewscouk\Reviews\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Reviewscouk\Reviews\Model\Feed\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento reviewscouk:feed:generate
 *
 * Builds the REVIEWS.io product feed as a static file, off the request
 * lifecycle, so generating it for a large catalog isn't bound by PHP-FPM /
 * nginx request timeouts.
 */
class GenerateFeedCommand extends Command
{
    private $generator;
    private $storeManager;
    private $appState;

    public function __construct(
        Generator $generator,
        StoreManagerInterface $storeManager,
        State $appState
    ) {
        $this->generator = $generator;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('reviewscouk:feed:generate');
        $this->setDescription('Generate the REVIEWS.io product feed as a static XML file');
        $this->addOption(
            'store',
            null,
            InputOption::VALUE_OPTIONAL,
            'Store code to generate the feed for (default: all active stores)'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set - fine, nothing to do.
        }

        $storeCode = $input->getOption('store');

        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        foreach ($stores as $store) {
            $output->writeln(sprintf('Generating feed for store "%s"...', $store->getCode()));
            try {
                $path = $this->generator->generate($store);
                $output->writeln(sprintf('  -> wrote %s', $path));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('  -> skipped: %s', $e->getMessage()));
            }
        }

        return 0;
    }
}
