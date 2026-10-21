<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model;

use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Stored UTC timestamps shown in the configured timezone, through the real container
 *
 * The timezone is read for the current store, so the fixture sets it there.
 */
class DateFormatterTest extends TestCase
{
    /**
     * @var DateFormatterInterface
     */
    private DateFormatterInterface $formatter;

    protected function setUp(): void
    {
        $formatter = Bootstrap::getObjectManager()->get(DateFormatterInterface::class);
        self::assertInstanceOf(DateFormatterInterface::class, $formatter);
        $this->formatter = $formatter;
    }

    #[Config('general/locale/timezone', 'Europe/Kyiv', ScopeInterface::SCOPE_STORE)]
    public function testAStoredUtcTimeIsShownInTheConfiguredTimezone(): void
    {
        self::assertSame('2026-01-02T05:04:05+02:00', $this->formatter->formatIso8601('2026-01-02 03:04:05'));
    }

    #[Config('general/locale/timezone', 'Europe/Kyiv', ScopeInterface::SCOPE_STORE)]
    public function testNoTimeOrAnInvalidOneIsShownAsNothing(): void
    {
        self::assertSame('', $this->formatter->formatIso8601(''));
        self::assertSame('', $this->formatter->formatIso8601(null));
        self::assertSame('', $this->formatter->formatIso8601('0000-00-00 00:00:00'));
        self::assertSame('', $this->formatter->formatIso8601('not a date'));
        self::assertSame('', $this->formatter->formatDateTime('not a date'));
    }
}
