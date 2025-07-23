<?php

namespace Reviewscouk\Reviews\Model;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;
use Magento\Store as Store;

class Api extends Framework\Model\AbstractModel
{

    private $configHelper;
    private $messageInterface;
    private $store;

    public function __construct(
        Reviews\Helper\Config $config,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Framework\Message\ManagerInterface $managerInterface
    ) {

        $this->configHelper = $config;
        $this->messageInterface = $managerInterface;

        $this->store = $storeManagerInterface->getStore();
    }

    public function apiPost($url, $data, ?$magento_store_id = null)
    {
        if ($magento_store_id == null) {
            $magento_store_id = $this->store->getId();
        }

        $api_url = 'https://' . $this->getApiDomain($magento_store_id) . '/' . $url;
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'store: ' . $this->configHelper->getStoreId($magento_store_id),
                'apikey: ' . $this->configHelper->getApiKey($magento_store_id),
                'Content-Type: application/json'
            ]
        );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    protected function getApiDomain(?$magento_store_id = null)
    {
        return $this->configHelper->getRegion($magento_store_id) == 'US' ? 'api.reviews.io' : 'api.reviews.co.uk';
    }

    public function addStatusMessage($object, $task)
    {
        $object = json_decode($object);

        if (isset($object->status) && $object->status == 'error') {
            $this->messageInterface->addError($task . ' Error: ' . $object->message);
        }
    }
}
