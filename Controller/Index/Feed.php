<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Reviewscouk\Reviews\Helper\Config;
use Magento\Framework\Controller\ResultFactory;
use Reviewscouk\Reviews\Model\Feed\Generator;

class Feed implements HttpGetActionInterface
{
    protected $configHelper;
    protected $storeModel;
    protected $resultFactory;
    protected $generator;

    public function __construct(
        StoreManagerInterface $storeManagerInterface,
        Config $config,
        ResultFactory $resultFactory,
        Generator $generator
    ) {
        $this->configHelper = $config;
        $this->storeModel = $storeManagerInterface;
        $this->resultFactory = $resultFactory;
        $this->generator = $generator;
    }

    private function serveStaticFeed($store)
    {
        $feedPath = $this->generator->getFinalPath($store);

        if (!is_file($feedPath)) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setHttpResponseCode(503);
            $result->setHeader('Retry-After', '3600', true);
            $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
            $result->setContents('Product feed is being generated. Please check back shortly.');
            return $result;
        }

        $this->beginXmlOutput();

        header('Content-Length: ' . filesize($feedPath));
        readfile($feedPath);

        exit;
    }

    private function beginXmlOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        set_time_limit(0);

        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: no-store');
    }

    public function execute()
    {
        $store = $this->storeModel->getStore();

        if ($this->configHelper->isProductFeedCronEnabled($store->getId())) {
            return $this->serveStaticFeed($store);
        }

        if (!$this->configHelper->isProductFeedEnabled($store->getId())) {
            print "Product Feed is disabled.";
            return;
        }

        $this->beginXmlOutput();
        $this->generator->stream($store);
        exit;
    }
}
