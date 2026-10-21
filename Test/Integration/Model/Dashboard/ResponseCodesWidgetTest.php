<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterfaceFactory;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\Dashboard\ResponseCodesWidget;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The response-codes widget over rows of the real table
 *
 * The widget counts every stored call of its period, and the test database keeps calls from other runs, so the rows
 * are seeded in a week long past that nothing else writes to.
 */
#[DbIsolation(true)]
class ResponseCodesWidgetTest extends TestCase
{
    private const PERIOD_DAYS = 7;
    private const PERIOD_END = '2001-01-11 00:00:00';
    private const INSIDE = '2001-01-10 12:00:00';

    /**
     * @var ResponseCodesWidget
     */
    private ResponseCodesWidget $widget;

    protected function setUp(): void
    {
        $this->widget = self::service(ResponseCodesWidget::class);
    }

    public function testCallsOfThePeriodAreCountedByClassOfStatus(): void
    {
        $this->seed([
            [200, self::INSIDE],
            [200, '2001-01-04 00:00:00'],
            [404, self::INSIDE],
            [500, '2001-01-10 23:59:59'],
            [null, self::INSIDE],
            [200, self::PERIOD_END],
            [503, '2001-01-03 23:59:59'],
        ]);

        $data = $this->widget->getData($this->query([RestCallRecorder::CHANNEL]));

        self::assertSame(['2xx', '3xx', '4xx', '5xx'], $data['labels'] ?? null);
        $datasets = $data['datasets'] ?? null;
        self::assertIsArray($datasets);
        self::assertCount(1, $datasets);
        $dataset = $datasets[0];
        self::assertIsArray($dataset);
        self::assertSame(
            [2, 0, 1, 1],
            $dataset['values'] ?? null,
            'The period starts at its first moment and ends before its last; a call without a status is not counted.'
        );
    }

    public function testNothingIsShownWhenTheRestChannelIsNotSelected(): void
    {
        $this->seed([[200, self::INSIDE], [404, self::INSIDE]]);

        self::assertSame(
            ['labels' => [], 'datasets' => []],
            $this->widget->getData($this->query(['other_channel']))
        );
    }

    public function testNothingIsShownForAPeriodWithoutCalls(): void
    {
        $this->seed([[null, self::INSIDE]]);

        self::assertSame(
            ['labels' => [], 'datasets' => []],
            $this->widget->getData($this->query([RestCallRecorder::CHANNEL]))
        );
    }

    /**
     * The week ending at the seeded period's end, for the channels
     *
     * @param list<string> $channels
     * @return DashboardQueryInterface
     */
    private function query(array $channels): DashboardQueryInterface
    {
        $end = new \DateTimeImmutable(self::PERIOD_END, new \DateTimeZone('UTC'));

        return self::service(DashboardQueryInterfaceFactory::class)->create([
            'periodDays' => self::PERIOD_DAYS,
            'channels' => $channels,
            'toMs' => $end->getTimestamp() * 1000,
        ]);
    }

    /**
     * Store calls answered with the status at the moment (UTC)
     *
     * @param list<array{0: int|null, 1: string}> $calls
     * @return void
     */
    private function seed(array $calls): void
    {
        $resource = self::service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName(LogEntryResource::TABLE);
        foreach ($calls as [$code, $createdAt]) {
            $connection->insert($table, [
                LogEntryInterface::ENDPOINT => '/V1/products',
                LogEntryInterface::METHOD => 'GET',
                LogEntryInterface::RESPONSE_CODE => $code,
                LogEntryInterface::CREATED_AT => $createdAt,
            ]);
        }
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
