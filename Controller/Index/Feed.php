<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework\App\Action;
use Magento\Framework\Controller\Result;
use Reviewscouk\Reviews\Helper;

class Feed extends \Magento\Framework\App\Action\Action
{

    public function __construct(Action\Context $context,
                                Result\JsonFactory $resultJsonFactory,
                                Helper\Data $helperData)
    {
        parent::__construct($context);


    }

    public function execute()
    {
        die("hello");
    }


}