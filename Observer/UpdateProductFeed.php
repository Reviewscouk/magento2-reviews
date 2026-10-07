<?php

namespace Reviewscouk\Reviews\Observer;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;
use Psr\Log\LoggerInterface;

class UpdateProductFeed implements Framework\Event\ObserverInterface
{

    private $apiModel;
    private $storeModel;
    private $messageManager;
    private $logger;

    public function __construct(
        Reviews\Model\Api $api,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Framework\Message\ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->apiModel = $api;
        $this->storeModel = $storeManagerInterface;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Sync the product feed URL and installed state with REVIEWS.io after a config save.
     *
     * The config is already committed when this event fires, so a failure here is
     * reported as an admin error message instead of being thrown back into the save.
     *
     * @param Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Framework\Event\Observer $observer)
    {
        try {
            // Resolve a concrete store view from the config-save scope before
            // touching getStore()/getBaseUrl(), so headless/multi-store installs
            // (admin host not a registered store view) don't blow up.
            $store = $this->resolveStore($observer->getEvent());
            $scopeId = $store->getId();
            $baseUrl = $store->getBaseUrl();
        } catch (\Throwable $e) {
            $this->reportFailure('Syncing Product Feed Configuration', $e);
            return;
        }

        $this->postToApi(
            'integration/set-feed',
            [
                'url' => $baseUrl . 'reviews/index/feed',
                'format' => 'xml'
            ],
            $scopeId,
            'Syncing Product Feed Configuration'
        );

        $this->postToApi(
            'integration/app-installed',
            [
                'platform' => 'magento',
                'url' => $baseUrl
            ],
            $scopeId,
            'Communication'
        );
    }

    /**
     * Post to the REVIEWS.io API and surface the outcome as an admin message.
     *
     * @param string $url
     * @param array $data
     * @param int|string $scopeId
     * @param string $task
     * @return void
     */
    protected function postToApi($url, array $data, $scopeId, $task)
    {
        try {
            $response = $this->apiModel->apiPost($url, $data, $scopeId);
            $this->apiModel->addStatusMessage($response, $task);
        } catch (\Throwable $e) {
            $this->reportFailure($task, $e);
        }
    }

    /**
     * Log a failed sync and show it to the admin without failing the config save.
     *
     * @param string $task
     * @param \Throwable $e
     * @return void
     */
    protected function reportFailure($task, \Throwable $e)
    {
        $this->logger->error('REVIEWS.io ' . $task . ' failed: ' . $e->getMessage(), ['exception' => $e]);
        $this->messageManager->addErrorMessage($task . ' Error: ' . $e->getMessage());
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
