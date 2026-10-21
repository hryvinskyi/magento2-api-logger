<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Export;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Model\Export\RawHttpExporter;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use PHPUnit\Framework\TestCase;

/**
 * A stored call as the raw HTTP exchange
 */
class RawHttpExporterTest extends TestCase
{
    use ExportFixture;

    /**
     * @var RawHttpExporter
     */
    private RawHttpExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new RawHttpExporter($this->urlResolver(), new StoredHeaders());
    }

    public function testTheRequestLineHoldsTheMethodAndTheTargetWithTheFrontName(): void
    {
        $export = $this->exporter->export($this->storedCall());

        self::assertStringStartsWith("POST /rest/default/V1/orders?x=1 HTTP/1.1\r\n", $export['content']);
        self::assertSame('rest-call-42.txt', $export['filename']);
        self::assertSame('text/plain', $export['contentType']);
    }

    public function testTheHostComesFromTheUrlWhenTheStoredHeadersHaveNone(): void
    {
        $content = $this->exporter->export($this->storedCall())['content'];

        self::assertStringStartsWith(
            "POST /rest/default/V1/orders?x=1 HTTP/1.1\r\nHost: shop.test\r\n",
            $content
        );
        self::assertSame(1, substr_count($content, 'Host: '));
    }

    public function testAStoredHostHeaderIsNotRepeated(): void
    {
        $content = $this->exporter->export(
            $this->storedCall((string) json_encode(['host' => 'stored.test', 'Accept' => 'text/plain']))
        )['content'];

        self::assertSame(1, substr_count(strtolower($content), 'host: '));
        self::assertStringContainsString("host: stored.test\r\n", $content);
        self::assertStringNotContainsString('Host: shop.test', $content);
    }

    public function testRequestHeadersBodyAndTheResponseFollowInOrder(): void
    {
        $content = $this->exporter->export($this->storedCall())['content'];

        $expected = "POST /rest/default/V1/orders?x=1 HTTP/1.1\r\n"
            . "Host: shop.test\r\n"
            . "Authorization: Bearer " . SanitizerInterface::MASK . "\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: 13\r\n"
            . "\r\n"
            . "{\"note\":\"it's\"}\r\n\r\n"
            . "HTTP/1.1 200\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . '{"id":1}';
        self::assertSame($expected, $content);
    }

    public function testAMissingResponseCodeIsTripleZero(): void
    {
        self::assertStringContainsString(
            "\r\n\r\nHTTP/1.1 000\r\n",
            $this->exporter->export($this->storedCall(null, null))['content']
        );
    }

    public function testTheLabelIsHumanReadable(): void
    {
        self::assertSame('Raw HTTP (.txt)', $this->exporter->getLabel());
    }
}
