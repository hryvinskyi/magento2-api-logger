<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Http;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use PHPUnit\Framework\TestCase;

/**
 * Stored header JSON read back as ordered name/value pairs
 */
class StoredHeadersTest extends TestCase
{
    /**
     * @var StoredHeaders
     */
    private StoredHeaders $storedHeaders;

    protected function setUp(): void
    {
        $this->storedHeaders = new StoredHeaders();
    }

    public function testHeadersKeepTheirStoredOrder(): void
    {
        $headers = $this->storedHeaders->read('{"Host":"shop.test","Accept":"*\/*","X-Trace":"1"}');

        self::assertSame(['Host', 'Accept', 'X-Trace'], array_column($headers, 'name'));
        self::assertSame(['shop.test', '*/*', '1'], array_column($headers, 'value'));
    }

    public function testARepeatedHeaderJoinsItsValuesWithACommaAndASpace(): void
    {
        $headers = $this->storedHeaders->read('{"Accept":["application/json","text/plain"]}');

        self::assertSame(
            [['name' => 'Accept', 'value' => 'application/json, text/plain', 'masked' => false]],
            $headers
        );
    }

    public function testAValueHoldingTheMaskIsFlaggedMasked(): void
    {
        $headers = $this->storedHeaders->read(
            (string) json_encode(['Authorization' => 'Bearer ' . SanitizerInterface::MASK, 'Accept' => 'text/plain'])
        );

        self::assertTrue($headers[0]['masked']);
        self::assertSame('Bearer ' . SanitizerInterface::MASK, $headers[0]['value']);
        self::assertFalse($headers[1]['masked']);
    }

    public function testAMaskInsideOneValueOfARepeatedHeaderFlagsTheHeader(): void
    {
        $headers = $this->storedHeaders->read(
            (string) json_encode(['Cookie' => ['a=1', 'b=' . SanitizerInterface::MASK]])
        );

        self::assertTrue($headers[0]['masked']);
    }

    public function testAnythingButAJsonObjectOrListReadsAsNoHeaders(): void
    {
        self::assertSame([], $this->storedHeaders->read(null));
        self::assertSame([], $this->storedHeaders->read(''));
        self::assertSame([], $this->storedHeaders->read('{not json'));
        self::assertSame([], $this->storedHeaders->read('"text"'));
        self::assertSame([], $this->storedHeaders->read('[]'));
    }

    public function testANonScalarValueReadsAsEmptyText(): void
    {
        $headers = $this->storedHeaders->read('{"Odd":["x",{"a":1}],"Null":null,"Num":5}');

        self::assertSame(['x, ', '', '5'], array_column($headers, 'value'));
    }

    public function testAValueIsFoundByNameWithoutCase(): void
    {
        $json = '{"Content-Type":"application/json","Host":"shop.test"}';

        self::assertSame('application/json', $this->storedHeaders->value($json, 'content-type'));
        self::assertSame('shop.test', $this->storedHeaders->value($json, 'HOST'));
    }

    public function testAnAbsentHeaderIsEmptyText(): void
    {
        self::assertSame('', $this->storedHeaders->value('{"Host":"shop.test"}', 'Accept'));
        self::assertSame('', $this->storedHeaders->value(null, 'Host'));
        self::assertSame('', $this->storedHeaders->value('{not json', 'Host'));
    }
}
