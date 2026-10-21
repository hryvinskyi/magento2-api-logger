<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Model\Recording\EndpointMasker;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use Hryvinskyi\ApiLogger\Test\Unit\Fixture\ActivityLogSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The secrets of a called endpoint, in its path and in its query, masked through the activity log's own sanitizer
 */
class EndpointMaskerTest extends TestCase
{
    use ActivityLogSanitizer;

    private const RESET_PATTERN = 'GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken';
    private const MASK = SanitizerInterface::MASK;

    /**
     * @var EndpointMasker
     */
    private EndpointMasker $masker;

    protected function setUp(): void
    {
        $this->masker = new EndpointMasker(new RoutePath(), $this->activityLogSanitizer());
    }

    /**
     * @return array<string, array{string, string|null, string}>
     */
    public static function paths(): array
    {
        return [
            'secret parameter' => [
                '/V1/customers/42/password/resetLinkToken/abc123',
                self::RESET_PATTERN,
                '/V1/customers/42/password/resetLinkToken/' . self::MASK,
            ],
            'store code and legacy front name stay' => [
                '/rest/default/V1/customers/42/password/resetLinkToken/abc123',
                self::RESET_PATTERN,
                '/rest/default/V1/customers/42/password/resetLinkToken/' . self::MASK,
            ],
            'async prefix stays' => [
                '/default/async/V1/customers/42/password/resetLinkToken/abc123',
                self::RESET_PATTERN,
                '/default/async/V1/customers/42/password/resetLinkToken/' . self::MASK,
            ],
            'trailing slash stays' => [
                '/V1/customers/42/password/resetLinkToken/abc123/',
                self::RESET_PATTERN,
                '/V1/customers/42/password/resetLinkToken/' . self::MASK . '/',
            ],
            'parameter that is no secret stays' => [
                '/V1/products/24-MB01',
                'GET|/V1/products/:sku',
                '/V1/products/24-MB01',
            ],
            'literal segment named like a secret stays' => [
                '/V1/integration/admin/token',
                'POST|/V1/integration/admin/token',
                '/V1/integration/admin/token',
            ],
            'no pattern' => [
                '/V1/customers/42/password/resetLinkToken/abc123',
                null,
                '/V1/customers/42/password/resetLinkToken/abc123',
            ],
            'pattern without separator' => [
                '/V1/customers/42/password/resetLinkToken/abc123',
                '/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken',
                '/V1/customers/42/password/resetLinkToken/abc123',
            ],
            'wildcard pattern names no parameter' => [
                '/V1/customers/42/password/resetLinkToken/abc123',
                'GET|/V1/customers/*',
                '/V1/customers/42/password/resetLinkToken/abc123',
            ],
            'path of another shape' => [
                '/',
                self::RESET_PATTERN,
                '/',
            ],
        ];
    }

    #[DataProvider('paths')]
    public function testEachPathSegmentASecretRouteParameterStandsForIsMasked(
        string $path,
        ?string $pattern,
        string $masked
    ): void {
        self::assertSame($masked, $this->masker->maskPath($path, $pattern));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function queries(): array
    {
        return [
            'secret key' => ['a=1&access_token=x', 'a=1&access_token=' . self::MASK],
            'secret key in brackets' => [
                'searchCriteria%5BpageSize%5D=1&auth%5Bapi_key%5D=k',
                'searchCriteria%5BpageSize%5D=1&auth%5Bapi_key%5D=' . self::MASK,
            ],
            'nothing secret' => ['searchCriteria[pageSize]=5&a=1', 'searchCriteria[pageSize]=5&a=1'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('queries')]
    public function testTheValueOfEachSecretQueryKeyIsMaskedInPlace(string $query, string $masked): void
    {
        self::assertSame($masked, $this->masker->maskQuery($query));
    }

    public function testAnEndpointHasItsPathAndItsQueryMasked(): void
    {
        self::assertSame(
            '/default/V1/customers/42/password/resetLinkToken/' . self::MASK . '?a=1&access_token=' . self::MASK,
            $this->masker->mask(
                '/default/V1/customers/42/password/resetLinkToken/abc123?a=1&access_token=x',
                self::RESET_PATTERN
            )
        );
    }

    public function testAnEndpointWithoutQueryGetsNone(): void
    {
        self::assertSame('/V1/products', $this->masker->mask('/V1/products', 'GET|/V1/products'));
        self::assertSame('/V1/products?', $this->masker->mask('/V1/products?', 'GET|/V1/products'));
    }
}
