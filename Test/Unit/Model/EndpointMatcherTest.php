<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model;

use Hryvinskyi\ApiLogger\Model\EndpointMatcher;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A selected endpoint pattern (`METHOD|/route`) against the endpoint of a call
 */
class EndpointMatcherTest extends TestCase
{
    /**
     * @var EndpointMatcher
     */
    private EndpointMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new EndpointMatcher(new RoutePath());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function matching(): array
    {
        return [
            'exact route' => ['/V1/products', 'GET', 'GET|/V1/products'],
            'a parameter segment matches any one segment' => ['/V1/products/24-MB01', 'GET', 'GET|/V1/products/:sku'],
            'a parameter in the middle' => ['/V1/orders/7/ship', 'POST', 'POST|/V1/orders/:id/ship'],
            'trailing star matches one further segment' => ['/V1/x/a', 'GET', 'GET|/V1/x/*'],
            'trailing star matches several further segments' => ['/V1/x/a/b/c', 'GET', 'GET|/V1/x/*'],
            'method compares without case' => ['/V1/x', 'GET', 'get|/V1/x'],
            'called method compares without case' => ['/V1/x', 'get', 'GET|/V1/x'],
            'blanks around the method are ignored' => ['/V1/x', 'GET', ' GET |/V1/x'],
            'a store code is not part of the route' => ['/default/V1/x', 'GET', 'GET|/V1/x'],
            'the all-stores code is not part of the route' => ['/all/V1/x', 'GET', 'GET|/V1/x'],
            'a legacy front name is not part of the route' => ['/rest/V1/x', 'GET', 'GET|/V1/x'],
            'a legacy front name and a store code' => ['/rest/default/V1/x', 'GET', 'GET|/V1/x'],
            'a query is not part of the route' => ['/V1/x?searchCriteria[pageSize]=5', 'GET', 'GET|/V1/x'],
            'store code, legacy front name and query together' => ['/rest/fr/V1/x?a=1', 'GET', 'GET|/V1/x'],
            'an async call matches the synchronous route' => ['/async/V1/x', 'POST', 'POST|/V1/x'],
            'a bulk call matches the synchronous route' => ['/async/bulk/V1/x', 'POST', 'POST|/V1/x'],
            'an async call after a store code' => ['/default/async/V1/x', 'POST', 'POST|/V1/x'],
            'a trailing star after a store code' => ['/default/V1/x/a', 'GET', 'GET|/V1/x/*'],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function notMatching(): array
    {
        return [
            'another method' => ['/V1/x', 'POST', 'GET|/V1/x'],
            'another route' => ['/V1/y', 'GET', 'GET|/V1/x'],
            'another version' => ['/V2/x', 'GET', 'GET|/V1/x'],
            'trailing star does not match zero further segments' => ['/V1/x', 'GET', 'GET|/V1/x/*'],
            'the called endpoint is longer' => ['/V1/x/y', 'GET', 'GET|/V1/x'],
            'the called endpoint is shorter' => ['/V1/x', 'GET', 'GET|/V1/x/y'],
            'a parameter segment does not match a missing segment' => ['/V1/x', 'GET', 'GET|/V1/x/:id'],
            'a parameter segment matches exactly one segment' => ['/V1/x/a/b', 'GET', 'GET|/V1/x/:id'],
            'a pattern without a method separator' => ['/V1/x', 'GET', '/V1/x'],
            'a pattern that is a bare method' => ['/V1/x', 'GET', 'GET'],
            'an empty pattern' => ['/V1/x', 'GET', ''],
        ];
    }

    #[DataProvider('matching')]
    public function testEndpointMatchesPattern(string $endpoint, string $method, string $pattern): void
    {
        self::assertTrue($this->matcher->matches($endpoint, $method, $pattern));
    }

    #[DataProvider('notMatching')]
    public function testEndpointDoesNotMatchPattern(string $endpoint, string $method, string $pattern): void
    {
        self::assertFalse($this->matcher->matches($endpoint, $method, $pattern));
    }
}
