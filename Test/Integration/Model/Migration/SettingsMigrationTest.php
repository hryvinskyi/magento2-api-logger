<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Migration;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Model\Migration\SettingsMigration;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Moves the 1.x settings against the real configuration table
 *
 * The migration reloads the configuration, so every test reloads it again when it ends: what it loaded inside the
 * test's transaction must not outlive the rollback.
 */
#[DbIsolation(true)]
class SettingsMigrationTest extends TestCase
{
    private const CONFIG_TABLE = 'core_config_data';
    private const STORE_ID = 1;
    private const OLD_SETTINGS = [
        'api_logger/security/secret_fields',
        'api_logger/general/secret_fields',
        'api_logger/cleanup/enabled',
        'api_logger/cleanup/retention_days',
        'api_logger/general/sanitize_secrets',
    ];
    private const KEPT_SETTING = 'api_logger/general/log_request_body';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    protected function setUp(): void
    {
        $this->resourceConnection = self::service(ResourceConnection::class);
    }

    protected function tearDown(): void
    {
        self::service(ReinitableConfigInterface::class)->reinit();
    }

    public function testAFreshStoreWithoutOldSettingsGetsNoRetentionItDidNotHave(): void
    {
        $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName(self::CONFIG_TABLE),
            ['path LIKE ?' => 'api_logger/%']
        );

        self::service(SettingsMigration::class)->run();

        self::assertSame([], $this->storedValues(ConfigInterface::XML_PATH_RETENTION_DAYS));
    }

    public function testAFreshStoreLeavesTheActivityLogSecretListUnwritten(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::CONFIG_TABLE);
        $connection->delete($table, ['path LIKE ?' => 'api_logger/%']);
        $connection->delete($table, ['path = ?' => SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS]);
        self::service(ReinitableConfigInterface::class)->reinit();

        self::service(SettingsMigration::class)->run();

        self::assertSame([], $this->storedValues(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS));
    }

    public function testOldNamesTheListAlreadyHoldsLeaveTheStoredListAsItWas(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName(self::CONFIG_TABLE),
            ['path LIKE ?' => 'api_logger/%']
        );
        $this->save(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS, 'Password, TOKEN,x_partner_secret');
        $this->save('api_logger/security/secret_fields', 'password,Token');
        $this->save('api_logger/general/secret_fields', 'X_Partner_Secret', ScopeInterface::SCOPE_STORES);
        self::service(ReinitableConfigInterface::class)->reinit();

        self::service(SettingsMigration::class)->run();

        self::assertSame(
            ['Password, TOKEN,x_partner_secret'],
            $this->storedValues(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS)
        );
        self::assertSame([], $this->storedValues('api_logger/security/secret_fields'));
    }

    public function testSecretFieldNamesOfEveryScopeJoinTheActivityLogListOnceAndLowercase(): void
    {
        $this->seedOneXSettings('1', '14');

        self::service(SettingsMigration::class)->run();

        self::assertSame(
            ['password,token,x_partner_secret,client_id,store_only_key'],
            $this->storedValues(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS)
        );
    }

    public function testTheMergedListIsWhatTheConfigurationAnswersRightAfterTheRun(): void
    {
        $this->seedOneXSettings('1', '14');
        self::assertSame(
            'password,token',
            self::service(ScopeConfigInterface::class)->getValue(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS),
            'The configuration should answer the list stored before the run.'
        );

        self::service(SettingsMigration::class)->run();

        self::assertSame(
            'password,token,x_partner_secret,client_id,store_only_key',
            self::service(ScopeConfigInterface::class)->getValue(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS)
        );
        self::assertNull(
            self::service(ScopeConfigInterface::class)->getValue('api_logger/cleanup/retention_days'),
            'A removed setting should not be answered from memory.'
        );
    }

    public function testTheCleanupRetentionBecomesTheRestChannelRetention(): void
    {
        $this->seedOneXSettings('1', '14');

        self::service(SettingsMigration::class)->run();

        self::assertSame(['14'], $this->storedValues(ConfigInterface::XML_PATH_RETENTION_DAYS));
    }

    public function testCleanupSwitchedOffKeepsCallsForGood(): void
    {
        $this->seedOneXSettings('0', '14');

        self::service(SettingsMigration::class)->run();

        self::assertSame(['0'], $this->storedValues(ConfigInterface::XML_PATH_RETENTION_DAYS));
    }

    public function testTheOldSettingsAreRemovedFromEveryScopeAndOtherSettingsKept(): void
    {
        $this->seedOneXSettings('1', '14');
        $this->save(self::KEPT_SETTING, '1');

        self::service(SettingsMigration::class)->run();

        foreach (self::OLD_SETTINGS as $path) {
            self::assertSame([], $this->storedValues($path), $path . ' should be removed in every scope.');
        }
        self::assertSame(['1'], $this->storedValues(self::KEPT_SETTING));
    }

    public function testASecondRunLeavesTheListAndTheRetentionAsTheFirstLeftThem(): void
    {
        $this->seedOneXSettings('1', '14');
        $migration = self::service(SettingsMigration::class);

        $migration->run();
        $list = $this->storedValues(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS);
        $retention = $this->storedValues(ConfigInterface::XML_PATH_RETENTION_DAYS);
        $migration->run();

        self::assertSame(['password,token,x_partner_secret,client_id,store_only_key'], $list);
        self::assertSame($list, $this->storedValues(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS));
        self::assertSame(['14'], $retention);
        self::assertSame($retention, $this->storedValues(ConfigInterface::XML_PATH_RETENTION_DAYS));
    }

    /**
     * Store the settings a 1.x installation leaves, in the default scope and a store scope, over an activity log list
     * of "password,token", and load them into the configuration
     *
     * @param string $cleanupEnabled
     * @param string $retentionDays
     * @return void
     */
    private function seedOneXSettings(string $cleanupEnabled, string $retentionDays): void
    {
        $this->resourceConnection->getConnection()->delete(
            $this->resourceConnection->getTableName(self::CONFIG_TABLE),
            ['path LIKE ?' => 'api_logger/%']
        );
        $this->save(SettingsMigration::ACTIVITY_LOG_SECRET_FIELDS, 'password,token');
        $this->save('api_logger/security/secret_fields', 'Password, X_Partner_Secret');
        $this->save('api_logger/general/secret_fields', "token\nclient_id");
        $this->save(
            'api_logger/general/secret_fields',
            'x_partner_secret,Store_Only_Key',
            ScopeInterface::SCOPE_STORES
        );
        $this->save('api_logger/general/sanitize_secrets', '1');
        $this->save('api_logger/general/sanitize_secrets', '0', ScopeInterface::SCOPE_STORES);
        $this->save('api_logger/cleanup/enabled', $cleanupEnabled);
        $this->save('api_logger/cleanup/retention_days', $retentionDays);
        $this->save('api_logger/cleanup/retention_days', '3', ScopeInterface::SCOPE_STORES);

        self::service(ReinitableConfigInterface::class)->reinit();
    }

    /**
     * Store a value for the default scope, or for the store
     *
     * @param string $path
     * @param string $value
     * @param string $scope
     * @return void
     */
    private function save(string $path, string $value, string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::CONFIG_TABLE),
            [
                'scope' => $scope,
                'scope_id' => $scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT ? 0 : self::STORE_ID,
                'path' => $path,
                'value' => $value,
            ],
            ['value']
        );
    }

    /**
     * Values stored for the path, in any scope
     *
     * @param string $path
     * @return array<mixed>
     */
    private function storedValues(string $path): array
    {
        $connection = $this->resourceConnection->getConnection();

        return $connection->fetchCol(
            $connection->select()
                ->from($this->resourceConnection->getTableName(self::CONFIG_TABLE), ['value'])
                ->where('path = ?', $path)
        );
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
