<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Recording;

use Hryvinskyi\ApiLogger\Model\Recording\SecretResponsePolicy;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The routes whose whole response is stored as the mask, as the container configures the policy
 */
class SecretResponsePolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function endpoints(): array
    {
        return [
            'admin token' => ['/V1/integration/admin/token', true],
            'customer token with legacy front name and store code' => [
                '/rest/default/V1/integration/customer/token',
                true,
            ],
            'admin token with store code and query' => ['/default/V1/integration/admin/token?x=1', true],
            'admin token with all stores' => ['/rest/all/V1/integration/admin/token', true],
            'admin token with trailing slash' => ['/default/V1/integration/admin/token/', true],
            'second factor authenticate' => ['/V1/tfa/provider/google/authenticate', true],
            'second factor one-touch authenticate' => ['/V1/tfa/provider/authy/authenticate-onetouch', true],
            'second factor verify' => ['/default/V1/tfa/provider/u2fkey/verify', true],
            'second factor configure' => ['/V1/tfa/provider/google/configure/', true],
            'catalog route' => ['/V1/products', false],
            'token-like segment in the middle' => ['/V1/tokens/list', false],
            'token revocation' => ['/V1/integration/customer/revoke-customer-token', false],
            'second factor activate' => ['/V1/tfa/provider/google/activate', false],
            'second factor providers' => ['/V1/tfa/installed-providers', false],
        ];
    }

    #[DataProvider('endpoints')]
    public function testTokenAndSecondFactorSecretRoutesHaveTheirWholeResponseMasked(
        string $endpoint,
        bool $masked
    ): void {
        $policy = Bootstrap::getObjectManager()->get(SecretResponsePolicy::class);
        self::assertInstanceOf(SecretResponsePolicy::class, $policy);

        self::assertSame($masked, $policy->masksWholeResponse($endpoint));
    }
}
