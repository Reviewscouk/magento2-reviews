<?php

namespace Reviewscouk\Reviews\Test\Unit\Observer;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Api;
use Reviewscouk\Reviews\Observer\SendOrderDetails;

class SendOrderDetailsTest extends TestCase
{
    /** @var Config|MockObject */
    private $configHelper;

    /** @var Api|MockObject */
    private $api;

    /** @var SendOrderDetails */
    private $observer;

    protected function setUp(): void
    {
        $this->configHelper = $this->createStub(Config::class);
        $this->configHelper->method('getStoreId')->willReturn('store-1');
        $this->configHelper->method('getApiKey')->willReturn('api-key');
        $this->configHelper->method('isProductReviewsEnabled')->willReturn(true);
        $this->configHelper->method('getInvitationTrigger')->willReturn('completed');

        $this->api = $this->createMock(Api::class);

        $this->observer = new SendOrderDetails(
            $this->configHelper,
            $this->api,
            $this->createStub(ProductFactory::class),
            $this->createStub(Image::class),
            $this->createStub(Configurable::class),
            $this->createStub(Grouped::class)
        );
    }

    public function testUsesBillingCountryWhenOrderHasNoShippingAddress(): void
    {
        $order = $this->createOrder($this->createAddress('GB'), null);

        $this->assertCountryCodeSent($order, 'GB');
    }

    public function testUsesShippingCountryWhenShippingAddressPresent(): void
    {
        $order = $this->createOrder($this->createAddress('GB'), $this->createAddress('US'));

        $this->assertCountryCodeSent($order, 'US');
    }

    /**
     * @return Order|MockObject
     */
    private function createOrder(Address $billingAddress, ?Address $shippingAddress)
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getCustomerName')->willReturn('John Doe');
        $order->method('getCustomerIsGuest')->willReturn(false);
        $order->method('getCustomerEmail')->willReturn('john@example.com');
        $order->method('getRealOrderId')->willReturn('100000001');
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        return $order;
    }

    /**
     * @return Address|MockObject
     */
    private function createAddress(string $country)
    {
        $address = $this->createStub(Address::class);
        $address->method('getCountryId')->willReturn($country);
        $address->method('getTelephone')->willReturn('07123456789');

        return $address;
    }

    private function assertCountryCodeSent(Order $order, string $expectedCountry): void
    {
        $payload = null;
        $this->api->expects($this->once())
            ->method('apiPost')
            ->willReturnCallback(function ($url, $data) use (&$payload) {
                $payload = $data;
                return '{"status":"success"}';
            });

        $this->observer->execute(new Observer(['event' => new Event(['order' => $order])]));

        $this->assertSame($expectedCountry, $payload['country_code']);
    }
}
