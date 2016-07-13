<?php

namespace Reviewscouk\Reviews\Observer;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;

class UpdateProductFeed implements Framework\Event\ObserverInterface
{

    private $_apiModel;
    private $_storeModel;

    public function __construct(Reviews\Model\Api $api,
                                Store\Model\StoreManagerInterface $storeManagerInterface)
    {

        $this->_apiModel = $api;
        $this->_storeModel = $storeManagerInterface;

    }

    public function execute(Framework\Event\Observer $observer)
    {
        $setFeed = $this->_apiModel->apiPost('integration/set-feed', array(
            'url' => $this->_storeModel->getStore()->getBaseUrl() . 'reviews/index/feed',
            'format' => 'xml'
        ));
        $this->_apiModel->addStatusMessage($setFeed, "Syncing Product Feed Configuration");

        $appInstalled = $this->_apiModel->apiPost('integration/app-installed', array(
            'platform' => 'magento',
            'url' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        ));
        $this->_apiModel->addStatusMessage($appInstalled, "Communication");

    }


}