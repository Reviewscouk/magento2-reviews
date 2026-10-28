<?php

namespace Reviewscouk\Reviews\Test\Unit\Observer;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Helper\OrderDate;
use Reviewscouk\Reviews\Model\Api;
use Reviewscouk\Reviews\Observer\SendOrderDetails;

class SendOrderDetailsTest extends TestCase
{
    /** @var Config|MockObject */
    private $configHelper;

    /** @var Api|MockObject */
    private $apiModel;

    /** @var OrderDate|MockObject */
    private $orderDateHelper;

    /** @var ImageHelper|MockObject */
    private $imageHelper;

    /** @var SendOrderDetails */
    private $observer;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(Config::class);
        $this->apiModel = $this->createMock(Api::class);
        $this->orderDateHelper = $this->createMock(OrderDate::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn('https://example.com/image.jpg');

        $this->observer = new SendOrderDetails(
            $this->configHelper,
            $this->apiModel,
            $this->createMock(ProductFactory::class),
            $this->imageHelper,
            $this->createMock(Configurable::class),
            $this->createMock(Grouped::class),
            $this->orderDateHelper
        );
    }

    public function testInvitationIncludesOrderDateFromOrderCreation(): void
    {
        $order = $this->createConfiguredOrder(['created_at' => '2026-10-05 10:15:00']);

        $this->givenInvitesEnabled();
        $this->orderDateHelper->expects($this->once())
            ->method('fromCreatedAt')
            ->with('2026-10-05 10:15:00')
            ->willReturn('2026-10-05');

        $this->apiModel->expects($this->once())
            ->method('apiPost')
            ->with(
                'invitation',
                $this->callback(function (array $payload) {
                    return ($payload['source'] ?? null) === 'magento'
                        && ($payload['order_date'] ?? null) === '2026-10-05'
                        && ($payload['order_date_source'] ?? null) === 'magento'
                        && ($payload['order_id'] ?? null) === '100000001';
                }),
                1
            )
            ->willReturn('{"status":"success"}');

        $this->apiModel->expects($this->once())->method('addStatusMessage');

        $this->observer->dispatchNotification($order);
    }

    public function testInvitationOmitsOrderDateWhenCreationDateMissing(): void
    {
        $order = $this->createConfiguredOrder(['created_at' => null]);

        $this->givenInvitesEnabled();
        $this->orderDateHelper->expects($this->once())
            ->method('fromCreatedAt')
            ->with(null)
            ->willReturn(null);

        $this->apiModel->expects($this->once())
            ->method('apiPost')
            ->with(
                'invitation',
                $this->callback(function (array $payload) {
                    return !array_key_exists('order_date', $payload)
                        && !array_key_exists('order_date_source', $payload);
                }),
                1
            )
            ->willReturn('{"status":"success"}');

        $this->observer->dispatchNotification($order);
    }

    public function testInvitationUsesOriginalCreatedAtEvenIfOrderWasEdited(): void
    {
        // Magento keeps created_at as the original placement time after edits.
        $order = $this->createConfiguredOrder(['created_at' => '2026-09-01 08:00:00']);

        $this->givenInvitesEnabled();
        $this->orderDateHelper->method('fromCreatedAt')
            ->with('2026-09-01 08:00:00')
            ->willReturn('2026-09-01');

        $this->apiModel->expects($this->once())
            ->method('apiPost')
            ->with(
                'invitation',
                $this->callback(function (array $payload) {
                    return ($payload['order_date'] ?? null) === '2026-09-01';
                }),
                1
            )
            ->willReturn('{"status":"success"}');

        $this->observer->dispatchNotification($order);
    }

    private function givenInvitesEnabled(): void
    {
        $this->configHelper->method('getStoreId')->willReturn('test-store');
        $this->configHelper->method('getApiKey')->willReturn('test-key');
        $this->configHelper->method('isProductReviewsEnabled')->willReturn(true);
        $this->configHelper->method('isUsingGroupSkus')->willReturn(false);
    }

    private function createConfiguredOrder(array $overrides): Order
    {
        // Duck-typed order item: Magento AbstractModel exposes several of these via __call.
        $item = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getSku', 'getId', 'getName', 'getProductUrl', 'getProduct'])
            ->getMock();
        $item->method('getSku')->willReturn('SKU-1');
        $item->method('getId')->willReturn(10);
        $item->method('getName')->willReturn('Test Product');
        $item->method('getProductUrl')->willReturn('https://example.com/p/1');
        $item->method('getProduct')->willReturn(null);

        $shipping = $this->createMock(Address::class);
        $shipping->method('getCountryId')->willReturn('GB');

        $billing = $this->createMock(Address::class);
        $billing->method('getTelephone')->willReturn('07000000000');
        $billing->method('getFirstName')->willReturn('Guest');

        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getCustomerName')->willReturn('Jane Doe');
        $order->method('getCustomerIsGuest')->willReturn(false);
        $order->method('getCustomerEmail')->willReturn('jane@example.com');
        $order->method('getRealOrderId')->willReturn('100000001');
        $order->method('getShippingAddress')->willReturn($shipping);
        $order->method('getBillingAddress')->willReturn($billing);
        $order->method('getCreatedAt')->willReturn($overrides['created_at'] ?? null);

        return $order;
    }
}
