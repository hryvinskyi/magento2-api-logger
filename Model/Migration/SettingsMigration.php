<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Migration;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Moves the 1.x settings that 2.0 no longer owns
 *
 * Secret field names join the activity log's own list, which masks every channel. The cleanup retention becomes the
 * REST channel's retention. The masking switch and the cleanup switch are gone: secrets are always masked and the
 * journal always applies retention.
 *
 * The settings are written to the database, which the configuration already loaded in this process does not see; it
 * is reloaded once they are written, so whatever runs next in the same process (the backfill's masking, a second run)
 * reads the merged list rather than the one before it.
 */
class SettingsMigration
{
    public const ACTIVITY_LOG_SECRET_FIELDS = 'hryvinskyi_activity_log/security/secret_fields';
    private const OLD_SECRET_FIELDS = ['api_logger/security/secret_fields', 'api_logger/general/secret_fields'];
    private const OLD_RETENTION_DAYS = 'api_logger/cleanup/retention_days';
    private const OLD_CLEANUP_ENABLED = 'api_logger/cleanup/enabled';
    private const OLD_PATHS = [
        'api_logger/security/%',
        'api_logger/cleanup/%',
        'api_logger/general/sanitize_secrets',
        'api_logger/general/secret_fields',
    ];

    /**
     * @param ResourceConnection $resourceConnection
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param ReinitableConfigInterface $config
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly ReinitableConfigInterface $config
    ) {
    }

    /**
     * Move the settings, remove the old ones and reload the configuration
     *
     * @return void
     */
    public function run(): void
    {
        $this->mergeSecretFields();
        $this->moveRetention();
        $this->removeOldSettings();
        $this->config->reinit();
    }

    /**
     * Add the secret field names of 1.x, from any scope, to the activity log's list
     *
     * The list is written only when 1.x knew a name it lacks. Otherwise the setting is left as it is: on a store
     * without 1.x names, writing the list would store the activity log's default and keep it from ever following a
     * change of that default.
     *
     * @return void
     */
    private function mergeSecretFields(): void
    {
        $current = $this->words($this->scopeConfig->getValue(self::ACTIVITY_LOG_SECRET_FIELDS));
        $names = $current;
        foreach ($this->storedValues(self::OLD_SECRET_FIELDS) as $value) {
            foreach ($this->words($value) as $name) {
                if (!in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        if ($names === $current) {
            return;
        }

        $this->configWriter->save(self::ACTIVITY_LOG_SECRET_FIELDS, implode(',', $names));
    }

    /**
     * Keep the default-scope cleanup retention as the REST channel's retention
     *
     * Cleanup switched off keeps calls for good, as 0 days does.
     *
     * @return void
     */
    private function moveRetention(): void
    {
        $enabled = $this->defaultValue(self::OLD_CLEANUP_ENABLED);
        $days = $this->defaultValue(self::OLD_RETENTION_DAYS);

        if ($enabled === '0') {
            $this->configWriter->save(ConfigInterface::XML_PATH_RETENTION_DAYS, '0');

            return;
        }

        if (ctype_digit($days)) {
            $this->configWriter->save(ConfigInterface::XML_PATH_RETENTION_DAYS, (string) (int) $days);
        }
    }

    /**
     * A value stored for the default scope
     *
     * An empty string when no row holds one (a fresh install) or its value is NULL.
     *
     * @param string $path
     * @return string
     */
    private function defaultValue(string $path): string
    {
        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('core_config_data'), ['value'])
                ->where('path = ?', $path)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
        );

        // The adapter documents a string, but answers false for no row and null for a NULL value
        return trim((string) $value);
    }

    /**
     * Delete the settings 2.0 no longer reads, in every scope
     *
     * @return void
     */
    private function removeOldSettings(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('core_config_data');
        foreach (self::OLD_PATHS as $path) {
            $connection->delete($table, ['path LIKE ?' => $path]);
        }
    }

    /**
     * Values stored for the paths, in any scope, in the order they were saved
     *
     * @param list<string> $paths
     * @return list<string>
     */
    private function storedValues(array $paths): array
    {
        $connection = $this->resourceConnection->getConnection();
        $values = [];
        foreach ($connection->fetchCol(
            $connection->select()
                ->from($this->resourceConnection->getTableName('core_config_data'), ['value'])
                ->where('path IN (?)', $paths)
                ->order('config_id ASC')
        ) as $value) {
            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Field names of a list separated by commas or new lines, lowercase
     *
     * @param mixed $value
     * @return list<string>
     */
    private function words(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $words = [];
        foreach (preg_split('/[\s,]+/', strtolower($value)) ?: [] as $word) {
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        return $words;
    }
}
