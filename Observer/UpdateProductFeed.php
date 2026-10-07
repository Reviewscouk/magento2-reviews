<?php

namespace Reviewscouk\Reviews\Observer;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Reviewscouk\Reviews\Model\Feed\Generator;

class UpdateProductFeed implements Framework\Event\ObserverInterface
{

    private $apiModel;
    private $storeModel;
    private $configHelper;
    private $scheduleFactory;
    private $scheduleCollectionFactory;
    private $generator;

    public function __construct(
        Reviews\Model\Api $api,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Reviews\Helper\Config $configHelper,
        ScheduleFactory $scheduleFactory,
        ScheduleCollectionFactory $scheduleCollectionFactory,
        Generator $generator
    ) {
        $this->apiModel = $api;
        $this->storeModel = $storeManagerInterface;
        $this->configHelper = $configHelper;
        $this->scheduleFactory = $scheduleFactory;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
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


        if ($this->configHelper->isProductFeedCronEnabled($scopeId) && !is_file($this->generator->getFinalPath($store))) {
            $this->queueFeedGeneration();
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

    private function queueFeedGeneration(): void
    {
        $jobCode = 'reviewscouk_generate_product_feed';

        $pending = $this->scheduleCollectionFactory->create()
            ->addFieldToFilter('job_code', $jobCode) // check job name
            ->addFieldToFilter('status', Schedule::STATUS_PENDING); // check if pendeding
        if ($pending->getSize() > 0) {
            return;
        }

        $now = gmdate('Y-m-d H:i:00');
        $this->scheduleFactory->create()
            ->setJobCode($jobCode)
            ->setStatus(Schedule::STATUS_PENDING)
            ->setCreatedAt($now)
            ->setScheduledAt($now)
            ->save();
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
