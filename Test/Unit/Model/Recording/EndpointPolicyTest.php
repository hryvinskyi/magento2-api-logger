<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Model\EndpointMatcher;
use Hryvinskyi\ApiLogger\Model\Recording\CallStore;
use Hryvinskyi\ApiLogger\Model\Recording\EndpointPolicy;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Which calls are logged, read for the store the call's path names
 */
class EndpointPolicyTest extends TestCase
{
    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var CallStore&MockObject
     */
    private $callStore;

    /**
     * @var EndpointPolicy
     */
    private EndpointPolicy $policy;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->callStore = $this->createMock(CallStore::class);
        $this->policy = new EndpointPolicy($this->config, new EndpointMatcher(new RoutePath()), $this->callStore);
    }

    public function testNothingIsLoggedWhileTheLoggerIsDisabled(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->with(null)->willReturn(false);
        $this->config->expects(self::never())->method('getEnabledEndpoints');

        self::assertFalse($this->policy->logsCall('/V1/products', 'GET'));
    }

    public function testAnEndpointOutsideTheEnabledListIsNotLogged(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn(['GET|/V1/orders', 'POST|/V1/products']);

        self::assertFalse($this->policy->logsCall('/V1/products', 'GET'));
    }

    public function testNoEnabledEndpointsLogsNothing(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn([]);

        self::assertFalse($this->policy->logsCall('/V1/products', 'GET'));
    }

    public function testAnEndpointSelectedByAParameterPatternIsLogged(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn(['GET|/V1/orders', 'GET|/V1/products/:sku']);

        self::assertTrue($this->policy->logsCall('/V1/products/24-MB01', 'GET'));
    }

    public function testASelectedEndpointIsLoggedWhateverStoreCodeAndFrontNameItsPathCarries(): void
    {
        $this->callStore->method('storeId')->willReturn(1);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn(['GET|/V1/products']);

        self::assertTrue($this->policy->logsCall('/default/V1/products?searchCriteria[pageSize]=5', 'GET'));
        self::assertTrue($this->policy->logsCall('/rest/default/V1/products', 'GET'));
    }

    public function testTheStoreOfTheCallIsTheOneEveryConfigurationReadUses(): void
    {
        $this->callStore->expects(self::once())->method('storeId')->with('/fr/V1/products')->willReturn(7);
        $this->config->expects(self::once())->method('isEnabled')->with(7)->willReturn(true);
        $this->config->expects(self::once())->method('getEnabledEndpoints')->with(7)
            ->willReturn(['GET|/V1/products']);

        self::assertTrue($this->policy->logsCall('/fr/V1/products', 'GET'));
    }

    public function testAnEmptyResponseCodeSelectionKeepsEveryResponse(): void
    {
        $this->config->method('getEnabledResponseCodes')->willReturn([]);

        self::assertTrue($this->policy->keepsResponse(200, null));
        self::assertTrue($this->policy->keepsResponse(500, null));
    }

    public function testOnlyTheSelectedResponseCodesAreKept(): void
    {
        $this->config->method('getEnabledResponseCodes')->willReturn(['401', '500']);

        self::assertTrue($this->policy->keepsResponse(401, null));
        self::assertTrue($this->policy->keepsResponse(500, null));
        self::assertFalse($this->policy->keepsResponse(200, null));
    }

    public function testResponseCodesAreReadForTheGivenStore(): void
    {
        $this->config->expects(self::once())->method('getEnabledResponseCodes')->with(3)->willReturn(['404']);

        self::assertTrue($this->policy->keepsResponse(404, 3));
    }

    public function testTheMatchingPatternIsTheEnabledPatternTheCallMatched(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn(['GET|/V1/orders', 'GET|/V1/products/:sku']);

        self::assertSame('GET|/V1/products/:sku', $this->policy->matchingPattern('/default/V1/products/a?x=1', 'GET'));
        self::assertNull($this->policy->matchingPattern('/V1/products/a', 'POST'));
    }

    public function testThereIsNoMatchingPatternWhileTheLoggerIsDisabled(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(false);
        $this->config->method('getEnabledEndpoints')->willReturn(['GET|/V1/products/:sku']);

        self::assertNull($this->policy->matchingPattern('/V1/products/a', 'GET'));
    }

    public function testAPatternNamingEverySegmentWinsOverAWildcardListedBeforeIt(): void
    {
        $this->callStore->method('storeId')->willReturn(null);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getEnabledEndpoints')->willReturn([
            'GET|/V1/customers/*',
            'GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken',
        ]);

        self::assertSame(
            'GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken',
            $this->policy->matchingPattern('/V1/customers/42/password/resetLinkToken/abc', 'GET')
        );
        self::assertSame('GET|/V1/customers/*', $this->policy->matchingPattern('/V1/customers/42', 'GET'));
    }

    public function testTheSelectedPatternIsReadWhetherOrNotLoggingIsSwitchedOn(): void
    {
        $this->callStore->expects(self::once())->method('storeId')->with('/rest/fr/V1/products/a')->willReturn(7);
        $this->config->expects(self::never())->method('isEnabled');
        $this->config->expects(self::once())->method('getEnabledEndpoints')->with(7)
            ->willReturn(['GET|/V1/products/:sku']);

        self::assertSame('GET|/V1/products/:sku', $this->policy->selectedPattern('/rest/fr/V1/products/a', 'GET'));
    }
}
