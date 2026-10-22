<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Body;

use Hryvinskyi\ApiLogger\Model\Body\BodyPager;
use Hryvinskyi\ApiLogger\Model\Body\ReadableBody;
use PHPUnit\Framework\TestCase;

/**
 * A stored body as numbered, paged, searchable lines
 */
class BodyPagerTest extends TestCase
{
    /**
     * @var BodyPager
     */
    private BodyPager $pager;

    protected function setUp(): void
    {
        $this->pager = new BodyPager(new ReadableBody());
    }

    public function testAJsonBodyIsPagedByTheLinesItReadsAs(): void
    {
        $result = $this->pager->page('{"sku":"a","qty":2}', 1);

        self::assertSame(['{', '    "sku": "a",', '    "qty": 2', '}'], array_column($result['lines'], 'text'));
    }

    public function testALongBodyIsSplitIntoPagesOfFiveHundredNumberedLines(): void
    {
        $result = $this->pager->page($this->lines(1201), 3);

        self::assertSame(3, $result['pages']);
        self::assertSame(3, $result['page']);
        self::assertSame(1201, $result['totalLines']);
        self::assertCount(201, $result['lines']);
        self::assertSame(['n' => 1001, 'text' => 'line 1001'], $result['lines'][0]);
        self::assertSame(['n' => 1201, 'text' => 'line 1201'], $result['lines'][200]);
    }

    public function testTheFirstPageHoldsTheFirstFiveHundredLines(): void
    {
        $result = $this->pager->page($this->lines(1201), 1);

        self::assertCount(500, $result['lines']);
        self::assertSame(['n' => 1, 'text' => 'line 1'], $result['lines'][0]);
        self::assertSame(['n' => 500, 'text' => 'line 500'], $result['lines'][499]);
    }

    public function testPageSizeIsAConstructorArgument(): void
    {
        $result = (new BodyPager(new ReadableBody(), pageSize: 2))->page("a\nb\nc\nd\ne", 3);

        self::assertSame(3, $result['pages']);
        self::assertSame([['n' => 5, 'text' => 'e']], $result['lines']);
    }

    public function testCarriageReturnLineFeedAndCarriageReturnSplitLikeLineFeed(): void
    {
        $result = $this->pager->page("a\r\nb\rc\nd", 1);

        self::assertSame(4, $result['totalLines']);
        self::assertSame(['a', 'b', 'c', 'd'], array_column($result['lines'], 'text'));
    }

    public function testPageZeroAndNegativePagesGivePageOne(): void
    {
        self::assertSame(1, $this->pager->page($this->lines(1201), 0)['page']);
        self::assertSame(1, $this->pager->page($this->lines(1201), -4)['page']);
        self::assertSame(1, $this->pager->page($this->lines(1201), 0)['lines'][0]['n']);
    }

    public function testAPageBeyondTheLastGivesTheLastPage(): void
    {
        $result = $this->pager->page($this->lines(1201), 99);

        self::assertSame(3, $result['page']);
        self::assertSame(1001, $result['lines'][0]['n']);
    }

    public function testANullBodyHasOnePageAndNoLines(): void
    {
        $result = $this->pager->page(null, 1);

        self::assertSame([], $result['lines']);
        self::assertSame(1, $result['pages']);
        self::assertSame(1, $result['page']);
        self::assertSame(0, $result['totalLines']);
        self::assertSame(0, $result['bytes']);
        self::assertSame([], $result['hits']);
    }

    public function testAnEmptyBodyHasOnePageAndNoLines(): void
    {
        $result = $this->pager->page('', 5);

        self::assertSame([], $result['lines']);
        self::assertSame(1, $result['pages']);
        self::assertSame(0, $result['totalLines']);
        self::assertSame(0, $result['bytes']);
    }

    public function testBytesIsTheByteLengthOfTheStoredBodyNotOfItsPrettyPrint(): void
    {
        $body = '{"name":"Zażółć"}';

        self::assertSame(strlen($body), $this->pager->page($body, 1)['bytes']);
        self::assertSame(strlen('ąę'), $this->pager->page('ąę', 1)['bytes']);
    }

    public function testSearchIgnoresCaseAndAnswersLineNumbers(): void
    {
        $result = $this->pager->page("Alpha\nbeta\nALPHA beta\ngamma", 1, 'alpha');

        self::assertSame([1, 3], $result['hits']);
    }

    public function testSearchCoversTheWholeBodyNotOnlyTheShownPage(): void
    {
        $result = $this->pager->page($this->lines(1201), 1, 'line 1100');

        self::assertSame([1100], $result['hits']);
        self::assertSame(1, $result['page']);
    }

    public function testSearchNumbersTheLinesOfThePrettyPrint(): void
    {
        $result = $this->pager->page('{"a":1,"needle":2}', 1, 'needle');

        self::assertSame([3], $result['hits']);
    }

    public function testSearchIgnoresSurroundingBlanksAndAnEmptySearchHasNoHits(): void
    {
        self::assertSame([2], $this->pager->page("a\nneedle\nb", 1, '  needle ')['hits']);
        self::assertSame([], $this->pager->page("a\nneedle\nb", 1, '   ')['hits']);
        self::assertSame([], $this->pager->page("a\nneedle\nb", 1)['hits']);
    }

    public function testSearchWithoutAMatchHasNoHits(): void
    {
        self::assertSame([], $this->pager->page("a\nb", 1, 'zzz')['hits']);
    }

    public function testHitsStopAtTheConfiguredCap(): void
    {
        $result = (new BodyPager(new ReadableBody(), maxHits: 3))->page("x\nx\nx\nx\nx", 1, 'x');

        self::assertSame([1, 2, 3], $result['hits']);
    }

    public function testTheDefaultCapIsOneThousandHits(): void
    {
        $result = $this->pager->page(implode("\n", array_fill(0, 1200, 'x')), 1, 'x');

        self::assertCount(1000, $result['hits']);
        self::assertSame(1000, $result['hits'][999]);
    }

    /**
     * A body of non-JSON lines "line 1" … "line N"
     *
     * @param int $count
     * @return string
     */
    private function lines(int $count): string
    {
        return implode("\n", array_map(static fn (int $n): string => 'line ' . $n, range(1, $count)));
    }
}
