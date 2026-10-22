<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Body;

use Hryvinskyi\ApiLogger\Model\Body\ReadableBody;
use PHPUnit\Framework\TestCase;

/**
 * A stored body as people read it
 */
class ReadableBodyTest extends TestCase
{
    /**
     * @var ReadableBody
     */
    private ReadableBody $readable;

    protected function setUp(): void
    {
        $this->readable = new ReadableBody();
    }

    public function testAJsonObjectIsLaidOutOverIndentedLines(): void
    {
        self::assertSame(
            "{\n    \"sku\": \"a/b\",\n    \"tags\": [\n        1,\n        2\n    ]\n}",
            $this->readable->text('{"sku":"a\/b","tags":[1,2]}')
        );
    }

    public function testAJsonArrayIsLaidOutOverIndentedLines(): void
    {
        self::assertSame("[\n    1,\n    2\n]", $this->readable->text('[1,2]'));
    }

    public function testAnAlreadyIndentedBodyReadsTheSame(): void
    {
        self::assertSame(
            "{\n    \"a\": [\n        true,\n        null\n    ]\n}",
            $this->readable->text("{\r\n  \"a\" :\t[ true ,\n null ]\n}")
        );
    }

    public function testNumbersReadExactlyAsTheCallSentThem(): void
    {
        self::assertSame(
            "{\n    \"id\": 18446744073709551616,\n    \"price\": 1.50,\n    \"qty\": 1.0,\n    \"rate\": 1e-7\n}",
            $this->readable->text('{"id":18446744073709551616,"price":1.50,"qty":1.0,"rate":1e-7}'),
            'Decoded, an integer past PHP\'s range would read as a float and decimals would lose their zeros'
        );
    }

    public function testStringsReadWithSlashesAndLettersWrittenOut(): void
    {
        self::assertSame(
            "{\n    \"name\": \"Zażółć\",\n    \"path\": \"a/b\",\n    \"quote\": \"say \\\"hi\\\"\\n\"\n}",
            $this->readable->text('{"name":"Za\u017c\u00f3\u0142\u0107","path":"a\/b","quote":"say \"hi\"\n"}')
        );
    }

    public function testPunctuationInsideAStringIsNotLaidOut(): void
    {
        self::assertSame(
            "{\n    \"note\": \"{a: [1, 2]}\"\n}",
            $this->readable->text('{"note":"{a: [1, 2]}"}')
        );
    }

    public function testEmptyObjectsAndArraysStayOnTheirLine(): void
    {
        self::assertSame(
            "{\n    \"a\": {},\n    \"b\": []\n}",
            $this->readable->text('{"a":{ },"b":[]}')
        );
        self::assertSame('{}', $this->readable->text('{}'));
    }

    public function testTextThatIsNotJsonStaysAsItIs(): void
    {
        self::assertSame("plain text\nsecond line", $this->readable->text("plain text\nsecond line"));
        self::assertSame('{"broken":', $this->readable->text('{"broken":'));
    }

    public function testAJsonScalarStaysAsItIs(): void
    {
        self::assertSame('42', $this->readable->text('42'));
        self::assertSame('"42"', $this->readable->text('"42"'));
        self::assertSame('true', $this->readable->text('true'));
    }

    public function testNothingIsReadableFromANullOrEmptyBody(): void
    {
        self::assertSame('', $this->readable->text(null));
        self::assertSame('', $this->readable->text(''));
    }
}
