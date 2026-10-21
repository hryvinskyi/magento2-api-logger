<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use Hryvinskyi\ApiLogger\Model\Recording\SecretResponsePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Routes whose whole response is a secret, read from the route of every shape a logged path takes
 *
 * The routes the module configures are covered through the real container by the integration test of the same name.
 */
class SecretResponsePolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function endpoints(): array
    {
        return [
            'route of a pattern' => ['/V1/issue/key', true],
            'route of another pattern' => ['/V2/vault/7/open', true],
            'legacy front name, store code and query' => ['/rest/default/V1/issue/key?x=1', true],
            'async prefix' => ['/default/async/V1/issue/key', true],
            'trailing slash' => ['/default/V1/issue/key/', true],
            'route no pattern matches' => ['/V1/issue/keys', false],
            'pattern text further along the path' => ['/V1/products/issue/key/x', false],
        ];
    }

    #[DataProvider('endpoints')]
    public function testTheRouteOfTheEndpointIsMatchedAgainstEveryPattern(string $endpoint, bool $masked): void
    {
        $policy = new SecretResponsePolicy(new RoutePath(), [
            'issue' => '#^/V1/issue/key$#',
            'vault' => '#^/V\d+/vault/[^/]+/open$#',
        ]);

        self::assertSame($masked, $policy->masksWholeResponse($endpoint));
    }

    public function testNoRoutesMaskNoResponse(): void
    {
        $policy = new SecretResponsePolicy(new RoutePath());

        self::assertFalse($policy->masksWholeResponse('/V1/integration/admin/token'));
    }

    public function testARouteThatIsNotTextFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"broken"');

        new SecretResponsePolicy(new RoutePath(), ['broken' => ['#x#']]);
    }

    public function testARouteThatIsNoRegularExpressionFailsLoudly(): void
    {
        set_error_handler(static fn (): bool => true);
        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('"broken"');

            new SecretResponsePolicy(new RoutePath(), ['broken' => '/tokens?$']);
        } finally {
            restore_error_handler();
        }
    }
}
