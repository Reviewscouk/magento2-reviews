<?php

namespace Reviewscouk\Reviews\Observer;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;

class UpdateProductFeed implements Framework\Event\ObserverInterface
{

    private $apiModel;
    private $storeModel;

    public function __construct(
        Reviews\Model\Api $api,
        Store\Model\StoreManagerInterface $storeManagerInterface
    ) {
        $this->apiModel = $api;
        $this->storeModel = $storeManagerInterface;
    }

    public function execute(Framework\Event\Observer $observer)
    {
        // Get current website scope
        $scopeId = $observer->getEvent()->getWebsite() ?? null;

        $setFeed = $this->apiModel->apiPost(
            'integration/set-feed',
            [
                'url' => $this->storeModel->getStore()->getBaseUrl() . 'reviews/index/feed',
                'format' => 'xml'
            ],
            $scopeId
        );
        $this->apiModel->addStatusMessage($setFeed, "Syncing Product Feed Configuration");

        $appInstalled = $this->apiModel->apiPost(
            'integration/app-installed',
            [
                'platform' => 'magento',
                'url' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
            ],
            $scopeId
        );
        $this->apiModel->addStatusMessage($appInstalled, "Communication");

    }
}
