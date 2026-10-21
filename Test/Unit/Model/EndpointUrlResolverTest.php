<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\EndpointUrlResolver;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The absolute URL a stored endpoint is called at, whichever shape it was stored in, never pointed by stored headers
 */
class EndpointUrlResolverTest extends TestCase
{
    /**
     * @var StoreManagerInterface&MockObject
     */
    private $storeManager;

    /**
     * @var AreaList&MockObject
     */
    private $areaList;

    /**
     * @var EndpointUrlResolver
     */
    private EndpointUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->areaList = $this->createMock(AreaList::class);
        $this->areaList->method('getFrontName')->with(Area::AREA_WEBAPI_REST)->willReturn('rest');
        $this->resolver = new EndpointUrlResolver($this->storeManager, $this->areaList);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'versioned path' => ['/V1/x', 'https://base.test/rest/V1/x'],
            'store code' => ['/default/V1/x', 'https://base.test/rest/default/V1/x'],
            'legacy front name' => ['/rest/V1/x', 'https://base.test/rest/V1/x'],
            'legacy front name and store code' => ['/rest/default/V1/x', 'https://base.test/rest/default/V1/x'],
            'query kept' => ['/default/V1/x?a[b]=1', 'https://base.test/rest/default/V1/x?a[b]=1'],
            'no leading slash' => ['V1/x', 'https://base.test/rest/V1/x'],
        ];
    }

    #[DataProvider('endpoints')]
    public function testEveryStoredShapeGivesExactlyOneFrontName(string $endpoint, string $expected): void
    {
        self::assertSame($expected, $this->resolver->resolve($this->entry($endpoint), 'https://base.test'));
    }

    public function testABaseUrlPassedInWinsAndItsTrailingSlashIsNotDoubled(): void
    {
        $this->storeManager->expects(self::never())->method('getStore');
        $this->storeManager->expects(self::never())->method('getDefaultStoreView');

        self::assertSame(
            'https://other.test/rest/V1/x',
            $this->resolver->resolve($this->entry('/V1/x', '{"host":"stored.test"}'), 'https://other.test/')
        );
    }

    public function testTheSecureBaseUrlOfTheStoreOfTheCallIsUsed(): void
    {
        $store = $this->store('https://store.test/');
        $this->storeManager->expects(self::once())->method('getStore')->with(3)->willReturn($store);

        self::assertSame('https://store.test/rest/V1/x', $this->resolver->resolve($this->entry('/V1/x', null, 3)));
    }

    /**
     * Stored headers a caller controls, none of which may point the URL anywhere
     *
     * @return array<string, array{string}>
     */
    public static function forgedHeaders(): array
    {
        return [
            'forged host' => ['{"Host":"evil.test"}'],
            'forged host and plain http' => ['{"Host":"evil.test","X-Forwarded-Proto":"http"}'],
            'plain http alone' => ['{"x-forwarded-proto":"http"}'],
            'forwarded host' => ['{"X-Forwarded-Host":"evil.test","X-Forwarded-Proto":"http"}'],
        ];
    }

    #[DataProvider('forgedHeaders')]
    public function testStoredHeadersAreIgnoredAndTheStoresSecureBaseUrlIsUsed(string $requestHeaders): void
    {
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store('https://secure.test/'));

        self::assertSame(
            'https://secure.test/rest/default/V1/x',
            $this->resolver->resolve($this->entry('/default/V1/x', $requestHeaders, 1))
        );
    }

    public function testACallWithoutAStoreGoesToTheDefaultStoreView(): void
    {
        $this->storeManager->expects(self::never())->method('getStore');
        $this->storeManager->method('getDefaultStoreView')->willReturn($this->store('https://default.test/'));

        self::assertSame(
            'https://default.test/rest/V1/x',
            $this->resolver->resolve($this->entry('/V1/x', '{"Host":"evil.test"}'))
        );
    }

    public function testACallWhoseStoreIsGoneGoesToTheDefaultStoreView(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new NoSuchEntityException());
        $this->storeManager->method('getDefaultStoreView')->willReturn($this->store('https://default.test/'));

        self::assertSame('https://default.test/rest/V1/x', $this->resolver->resolve($this->entry('/V1/x', null, 99)));
    }

    public function testNoStoreAtAllGivesNoPrefix(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new NoSuchEntityException());
        $this->storeManager->method('getDefaultStoreView')->willReturn(null);

        self::assertSame('/rest/V1/x', $this->resolver->resolve($this->entry('/V1/x', '{"Host":"evil.test"}', 99)));
    }

    public function testAStoreWithoutBaseUrlsGivesNoPrefix(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        self::assertSame('/rest/V1/x', $this->resolver->resolve($this->entry('/V1/x', null, 1)));
    }

    public function testAnotherConfiguredFrontNameReplacesRestAndLeavesAStoredOneAlone(): void
    {
        $areaList = $this->createMock(AreaList::class);
        $areaList->method('getFrontName')->with(Area::AREA_WEBAPI_REST)->willReturn('api');
        $resolver = new EndpointUrlResolver($this->storeManager, $areaList);

        self::assertSame(
            'https://base.test/api/V1/x',
            $resolver->resolve($this->entry('/V1/x'), 'https://base.test')
        );
        self::assertSame(
            'https://base.test/api/V1/x',
            $resolver->resolve($this->entry('/api/V1/x'), 'https://base.test')
        );
    }

    public function testNoFrontNameLeavesThePathAsStored(): void
    {
        $areaList = $this->createMock(AreaList::class);
        $areaList->method('getFrontName')->willReturn('');
        $resolver = new EndpointUrlResolver($this->storeManager, $areaList);

        self::assertSame('https://base.test/V1/x', $resolver->resolve($this->entry('/V1/x'), 'https://base.test'));
    }

    /**
     * A store whose secure base web URL is the one given; asking for any other URL fails the test
     *
     * @param string $secureBaseUrl
     * @return Store&MockObject
     */
    private function store(string $secureBaseUrl): Store&MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB, true)->willReturn($secureBaseUrl);

        return $store;
    }

    /**
     * @param string $endpoint
     * @param string|null $requestHeaders
     * @param int|null $storeId
     * @return LogEntryInterface&MockObject
     */
    private function entry(
        string $endpoint,
        ?string $requestHeaders = null,
        ?int $storeId = null
    ): LogEntryInterface&MockObject {
        $entry = $this->createMock(LogEntryInterface::class);
        $entry->method('getEndpoint')->willReturn($endpoint);
        $entry->method('getRequestHeaders')->willReturn($requestHeaders);
        $entry->method('getStoreId')->willReturn($storeId);

        return $entry;
    }
}
