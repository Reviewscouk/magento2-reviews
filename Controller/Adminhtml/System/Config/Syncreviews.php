<?php

namespace Reviewscouk\Reviews\Controller\Adminhtml\System\Config;

use Magento\Framework as Framework;
use Magento\Backend as Backend;

class Syncreviews extends Backend\App\Action
{

    protected $_resultJsonFactory;


    public function __construct(Backend\App\Action\Context $context,
                                Framework\Controller\Result\JsonFactory $jsonFactory)
    {
        parent::__construct($context);

        $this->_resultJsonFactory = $jsonFactory;
    }

    public function execute()
    {

        $result = $this->_sync();

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData([
            'valid' => (int)$result['is_valid'],
            'message' => $result['message'],
        ]);

    }

    private function _sync()
    {
        $result = array();
        // Getting the Store ID
        $storeId = Mage::app()->getStore()->getId();

        $storeIds = array(0);
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    //$store is a store object
                    $storeIds[] = $store->store_id;
                }
            }
        }

        if (!$storeId) $storeId = 1;

        // Import Counter
        $imported = 0;
        $skipped = 0;
        $total = 0;

        // Table Prefix
        $prefix = Mage::getConfig()->getTablePrefix();

        try
        {
            $fetch = $this->fetchProductReviews();

            for ($i = 0; $i <= $fetch->total_pages; $i++)
            {
                $fetch = $this->fetchProductReviews($i);

                foreach ($fetch->reviews as $row)
                {

                    $skipped++;

                    $comment     = $row->review;

                    $connection  = Mage::getSingleton('core/resource')->getConnection('core_read');
                    $sql         = "Select * from " . $prefix . "review_detail WHERE detail = ? ";
                    $reviewExist = $connection->fetchRow($sql, $comment);

                    $review      = (count($reviewExist) == 0) ? Mage::getModel('review/review') : Mage::getModel('review/review')->load($reviewExist['review_id']);

                    $product_id = Mage::getModel("catalog/product")->getIdBySku($row->sku);

                    // Only Importing if the product exist on magento side
                    if ($product_id)
                    {
                        $imported++;

                        $review->setEntityPkValue($product_id);
                        $review->setStatusId(1);
                        $review->setTitle(substr($comment, 0, 50));
                        $review->setDetail($comment);
                        $review->setEntityId(1);
                        $review->setStoreId($storeId);
                        $review->setStatusId(1);
                        $review->setCustomerId(null);
                        $review->setNickname($row->reviewer->first_name . ' ' . $row->reviewer->last_name);
                        $review->setReviewId($review->getId());
                        $review->setStores($storeIds);
                        $review->save();

                        // If the user has provided ratings then we need to add some data to ratings table.
                        if (count($row->ratings) > 0)
                        {
                            $ratings = $row->ratings;

                            foreach ($ratings as $label => $value)
                            {
                                $this->sortRatings($value->rating_text,$value->rating, $product_id, $connection, $prefix, $review);
                            }

                            $review->aggregate();
                        }
                        else
                        {
                            $this->sortRatings('Rating', $row->rating,$product_id, $connection, $prefix, $review);

                            $review->aggregate();
                        }
                    }
                }
            }

            $skipped = $skipped - $imported;
            $result['message'] = " Total number of reviews imported or updated were ".$imported .", Number of reviews skipped were ".$skipped;
            $result['isValid'] = 1;
        } catch (Exception $e)
        {
            $result['message'] = $e->getMessage();
            $result['isValid'] = 0;
        }

        return $result;
    }



}