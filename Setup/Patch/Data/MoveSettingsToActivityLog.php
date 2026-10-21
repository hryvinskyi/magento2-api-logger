<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Setup\Patch\Data;

use Hryvinskyi\ApiLogger\Model\Migration\SettingsMigration;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 2.0: secret field names join the activity log's list; the cleanup retention becomes the REST channel's retention
 */
class MoveSettingsToActivityLog implements DataPatchInterface
{
    /**
     * @param SettingsMigration $migration
     */
    public function __construct(
        private readonly SettingsMigration $migration
    ) {
    }

    /**
     * Move the settings
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->migration->run();

        return $this;
    }

    /**
     * No patch has to run first
     *
     * @return list<string>
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * No other names
     *
     * @return list<string>
     */
    public function getAliases(): array
    {
        return [];
    }
}
