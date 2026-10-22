<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Migration;

use Hryvinskyi\ActivityLog\Model\Config as ActivityLogConfig;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\Http\Masker;
use Hryvinskyi\ApiLogger\Model\Migration\JournalBackfill;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A backfill stopped between an entry's activity and the rewrite that links the entry keeps neither
 *
 * The run is stopped by masking that fails after the activity was recorded. It runs outside a test transaction, as an
 * upgrade does: inside one the adapter only marks a nested rollback, and the activity would stay visible. The run
 * reads every unlinked entry, so entries other runs left are journaled before it reaches the test's own. The test's
 * entry, and any activity naming its endpoint, are removed when the test ends.
 */
#[DbIsolation(false)]
class JournalBackfillAtomicityTest extends TestCase
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var int|null
     */
    private ?int $entryId = null;

    /**
     * @var string
     */
    private string $path = '';

    protected function setUp(): void
    {
        $this->resourceConnection = self::service(ResourceConnection::class);
        self::service(ReinitableConfigInterface::class)->setValue(ActivityLogConfig::XML_PATH_ENABLED, '1');
    }

    protected function tearDown(): void
    {
        if ($this->entryId !== null) {
            $this->resourceConnection->getConnection()->delete(
                $this->resourceConnection->getTableName(LogEntryResource::TABLE),
                [LogEntryInterface::ENTITY_ID . ' = ?' => $this->entryId]
            );
        }
        if ($this->path !== '') {
            $this->resourceConnection->getConnection()->delete(
                $this->resourceConnection->getTableName(ActivityResource::TABLE),
                ['action = ?' => 'POST ' . $this->path]
            );
        }
        self::service(ReinitableConfigInterface::class)->reinit();
    }

    public function testAnEntryWhoseRewriteFailsKeepsNoActivityAndStaysUnlinked(): void
    {
        $this->path = '/V1/backfill-atomicity-' . bin2hex(random_bytes(4));
        $this->entryId = $this->insertEntry();
        $masker = $this->createMock(Masker::class);
        $masker->method('text')->willReturnCallback(fn (string $text): string => str_contains($text, 'plain')
            ? throw new \RuntimeException('The process stops here.')
            : $text);
        $backfill = Bootstrap::getObjectManager()->create(JournalBackfill::class, ['masker' => $masker]);
        self::assertInstanceOf(JournalBackfill::class, $backfill);

        try {
            $backfill->run();
            self::fail('The failing rewrite should stop the run.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The process stops here.', $exception->getMessage());
        }

        self::assertSame(0, $this->activitiesOfThePath(), 'The activity of the stopped entry is rolled back.');
        self::assertNull($this->activityIdOf($this->entryId), 'The entry stays unlinked, so the next run journals it.');
        self::assertSame(0, $this->resourceConnection->getConnection()->getTransactionLevel());
    }

    /**
     * Insert an unlinked entry with a body the masking reads
     *
     * @return int
     */
    private function insertEntry(): int
    {
        $connection = $this->resourceConnection->getConnection();
        self::assertInstanceOf(Mysql::class, $connection);
        $table = $this->resourceConnection->getTableName(LogEntryResource::TABLE);
        $connection->insert($table, [
            LogEntryInterface::ENDPOINT => '/rest' . $this->path,
            LogEntryInterface::METHOD => 'POST',
            LogEntryInterface::REQUEST_BODY => '{"note":"plain"}',
            LogEntryInterface::RESPONSE_CODE => 200,
            LogEntryInterface::IS_EXCEPTION => 0,
            LogEntryInterface::CREATED_AT => '2025-01-15 10:00:00',
        ]);
        $id = $connection->lastInsertId($table);
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * How many activities name the test entry's endpoint
     *
     * @return int
     */
    private function activitiesOfThePath(): int
    {
        $connection = $this->resourceConnection->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['COUNT(*)'])
                ->where('action = ?', 'POST ' . $this->path)
        );
    }

    /**
     * The activity an entry is linked to, or null
     *
     * @param int $entryId
     * @return int|null
     */
    private function activityIdOf(int $entryId): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName(LogEntryResource::TABLE),
                    [LogEntryInterface::ACTIVITY_ID]
                )
                ->where(LogEntryInterface::ENTITY_ID . ' = ?', $entryId)
        );

        return is_numeric($id) ? (int) $id : null;
    }

    /**
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
