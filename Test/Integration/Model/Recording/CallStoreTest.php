<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Recording;

use Hryvinskyi\ApiLogger\Model\Recording\CallStore;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The store a REST call targets, resolved against the real stores
 */
class CallStoreTest extends TestCase
{
    /**
     * @var CallStore
     */
    private CallStore $callStore;

    /**
     * @var int
     */
    private int $defaultStoreId;

    protected function setUp(): void
    {
        $this->callStore = self::service(CallStore::class);
        $this->defaultStoreId = (int) self::service(StoreRepositoryInterface::class)->get('default')->getId();
    }

    public function testAStoreCodeGivesThatStoresId(): void
    {
        self::assertNotSame(Store::DEFAULT_STORE_ID, $this->defaultStoreId);
        self::assertSame($this->defaultStoreId, $this->callStore->storeId('/default/V1/products?x=1'));
    }

    public function testAStoreCodeAfterTheLegacyFrontNameGivesThatStoresId(): void
    {
        self::assertSame($this->defaultStoreId, $this->callStore->storeId('/rest/default/V1/x'));
    }

    public function testAllStoresIsTheAdminStore(): void
    {
        self::assertSame(Store::DEFAULT_STORE_ID, $this->callStore->storeId('/all/V1/products'));
        self::assertSame(Store::DEFAULT_STORE_ID, $this->callStore->storeId('/rest/all/V1/products'));
    }

    public function testAPathWithoutAStoreCodeNamesNoStore(): void
    {
        self::assertNull($this->callStore->storeId('/V1/products'));
        self::assertNull($this->callStore->storeId('/rest/V1/products'));
        self::assertNull($this->callStore->storeId('/async/bulk/V1/products'));
    }

    public function testAnUnknownStoreCodeNamesNoStore(): void
    {
        self::assertNull($this->callStore->storeId('/nosuchstore/V1/products'));
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
