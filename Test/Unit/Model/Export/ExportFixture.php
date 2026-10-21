<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Export;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * A stored POST call with a masked bearer token, and the resolver that gives its absolute URL
 */
trait ExportFixture
{
    /**
     * A stored call whose request carried a bearer token (masked) and a JSON body holding a single quote
     *
     * @param string|null $requestHeaders stored request headers; the default holds no host header
     * @param int|null $responseCode
     * @return LogEntryInterface&MockObject
     */
    private function storedCall(?string $requestHeaders = null, ?int $responseCode = 200): LogEntryInterface&MockObject
    {
        $entry = $this->createMock(LogEntryInterface::class);
        $entry->method('getMethod')->willReturn('POST');
        $entry->method('getEndpoint')->willReturn('/default/V1/orders?x=1');
        $entry->method('getEntityId')->willReturn(42);
        $entry->method('getActivityId')->willReturn(0);
        $entry->method('getCreatedAt')->willReturn('2026-01-02 03:04:05');
        $entry->method('getDuration')->willReturn(12.5);
        $entry->method('getRequestHeaders')->willReturn($requestHeaders ?? (string) json_encode([
            'Authorization' => 'Bearer ' . SanitizerInterface::MASK,
            'Content-Type' => 'application/json',
            'Content-Length' => '13',
        ]));
        $entry->method('getRequestBody')->willReturn('{"note":"it\'s"}');
        $entry->method('getResponseCode')->willReturn($responseCode);
        $entry->method('getResponseHeaders')->willReturn((string) json_encode(['Content-Type' => 'text/plain']));
        $entry->method('getResponseBody')->willReturn('{"id":1}');

        return $entry;
    }

    /**
     * @return EndpointUrlResolverInterface&MockObject
     */
    private function urlResolver(): EndpointUrlResolverInterface&MockObject
    {
        $resolver = $this->createMock(EndpointUrlResolverInterface::class);
        $resolver->method('resolve')->willReturn('https://shop.test/rest/default/V1/orders?x=1');

        return $resolver;
    }
}
