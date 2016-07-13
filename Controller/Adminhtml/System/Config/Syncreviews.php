<?php

namespace Reviewscouk\Reviews\Controller\Adminhtml\System\Config;

use Magento\Catalog as Catalog;
use Magento\Framework as Framework;
use Magento\Backend as Backend;
use Magento\Store as Store;
use Magento\Review as Review;
use Reviewscouk\Reviews as Reviews;

class Syncreviews extends Backend\App\Action
{

    private $_configHelper;
    private $_resultJsonFactory;
    private $_store;
    private $_websites;
    private $_productModel;
    private $_reviewFactory;
    private $_ratingFactory;
    private $_resourceConnection;

    public function __construct(Backend\App\Action\Context $context,
                                Reviews\Helper\Config $config,
                                Framework\Controller\Result\JsonFactory $jsonFactory,
                                Catalog\Model\Product $product,
                                Review\Model\ReviewFactory $reviewFactory,
                                Review\Model\RatingFactory $ratingFactory,
                                Store\Model\StoreManagerInterface $storeManagerInterface,
                                Framework\App\ResourceConnection $resourceConnection)
    {
        parent::__construct($context);

        $this->_resultJsonFactory = $jsonFactory;
        $this->_configHelper = $config;
        $this->_productModel = $product;
        $this->_reviewFactory = $reviewFactory;
        $this->_ratingFactory = $ratingFactory;
        $this->_store = $storeManagerInterface->getStore();
        $this->_websites = $storeManagerInterface->getWebsites();

        $this->_resourceConnection = $resourceConnection;
    }

    public function execute()
    {

        $result = $this->_sync();

        $resultJson = $this->_resultJsonFactory->create();
        return $resultJson->setData([
            'valid' => (int)$result['is_valid'],
            'message' => $result['message'],
        ]);

    }

    private function _sync()
    {
        $result = array();
        // Getting the Store ID
        $storeId = $this->_store->getId();

        $storeIds = array(0);
        foreach ($this->_websites as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    //$store is a store object
                    $storeIds[] = $store->getId();
                }
            }
        }

        if (!$storeId) $storeId = 1;

        // Import Counter
        $imported = 0;
        $skipped = 0;
        $total = 0;

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

                    $connection  = $this->_resource->getConnection('core_read');
                    $tableName   = $connection->getTableName('review_detail');
                    $sql         = "Select * from " . $tableName . " WHERE detail = ? ";
                    $reviewExist = $connection->fetchRow($sql, $comment);

                    $review      = (count($reviewExist) == 0) ? $this->_reviewFactory->create() : $this->_reviewFactory->load($reviewExist['review_id']);

                    $product_id = $this->_productModel
                        ->getIdBySku($row->sku);

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
                                var_dump($label);
                                var_dump($value);
                                //$this->sortRatings($value->rating_text,$value->rating, $product_id, $connection, $prefix, $review);

                            }

                        }
                        else
                        {
                            var_dumpp($row->ratings);
                            //$this->sortRatings('Rating', $row->rating,$product_id, $connection, $prefix, $review);

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

    public function fetchProductReviews($page = 1)
    {

        // Api Key
        $apikey = $this->_configHelper->getApiKey($this->_store->getId());

        // Get Region
        $region = $this->_configHelper->getRegion($this->_store->getId());

        // Get store
        $storeName = $this->_configHelper->getStoreId($this->_store->getId());

        if (empty($storeName))
        {
            throw new Exception('Please Configure API Credentials');
        }

        //TODO:- Use API model here?

        try
        {
            $url = "http://api.reviews.co.uk";
            if ($region == 'US') $url = "http://api.review.io"; // Checking if Region is US or not
            $url .= "/product/reviews/all?store=" . $storeName . "&apikey=" . $apikey . "&page=" . $page;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            $data = curl_exec($ch);
        } catch (Exception $e)
        {
            return false;
        }

        try
        {
            $response = json_decode($data);
        } catch (Exception $e)
        {
            throw new Exception('Problem Parsing Data');
        }

        if (is_object($response))
        {
            return $response;
        }
        else
        {
            throw new Exception('Could not communicate to Reviews.co.uk API');
        }
    }

    private function sortRatings($ratingText, $ratingNumber, $product_id, $connection, $prefix, $review)
    {

    }



}