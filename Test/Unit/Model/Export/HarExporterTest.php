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
use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Hryvinskyi\ApiLogger\Model\Export\HarExporter;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A stored call as an HTTP Archive document
 */
class HarExporterTest extends TestCase
{
    use ExportFixture;

    /**
     * @var DateFormatterInterface&MockObject
     */
    private $dateFormatter;

    /**
     * @var HarExporter
     */
    private HarExporter $exporter;

    protected function setUp(): void
    {
        $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
        $this->exporter = new HarExporter($this->urlResolver(), new StoredHeaders(), $this->dateFormatter, new Json());
    }

    public function testTheDocumentIsValidJsonNamedAfterTheActivity(): void
    {
        $export = $this->exporter->export($this->storedCall());

        self::assertSame('rest-call-42.har', $export['filename']);
        self::assertSame('application/json', $export['contentType']);
        self::assertIsArray(json_decode($export['content'], true));
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testTheRequestCarriesTheResolvedUrlMethodAndQuery(): void
    {
        $call = $this->storedCall();

        self::assertSame('https://shop.test/rest/default/V1/orders?x=1', $this->harValue($call, 'request.url'));
        self::assertSame('POST', $this->harValue($call, 'request.method'));
        self::assertSame([['name' => 'x', 'value' => '1']], $this->harValue($call, 'request.queryString'));
        self::assertSame('application/json', $this->harValue($call, 'request.postData.mimeType'));
        self::assertSame('{"note":"it\'s"}', $this->harValue($call, 'request.postData.text'));
    }

    public function testStoredHeadersKeepTheirOrderAndMaskedValues(): void
    {
        self::assertSame(
            [
                ['name' => 'Authorization', 'value' => 'Bearer ' . SanitizerInterface::MASK],
                ['name' => 'Content-Type', 'value' => 'application/json'],
                ['name' => 'Content-Length', 'value' => '13'],
            ],
            $this->harValue($this->storedCall(), 'request.headers')
        );
    }

    public function testTheResponseCarriesItsStatusHeadersAndBody(): void
    {
        $call = $this->storedCall();

        self::assertSame(200, $this->harValue($call, 'response.status'));
        self::assertSame('text/plain', $this->harValue($call, 'response.content.mimeType'));
        self::assertSame('{"id":1}', $this->harValue($call, 'response.content.text'));
        self::assertSame(strlen('{"id":1}'), $this->harValue($call, 'response.content.size'));
        self::assertSame(
            [['name' => 'Content-Type', 'value' => 'text/plain']],
            $this->harValue($call, 'response.headers')
        );
    }

    public function testAMissingResponseCodeIsStatusZero(): void
    {
        self::assertSame(0, $this->harValue($this->storedCall(null, null), 'response.status'));
    }

    public function testTheStartIsTheFormattedCreationTimeAndTheDurationIsKept(): void
    {
        $this->dateFormatter->expects(self::atLeastOnce())->method('formatIso8601')->with('2026-01-02 03:04:05')
            ->willReturn('2026-01-02T03:04:05+00:00');
        $call = $this->storedCall();

        self::assertSame('2026-01-02T03:04:05+00:00', $this->harValue($call, 'startedDateTime'));
        self::assertEquals(12.5, $this->harValue($call, 'time'));
    }

    public function testAnUnformattableCreationTimeFallsBackToTheCurrentTime(): void
    {
        $this->dateFormatter->method('formatIso8601')->willReturn('');
        $started = $this->harValue($this->storedCall(), 'startedDateTime');

        self::assertIsString($started);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $started);
    }

    public function testAHeaderlessCallDefaultsItsMimeTypeToJson(): void
    {
        $call = $this->storedCall('{}');

        self::assertSame('application/json', $this->harValue($call, 'request.postData.mimeType'));
        self::assertSame([], $this->harValue($call, 'request.headers'));
    }

    public function testTheLabelIsHumanReadable(): void
    {
        self::assertSame('HAR (.har)', $this->exporter->getLabel());
    }

    /**
     * A value of the only HAR entry of the export of a call, addressed by a dotted path
     *
     * @param LogEntryInterface $call
     * @param string $path
     * @return mixed
     */
    private function harValue(LogEntryInterface $call, string $path): mixed
    {
        $har = json_decode($this->exporter->export($call)['content'], true);
        self::assertIsArray($har);
        self::assertIsArray($har['log']);
        self::assertIsArray($har['log']['entries']);
        self::assertCount(1, $har['log']['entries']);

        $value = $har['log']['entries'][0];
        foreach (explode('.', $path) as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        return $value;
    }
}
