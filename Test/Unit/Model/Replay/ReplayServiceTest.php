<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Replay;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayService;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayTransportInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\Data\UserTokenParametersInterface;
use Magento\Integration\Api\Data\UserTokenParametersInterfaceFactory;
use Magento\Integration\Api\UserTokenIssuerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * What a replay sends, where, and which calls it refuses to send
 */
class ReplayServiceTest extends TestCase
{
    private const STORE_URL = 'https://store.test/rest/default/V1/products';
    private const STAGING_URL = 'https://staging.test/rest/default/V1/products';

    /**
     * @var ConfigInterface&MockObject
     */
    private $config;

    /**
     * @var EndpointUrlResolverInterface&MockObject
     */
    private $urlResolver;

    /**
     * @var ReplayTransportInterface&MockObject
     */
    private $transport;

    /**
     * @var UserTokenIssuerInterface&MockObject
     */
    private $tokenIssuer;

    /**
     * @var ReplayService
     */
    private ReplayService $service;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigInterface::class);
        $this->urlResolver = $this->createMock(EndpointUrlResolverInterface::class);
        $this->urlResolver->method('resolve')->willReturnCallback(
            static fn (LogEntryInterface $entry, ?string $baseUrl): string => $baseUrl === null
                ? self::STORE_URL
                : self::STAGING_URL
        );
        $this->transport = $this->createMock(ReplayTransportInterface::class);
        $this->tokenIssuer = $this->createMock(UserTokenIssuerInterface::class);
        $tokenParametersFactory = $this->createMock(UserTokenParametersInterfaceFactory::class);
        $tokenParametersFactory->method('create')
            ->willReturn($this->createMock(UserTokenParametersInterface::class));

        $this->service = new ReplayService(
            $this->config,
            $this->urlResolver,
            new StoredHeaders(),
            $this->transport,
            $this->createMock(UserContextInterface::class),
            $this->tokenIssuer,
            $tokenParametersFactory
        );
    }

    public function testAReplayToThisStoreGoesToTheResolvedUrlAsTheAdminWithoutHeadersItMustNotForward(): void
    {
        $this->config->method('getReplayBaseUrl')->willReturn(null);
        $this->tokenIssuer->expects(self::once())->method('create')->willReturn('admin-token');
        $this->transport->expects(self::once())->method('send')
            ->with('PUT', self::STORE_URL, [
                'Accept' => 'application/json',
                'X-Request-Id' => 'r-1',
                'Authorization' => 'Bearer admin-token',
            ], '{"sku":"a"}')
            ->willReturn(['status' => 200, 'body' => '{}', 'durationMs' => 3.0]);

        $result = $this->service->replay($this->entry('put', '/default/V1/products', [
            'Host' => 'evil.test',
            'Cookie' => 'admin=abc',
            'Accept-Encoding' => 'gzip',
            'Content-Length' => '11',
            'Connection' => 'keep-alive',
            'Transfer-Encoding' => 'chunked',
            'Authorization' => 'Bearer ' . SanitizerInterface::MASK,
            'X-Api-Key' => SanitizerInterface::MASK,
            'Accept' => 'application/json',
            'X-Request-Id' => 'r-1',
        ]));

        self::assertSame(self::STORE_URL, $result['url']);
        self::assertSame(200, $result['status']);
        self::assertTrue($this->service->replaysToThisStore());
    }

    public function testAReplayToStagingGoesToItsUrlWithTheTokenSetForItAndIssuesNoAdminToken(): void
    {
        $this->config->method('getReplayBaseUrl')->willReturn('https://staging.test/');
        $this->config->method('getReplayToken')->willReturn('staging-token');
        $this->tokenIssuer->expects(self::never())->method('create');
        $this->transport->expects(self::once())->method('send')
            ->with('GET', self::STAGING_URL, ['Authorization' => 'Bearer staging-token'], null)
            ->willReturn(['status' => 200, 'body' => '', 'durationMs' => 1.0]);

        $entry = $this->entry('GET', '/default/V1/products', ['Host' => 'evil.test']);

        self::assertSame(self::STAGING_URL, $this->service->target($entry));
        self::assertSame(self::STAGING_URL, $this->service->replay($entry)['url']);
        self::assertFalse($this->service->replaysToThisStore());
    }

    public function testACallWhoseUrlCarriesAMaskedSecretIsRefusedBeforeAnyTokenIsIssued(): void
    {
        $this->config->method('getReplayBaseUrl')->willReturn(null);
        $this->tokenIssuer->expects(self::never())->method('create');
        $this->transport->expects(self::never())->method('send');
        $entry = $this->entry('POST', '/V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK, []);

        self::assertFalse($this->service->canReplay($entry));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('masked');

        $this->service->replay($entry);
    }

    public function testAnOrdinaryCallCanBeReplayed(): void
    {
        self::assertTrue($this->service->canReplay($this->entry('GET', '/default/V1/products/a', [])));
    }

    public function testOnlyCallsThatChangeDataAreWrites(): void
    {
        self::assertTrue($this->service->writes($this->entry('delete', '/V1/x', [])));
        self::assertFalse($this->service->writes($this->entry('GET', '/V1/x', [])));
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array<string, string> $requestHeaders
     * @return LogEntryInterface&MockObject
     */
    private function entry(string $method, string $endpoint, array $requestHeaders): LogEntryInterface&MockObject
    {
        $entry = $this->createMock(LogEntryInterface::class);
        $entry->method('getMethod')->willReturn($method);
        $entry->method('getEndpoint')->willReturn($endpoint);
        $entry->method('getRequestHeaders')
            ->willReturn($requestHeaders === [] ? null : (string) json_encode($requestHeaders));
        $entry->method('getRequestBody')->willReturn($method === 'GET' ? null : '{"sku":"a"}');

        return $entry;
    }
}
