<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Migration;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ActivityLog\Model\Config as ActivityLogConfig;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\Migration\JournalBackfill;
use Hryvinskyi\ApiLogger\Model\Migration\SettingsMigration;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Hryvinskyi\ApiLogger\Setup\Patch\Data\JournalExistingEntries;
use Hryvinskyi\ApiLogger\Setup\Patch\Data\MoveSettingsToActivityLog;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Journals and masks the calls logged before 2.0, against the real log table, journal and sanitizer
 *
 * The backfill reads every unlinked entry of the table, including entries other tests left there; the test's
 * transaction is rolled back so none of them stays journaled or rewritten. The shared sanitizer reads the secret list
 * before the settings move and masks with the merged list after it, as an upgrade that masks anything earlier does.
 *
 * @phpstan-type Row array<string, mixed>
 */
#[DbIsolation(true)]
class JournalBackfillTest extends TestCase
{
    private const CUSTOM_SECRET = 'x_partner_signature';
    private const TOKEN_ENDPOINT = '/rest/V1/integration/admin/token';
    private const LEGACY_STORE_ENDPOINT = '/rest/default/V1/products?searchCriteria[pageSize]=5';
    private const RESET_LINK_TOKEN = 'f3c9a1e07b2d4c58a6e1d0b9c8f7e6d5';
    private const RESET_PATTERN = '["GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken"]';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var string
     */
    private string $probe;

    protected function setUp(): void
    {
        $this->resourceConnection = self::service(ResourceConnection::class);
        $this->probe = 'backfill-probe-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        self::service(ReinitableConfigInterface::class)->reinit();
    }

    public function testEveryOldEntryIsJournaledAndMaskedWithTheMergedSecretList(): void
    {
        $this->saveConfig('api_logger/security/secret_fields', self::CUSTOM_SECRET);
        self::service(ReinitableConfigInterface::class)->reinit();
        self::assertFalse(
            self::service(SanitizerInterface::class)->isSecretField(self::CUSTOM_SECRET),
            'The activity log list should not cover the 1.x name before the settings are migrated.'
        );
        self::service(SettingsMigration::class)->run();
        self::service(ReinitableConfigInterface::class)->setValue(ActivityLogConfig::XML_PATH_ENABLED, '1');

        $password = $this->insertEntry('POST', '/rest/V1/' . $this->probe . '/login', [
            LogEntryInterface::REQUEST_BODY => '{"username":"admin","password":"plain-password"}',
        ]);
        $signed = $this->insertEntry('POST', '/rest/V1/' . $this->probe . '/orders', [
            LogEntryInterface::REQUEST_BODY => '{"order":"100","' . self::CUSTOM_SECRET . '":"plain-signature"}',
        ]);
        $token = $this->insertEntry('POST', self::TOKEN_ENDPOINT, [
            LogEntryInterface::REQUEST_BODY => '{"username":"admin","password":"plain-password"}',
            LogEntryInterface::RESPONSE_BODY => '"plain-admin-token"',
        ]);
        $legacy = $this->insertEntry('GET', self::LEGACY_STORE_ENDPOINT, [
            LogEntryInterface::RESPONSE_BODY => '{"items":[],"total_count":0}',
        ]);

        self::service(JournalBackfill::class)->run();

        foreach ([$password, $signed, $token, $legacy] as $entryId) {
            $entry = $this->entry($entryId);
            self::assertNotNull($entry[LogEntryInterface::ACTIVITY_ID], 'Entry ' . $entryId . ' should be journaled.');
            $activity = $this->activity($this->int($entry[LogEntryInterface::ACTIVITY_ID]));
            self::assertSame(RestCallRecorder::CHANNEL, $activity['channel']);
            self::assertSame(ActorType::Anonymous->value, $activity['actor_type']);
            self::assertSame(JournalBackfill::UNKNOWN_ACTOR, $activity['actor_name']);
            self::assertSame($activity['correlation_id'], $entry[LogEntryInterface::CORRELATION_ID]);
        }
        self::assertSame('GET /V1/products', $this->activityOf($legacy)['action']);
        self::assertSame('POST /V1/' . $this->probe . '/login', $this->activityOf($password)['action']);

        self::assertSame(
            ['username' => 'admin', 'password' => SanitizerInterface::MASK],
            $this->json($this->entry($password)[LogEntryInterface::REQUEST_BODY])
        );
        self::assertSame(
            ['order' => '100', self::CUSTOM_SECRET => SanitizerInterface::MASK],
            $this->json($this->entry($signed)[LogEntryInterface::REQUEST_BODY]),
            'The 1.x secret name should be masked: the backfill masks with the merged list.'
        );
        self::assertSame(SanitizerInterface::MASK, $this->entry($token)[LogEntryInterface::RESPONSE_BODY]);
        self::assertSame(
            ['username' => 'admin', 'password' => SanitizerInterface::MASK],
            $this->json($this->entry($token)[LogEntryInterface::REQUEST_BODY])
        );
        self::assertSame('{"items":[],"total_count":0}', $this->entry($legacy)[LogEntryInterface::RESPONSE_BODY]);
    }

    #[Config('api_logger/general/enabled', '0', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::RESET_PATTERN, ScopeInterface::SCOPE_STORE)]
    public function testASecretRouteParameterInAnOldPathIsMaskedInTheEntryAndTheAction(): void
    {
        self::service(ReinitableConfigInterface::class)->setValue(ActivityLogConfig::XML_PATH_ENABLED, '1');
        $reset = $this->insertEntry(
            'GET',
            '/rest/V1/customers/42/password/resetLinkToken/' . self::RESET_LINK_TOKEN,
            []
        );

        self::service(JournalBackfill::class)->run();

        self::assertSame(
            '/rest/V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK,
            $this->entry($reset)[LogEntryInterface::ENDPOINT],
            'The path is masked by the selected endpoint pattern even while nothing new is logged.'
        );
        self::assertSame(
            'GET /V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK,
            $this->activityOf($reset)['action']
        );
    }

    public function testASecondRunJournalsNothingTwice(): void
    {
        self::service(ReinitableConfigInterface::class)->setValue(ActivityLogConfig::XML_PATH_ENABLED, '1');
        $login = $this->insertEntry('POST', '/rest/V1/' . $this->probe . '/login', [
            LogEntryInterface::REQUEST_BODY => '{"password":"plain-password"}',
        ]);
        $legacy = $this->insertEntry('GET', self::LEGACY_STORE_ENDPOINT, []);
        $backfill = self::service(JournalBackfill::class);

        $backfill->run();
        $first = [$this->entry($login), $this->entry($legacy)];
        $lastActivityId = $this->lastActivityId();
        $backfill->run();

        self::assertSame($first, [$this->entry($login), $this->entry($legacy)]);
        self::assertSame(
            0,
            $this->countBackfilledActivitiesAfter(
                $lastActivityId,
                ['POST /V1/' . $this->probe . '/login', 'GET /V1/products']
            ),
            'A second run should create no activity for an entry already journaled.'
        );
    }

    #[Config(ActivityLogConfig::XML_PATH_ENABLED, '0')]
    public function testEntriesAreMaskedWhileTheActivityLogIsSwitchedOff(): void
    {
        $login = $this->insertEntry('POST', '/rest/V1/' . $this->probe . '/login', [
            LogEntryInterface::REQUEST_HEADERS => '{"Authorization":"Bearer plain-bearer","Accept":"application/json"}',
            LogEntryInterface::REQUEST_BODY => '{"username":"admin","password":"plain-password"}',
        ]);
        $token = $this->insertEntry('POST', self::TOKEN_ENDPOINT, [
            LogEntryInterface::RESPONSE_BODY => '"plain-admin-token"',
        ]);
        $backfill = self::service(JournalBackfill::class);

        self::assertSame(0, $backfill->run(), 'Nothing is journaled while the activity log is switched off.');

        $masked = $this->entry($login);
        self::assertNull($masked[LogEntryInterface::ACTIVITY_ID]);
        self::assertNull($masked[LogEntryInterface::CORRELATION_ID]);
        self::assertSame(
            ['Authorization' => 'Bearer ' . SanitizerInterface::MASK, 'Accept' => 'application/json'],
            $this->json($masked[LogEntryInterface::REQUEST_HEADERS])
        );
        self::assertSame(
            ['username' => 'admin', 'password' => SanitizerInterface::MASK],
            $this->json($masked[LogEntryInterface::REQUEST_BODY])
        );
        self::assertSame(SanitizerInterface::MASK, $this->entry($token)[LogEntryInterface::RESPONSE_BODY]);
        self::assertSame(0, $this->countActivities('POST /V1/' . $this->probe . '/login'));

        $backfill->run();

        self::assertSame($masked, $this->entry($login), 'Masking a masked entry again should change nothing.');
    }

    public function testBothPatchesCompleteOnAFreshStoreWithNothingToMigrate(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete($this->resourceConnection->getTableName(LogEntryResource::TABLE));
        $connection->delete(
            $this->resourceConnection->getTableName('core_config_data'),
            ['path LIKE ?' => 'api_logger/%']
        );

        self::service(MoveSettingsToActivityLog::class)->apply();
        self::service(JournalExistingEntries::class)->apply();

        self::assertSame(0, self::service(JournalBackfill::class)->run());
        self::assertSame(
            '0',
            $connection->fetchOne(
                $connection->select()
                    ->from($this->resourceConnection->getTableName(LogEntryResource::TABLE), ['COUNT(*)'])
            )
        );
        self::assertSame(
            [],
            $connection->fetchCol(
                $connection->select()
                    ->from($this->resourceConnection->getTableName('core_config_data'), ['value'])
                    ->where('path = ?', ConfigInterface::XML_PATH_RETENTION_DAYS)
            ),
            'A fresh store gets no retention it did not have.'
        );
    }

    /**
     * Store a 1.x setting for the default scope
     *
     * @param string $path
     * @param string $value
     * @return void
     */
    private function saveConfig(string $path, string $value): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName('core_config_data'),
            ['scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 'scope_id' => 0, 'path' => $path, 'value' => $value],
            ['value']
        );
    }

    /**
     * Insert an entry as 1.x stored it: the raw REST path, nothing masked, no activity
     *
     * @param string $method
     * @param string $endpoint
     * @param array<string, string> $fields
     * @return int
     */
    private function insertEntry(string $method, string $endpoint, array $fields): int
    {
        $connection = $this->resourceConnection->getConnection();
        self::assertInstanceOf(Mysql::class, $connection);
        $table = $this->resourceConnection->getTableName(LogEntryResource::TABLE);
        $connection->insert($table, $fields + [
            LogEntryInterface::ENDPOINT => $endpoint,
            LogEntryInterface::METHOD => $method,
            LogEntryInterface::RESPONSE_CODE => 200,
            LogEntryInterface::DURATION => 12.5,
            LogEntryInterface::IS_EXCEPTION => 0,
            LogEntryInterface::STORE_ID => $this->int(
                self::service(StoreManagerInterface::class)->getDefaultStoreView()?->getId()
            ),
            LogEntryInterface::IP_ADDRESS => '203.0.113.7',
            LogEntryInterface::CREATED_AT => '2025-01-15 10:00:00',
        ]);

        return $this->int($connection->lastInsertId($table));
    }

    /**
     * An entry as stored
     *
     * @param int $entryId
     * @return Row
     */
    private function entry(int $entryId): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->row($connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(LogEntryResource::TABLE))
                ->where(LogEntryInterface::ENTITY_ID . ' = ?', $entryId)
        ));
    }

    /**
     * The activity an entry is linked to
     *
     * @param int $entryId
     * @return Row
     */
    private function activityOf(int $entryId): array
    {
        return $this->activity($this->int($this->entry($entryId)[LogEntryInterface::ACTIVITY_ID]));
    }

    /**
     * An activity as stored
     *
     * @param int $activityId
     * @return Row
     */
    private function activity(int $activityId): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->row($connection->fetchRow(
            $connection->select()
                ->from($this->resourceConnection->getTableName(ActivityResource::TABLE))
                ->where('entity_id = ?', $activityId)
        ));
    }

    /**
     * The highest activity id so far
     *
     * @return int
     */
    private function lastActivityId(): int
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->int($connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['MAX(entity_id)'])
        ));
    }

    /**
     * Activities the backfill created after an id, for the actions
     *
     * @param int $afterId
     * @param list<string> $actions
     * @return int
     */
    private function countBackfilledActivitiesAfter(int $afterId, array $actions): int
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->int($connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['COUNT(*)'])
                ->where('entity_id > ?', $afterId)
                ->where('actor_name = ?', JournalBackfill::UNKNOWN_ACTOR)
                ->where('action IN (?)', $actions)
        ));
    }

    /**
     * Activities recorded for an action
     *
     * @param string $action
     * @return int
     */
    private function countActivities(string $action): int
    {
        $connection = $this->resourceConnection->getConnection();

        return $this->int($connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['COUNT(*)'])
                ->where('action = ?', $action)
        ));
    }

    /**
     * A row fetched from the database
     *
     * @param mixed $row
     * @return Row
     */
    private function row(mixed $row): array
    {
        self::assertIsArray($row);

        return array_combine(array_map('strval', array_keys($row)), array_values($row));
    }

    /**
     * Stored JSON text, decoded
     *
     * @param mixed $text
     * @return array<mixed>
     */
    private function json(mixed $text): array
    {
        self::assertIsString($text);
        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * A stored number, asserted numeric
     *
     * @param mixed $value
     * @return int
     */
    private function int(mixed $value): int
    {
        self::assertIsNumeric($value);

        return (int) $value;
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
