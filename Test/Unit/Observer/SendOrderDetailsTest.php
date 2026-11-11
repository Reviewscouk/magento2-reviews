<?php

namespace Reviewscouk\Reviews\Test\Unit\Observer;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Api;
use Reviewscouk\Reviews\Observer\SendOrderDetails;

/**
 * Covers the "completed" invitation trigger (sales_order_save_after).
 *
 * getStoreId() on the config helper returning null makes dispatchNotification()
 * stop before it builds the invitation, so counting calls to it tells us whether
 * the observer decided to send an invitation.
 */
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
        $this->configHelper = $this->createMock(Config::class);
        $this->configHelper->method('getInvitationTrigger')->willReturn('completed');
        $this->api = $this->createMock(Api::class);

        $this->observer = new SendOrderDetails(
            $this->configHelper,
            $this->api,
            $this->createMock(ProductFactory::class),
            $this->createMock(Image::class),
            $this->createMock(Configurable::class),
            $this->createMock(Grouped::class)
        );
    }

    public function testSendsWhenOrderMovesToComplete(): void
    {
        $this->expectInvitationAttempts(1);

        $this->observer->execute($this->orderSaved(10, 'processing', 'complete'));
    }

    public function testSendsWhenOrderIsCreatedAsComplete(): void
    {
        $this->expectInvitationAttempts(1);

        $this->observer->execute($this->orderSaved(10, null, 'complete'));
    }

    public function testDoesNotSendWhenAnAlreadyCompleteOrderIsSavedAgain(): void
    {
        $this->expectInvitationAttempts(0);

        $this->observer->execute($this->orderSaved(10, 'complete', 'complete'));
    }

    public function testDoesNotSendForEachPastOrderWhenACustomerIsResaved(): void
    {
        $this->expectInvitationAttempts(0);

        foreach ([101, 102, 103] as $orderId) {
            $this->observer->execute($this->orderSaved($orderId, 'complete', 'complete'));
        }
    }

    public function testSendsOnceWhenTheSameOrderObjectIsSavedTwiceInOneRequest(): void
    {
        // Magento keeps the pre-load value in getOrigData() after a save, so the
        // second save of the same object still reports "processing".
        $this->expectInvitationAttempts(1);

        $this->observer->execute($this->orderSaved(10, 'processing', 'complete'));
        $this->observer->execute($this->orderSaved(10, 'processing', 'complete'));
    }

    public function testDoesNotSendWhenOrderIsNotComplete(): void
    {
        $this->expectInvitationAttempts(0);

        $this->observer->execute($this->orderSaved(10, 'new', 'processing'));
    }

    private function expectInvitationAttempts(int $count): void
    {
        $this->configHelper->expects($this->exactly($count))->method('getStoreId')->willReturn(null);
        $this->api->expects($this->never())->method('apiPost');
    }

    /**
     * Build the event Magento passes to the observer for sales_order_save_after.
     */
    private function orderSaved($orderId, $originalState, $state): Observer
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn($orderId);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getState')->willReturn($state);
        $order->method('getOrigData')->with('state')->willReturn($originalState);

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getShipment', 'getOrder'])
            ->getMock();
        $event->method('getShipment')->willReturn(null);
        $event->method('getOrder')->willReturn($order);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }
}
