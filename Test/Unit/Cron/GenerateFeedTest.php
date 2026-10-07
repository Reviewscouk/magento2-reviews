<?php

namespace Reviewscouk\Reviews\Test\Unit\Cron;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Reviewscouk\Reviews\Cron\GenerateFeed;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Feed\Generator;

class GenerateFeedTest extends TestCase
{
    /** @var Generator|MockObject */
    private $generator;

    /** @var StoreManagerInterface|MockObject */
    private $storeManager;

    /** @var Config|MockObject */
    private $configHelper;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var GenerateFeed */
    private $cron;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(Generator::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->cron = new GenerateFeed(
            $this->generator,
            $this->storeManager,
            $this->configHelper,
            $this->logger
        );
    }

    public function testGeneratesFeedForEveryEnabledStore(): void
    {
        $default = $this->createStore(1, 'default');
        $french = $this->createStore(2, 'french');
        $this->givenStores([$default, $french], [1 => true, 2 => true]);

        $generated = [];
        $this->generator->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(function (StoreInterface $store) use (&$generated) {
                $generated[] = $store->getCode();
                return '/feed.xml';
            });
        $this->logger->expects($this->never())->method('error');

        $this->cron->execute();

        $this->assertSame(['default', 'french'], $generated);
    }

    public function testSkipsStoresWithProductFeedDisabled(): void
    {
        $enabled = $this->createStore(1, 'enabled');
        $disabled = $this->createStore(2, 'disabled');
        $this->givenStores([$enabled, $disabled], [1 => true, 2 => false]);

        $this->generator->expects($this->once())
            ->method('generate')
            ->with($enabled);
        $this->logger->expects($this->never())->method('error');

        $this->cron->execute();
    }

    public function testSkipsStoresWithFallbackDisabled(): void
    {
        $withFallback = $this->createStore(1, 'with_fallback');
        $withoutFallback = $this->createStore(2, 'without_fallback');
        $this->givenStores(
            [$withFallback, $withoutFallback],
            [1 => true, 2 => true],
            [1 => true, 2 => false]
        );

        $this->generator->expects($this->once())
            ->method('generate')
            ->with($withFallback);
        $this->logger->expects($this->never())->method('error');

        $this->cron->execute();
    }

    public function testLogsFailureAndContinuesWithNextStore(): void
    {
        $failing = $this->createStore(1, 'failing');
        $working = $this->createStore(2, 'working');
        $this->givenStores([$failing, $working], [1 => true, 2 => true]);

        $generated = [];
        $this->generator->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(function (StoreInterface $store) use (&$generated) {
                if ($store->getCode() === 'failing') {
                    throw new \RuntimeException('disk full');
                }
                $generated[] = $store->getCode();
                return '/feed.xml';
            });
        $this->logger->expects($this->once())
            ->method('error')
            ->with('REVIEWS.io feed generation skipped for store "failing": disk full');

        $this->cron->execute();

        $this->assertSame(['working'], $generated);
    }

    /**
     * @param StoreInterface[] $stores
     * @param bool[] $enabledByStoreId
     * @param bool[]|null $fallbackByStoreId defaults to enabled for every store
     */
    private function givenStores(array $stores, array $enabledByStoreId, ?array $fallbackByStoreId = null): void
    {
        $this->storeManager->method('getStores')->willReturn($stores);
        $this->configHelper->method('isProductFeedEnabled')
            ->willReturnCallback(function ($storeId) use ($enabledByStoreId) {
                return $enabledByStoreId[$storeId];
            });
        $this->configHelper->method('isProductFeedCronEnabled')
            ->willReturnCallback(function ($storeId) use ($fallbackByStoreId) {
                return $fallbackByStoreId === null ? true : $fallbackByStoreId[$storeId];
            });
    }

    /**
     * @return StoreInterface|MockObject
     */
    private function createStore(int $id, string $code)
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        return $store;
    }
}
