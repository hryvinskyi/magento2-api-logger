<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every shape a logged REST path takes gives the same route and its store code
 */
class RoutePathTest extends TestCase
{
    /**
     * @var RoutePath
     */
    private RoutePath $routePath;

    protected function setUp(): void
    {
        $this->routePath = new RoutePath();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return array_map(static fn (array $shape): array => [$shape[0], $shape[1]], self::shapes());
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function storeCodes(): array
    {
        return array_map(static fn (array $shape): array => [$shape[0], $shape[2]], self::shapes());
    }

    #[DataProvider('routes')]
    public function testRouteIsTheVersionedPathWithoutPrefixesOrQuery(string $endpoint, string $route): void
    {
        self::assertSame($route, $this->routePath->route($endpoint));
    }

    #[DataProvider('storeCodes')]
    public function testStoreCodeIsTheSegmentBeforeTheAsyncPrefixAndTheRoute(string $endpoint, ?string $storeCode): void
    {
        self::assertSame($storeCode, $this->routePath->storeCode($endpoint));
    }

    public function testAPathOfAnotherShapeIsItsOwnRouteAndHasNoStoreCode(): void
    {
        self::assertSame('/not/a/webapi/path', $this->routePath->route('/not/a/webapi/path?x=1'));
        self::assertSame('/not/a/webapi/path', $this->routePath->route('/not/a/webapi/path/'));
        self::assertNull($this->routePath->storeCode('/not/a/webapi/path?x=1'));
    }

    public function testTheRootPathStaysTheRoot(): void
    {
        self::assertSame('/', $this->routePath->route('/'));
        self::assertSame('/', $this->routePath->route('/?x=1'));
    }

    /**
     * Endpoint → route and store code, each shape with and without a query
     *
     * @return array<string, array{string, string, string|null}>
     */
    private static function shapes(): array
    {
        $shapes = [
            'bare route' => ['/V1/x', '/V1/x', null],
            'store code' => ['/default/V1/x', '/V1/x', 'default'],
            'legacy front name' => ['/rest/V1/x', '/V1/x', null],
            'legacy front name and store code' => ['/rest/default/V1/x', '/V1/x', 'default'],
            'legacy front name and all stores' => ['/rest/all/V1/x', '/V1/x', 'all'],
            'async' => ['/async/V1/products', '/V1/products', null],
            'async bulk' => ['/async/bulk/V1/products/bySku', '/V1/products/bySku', null],
            'store code and async' => ['/default/async/V1/products', '/V1/products', 'default'],
            'store code and async bulk' => ['/default/async/bulk/V1/x', '/V1/x', 'default'],
            'legacy front name, store code and async bulk' => [
                '/rest/default/async/bulk/V1/products',
                '/V1/products',
                'default',
            ],
            'legacy front name and async' => ['/rest/async/V1/x', '/V1/x', null],
            'front name lookalike is a store code' => ['/restaurants/V1/x', '/V1/x', 'restaurants'],
            'deeper route' => ['/default/V2/carts/mine/items/5', '/V2/carts/mine/items/5', 'default'],
            'trailing slash' => ['/default/V1/integration/admin/token/', '/V1/integration/admin/token', 'default'],
            'trailing slashes' => ['/V1/x//', '/V1/x', null],
        ];

        $cases = [];
        foreach ($shapes as $name => [$endpoint, $route, $storeCode]) {
            $cases[$name] = [$endpoint, $route, $storeCode];
            $cases[$name . ' with query'] = [$endpoint . '?searchCriteria[pageSize]=5&a=1', $route, $storeCode];
        }

        return $cases;
    }
}
