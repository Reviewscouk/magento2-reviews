<?php

namespace Reviewscouk\Reviews\Observer;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;
use Reviewscouk\Reviews\Console\Command\GenerateFeedCommand;
use Reviewscouk\Reviews\Model\Feed\Generator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class UpdateProductFeed implements Framework\Event\ObserverInterface
{

    private $apiModel;
    private $storeModel;
    private $configHelper;
    private $generateFeedCommand;
    private $generator;

    public function __construct(
        Reviews\Model\Api $api,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Reviews\Helper\Config $configHelper,
        GenerateFeedCommand $generateFeedCommand,
        Generator $generator
    ) {
        $this->apiModel = $api;
        $this->storeModel = $storeManagerInterface;
        $this->configHelper = $configHelper;
        $this->generateFeedCommand = $generateFeedCommand;
        $this->generator = $generator;
    }

    public function execute(Framework\Event\Observer $observer)
    {
        // Resolve a concrete store view from the config-save scope before
        // touching getStore()/getBaseUrl(), so headless/multi-store installs
        // (admin host not a registered store view) don't blow up.
        $store = $this->resolveStore($observer->getEvent());
        $scopeId = $store->getId();
        $baseUrl = $store->getBaseUrl();
        $feedUrl = $baseUrl . 'reviews/index/feed';


        if ($this->configHelper->isProductFeedCronEnabled($scopeId)
            && !is_file($this->generator->getFinalPath($store))
        ) {
            set_time_limit(0);
            $this->generateFeedCommand->run(
                new ArrayInput(['--store' => $store->getCode()]),
                new NullOutput()
            );
        }

        $setFeed = $this->apiModel->apiPost(
            'integration/set-feed',
            [
                'url' => $feedUrl,
                'format' => 'xml'
            ],
            $scopeId
        );
        $this->apiModel->addStatusMessage($setFeed, "Syncing Product Feed Configuration");

        $appInstalled = $this->apiModel->apiPost(
            'integration/app-installed',
            [
                'platform' => 'magento',
                'url' => $baseUrl
            ],
            $scopeId
        );
        $this->apiModel->addStatusMessage($appInstalled, "Communication");

    }

    /**
     * Resolve a concrete store view from the config-save event scope.
     *
     * The admin_system_config_changed_section_* event carries 'store' and
     * 'website' scope identifiers, not a store getStore() can always resolve.
     * A store-scope save sets 'store'; a website-scope save sets 'website';
     * a default/global save sets neither. Turn whichever we get into a real
     * store view (falling back to the default store view) so callers get a
     * store that resolves without a NoSuchEntityException.
     *
     * @param Framework\Event $event
     * @return \Magento\Store\Api\Data\StoreInterface
     */
    protected function resolveStore(Framework\Event $event)
    {
        $storeParam = $event->getStore();
        if ($storeParam) {
            return $this->storeModel->getStore($storeParam);
        }

        $websiteParam = $event->getWebsite();
        if ($websiteParam) {
            return $this->storeModel->getWebsite($websiteParam)->getDefaultStore();
        }

        return $this->storeModel->getDefaultStoreView();
    }
}
