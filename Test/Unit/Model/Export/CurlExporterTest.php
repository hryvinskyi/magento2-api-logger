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
use Hryvinskyi\ApiLogger\Model\Export\CurlExporter;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use PHPUnit\Framework\TestCase;

/**
 * A stored call as a shell command that replays it
 */
class CurlExporterTest extends TestCase
{
    use ExportFixture;

    /**
     * @var CurlExporter
     */
    private CurlExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new CurlExporter($this->urlResolver(), new StoredHeaders());
    }

    public function testTheCommandHoldsTheMethodAndTheQuotedUrl(): void
    {
        $export = $this->exporter->export($this->storedCall());

        self::assertStringStartsWith(
            "curl -X POST 'https://shop.test/rest/default/V1/orders?x=1' ",
            $export['content']
        );
        self::assertStringEndsWith("\n", $export['content']);
        self::assertSame('rest-call-42.sh', $export['filename']);
        self::assertSame('text/plain', $export['contentType']);
    }

    public function testAMaskedValueBecomesTheSecretVariableAndTheMaskNeverAppears(): void
    {
        $content = $this->exporter->export($this->storedCall())['content'];

        self::assertStringContainsString("-H 'Authorization: Bearer '\"\$API_SECRET\"''", $content);
        self::assertStringNotContainsString(SanitizerInterface::MASK, $content);
    }

    public function testAMaskInsideTheBodyBecomesTheSecretVariableToo(): void
    {
        $entry = $this->createMock(LogEntryInterface::class);
        $entry->method('getMethod')->willReturn('POST');
        $entry->method('getRequestHeaders')->willReturn(null);
        $entry->method('getRequestBody')->willReturn('{"password":"' . SanitizerInterface::MASK . '"}');

        $content = $this->exporter->export($entry)['content'];

        self::assertStringContainsString("--data-raw '{\"password\":\"'\"\$API_SECRET\"'\"}'", $content);
        self::assertStringNotContainsString(SanitizerInterface::MASK, $content);
    }

    public function testASingleQuoteInTheBodyIsQuotedSafely(): void
    {
        $content = $this->exporter->export($this->storedCall())['content'];

        self::assertStringContainsString("--data-raw '{\"note\":\"it'\\''s\"}'", $content);
    }

    public function testHeadersTheClientSetsItselfAreSkipped(): void
    {
        $content = $this->exporter->export($this->storedCall((string) json_encode([
            'Host' => 'shop.test',
            'Content-Length' => '13',
            'Connection' => 'keep-alive',
            'Accept-Encoding' => 'gzip',
            'Accept' => 'text/plain',
        ])))['content'];

        self::assertStringContainsString("-H 'Accept: text/plain'", $content);
        self::assertStringNotContainsString('Host:', $content);
        self::assertStringNotContainsString('Content-Length', $content);
        self::assertStringNotContainsString('Connection', $content);
        self::assertStringNotContainsString('Accept-Encoding', $content);
    }

    public function testACallWithoutABodyHasNoDataOption(): void
    {
        $entry = $this->createMock(LogEntryInterface::class);
        $entry->method('getMethod')->willReturn('GET');
        $entry->method('getRequestHeaders')->willReturn(null);
        $entry->method('getRequestBody')->willReturn(null);

        self::assertStringNotContainsString('--data-raw', $this->exporter->export($entry)['content']);
    }

    public function testTheLabelIsHumanReadable(): void
    {
        self::assertSame('cURL command', $this->exporter->getLabel());
    }
}
