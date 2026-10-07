<?php

namespace Reviewscouk\Reviews\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Feed\Generator;

/**
 * Regenerates the REVIEWS.io product feed static file for every store on a
 * schedule, so the feed stays fresh without relying on the console command
 * or an on-demand request.
 */
class GenerateFeed
{
    private $generator;
    private $storeManager;
    private $configHelper;
    private $logger;

    public function __construct(
        Generator $generator,
        StoreManagerInterface $storeManager,
        Config $configHelper,
        LoggerInterface $logger
    ) {
        $this->generator = $generator;
        $this->storeManager = $storeManager;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            if (!$this->configHelper->isProductFeedEnabled($store->getId())
                || !$this->configHelper->isProductFeedCronEnabled($store->getId())
            ) {
                continue;
            }
            try {
                $this->generator->generate($store);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'REVIEWS.io feed generation skipped for store "%s": %s',
                    $store->getCode(),
                    $e->getMessage()
                ));
            }
        }
    }
}
