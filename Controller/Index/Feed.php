<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Feed\Generator;
class Feed implements HttpGetActionInterface
{
    private $configHelper;
    private $storeModel;
    private $resultFactory;
    private $feedGenerator;

    public function __construct(
        Config $config,
        StoreManagerInterface $storeManagerInterface,
        ResultFactory $resultFactory,
        Generator $feedGenerator
    ) {
        $this->configHelper = $config;
        $this->storeModel = $storeManagerInterface;
        $this->resultFactory = $resultFactory;
        $this->feedGenerator = $feedGenerator;
    }

    public function execute()
    {
        $store = $this->storeModel->getStore();

        if (!$this->configHelper->isProductFeedEnabled($store->getId())) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
            $result->setContents('Product Feed is disabled.');
            return $result;
                }

        $feedPath = $this->feedGenerator->getFinalPath($store);

        if (!is_file($feedPath)) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHttpResponseCode(404);
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
            $result->setContents('Product feed has not been generated yet.');
            return $result;
        }

        // Stream straight from disk rather than loading the file into a
        // Result object's body, so serving a large feed doesn't need
        // memory proportional to catalog size.
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Length: ' . filesize($feedPath));
        readfile($feedPath);
        exit;
    }
}