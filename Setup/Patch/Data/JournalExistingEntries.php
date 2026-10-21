<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Setup\Patch\Data;

use Hryvinskyi\ApiLogger\Model\Migration\JournalBackfill;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 2.0: every call logged before is journaled as a REST activity, and the secrets it stored are masked
 */
class JournalExistingEntries implements DataPatchInterface
{
    /**
     * @param JournalBackfill $backfill
     */
    public function __construct(
        private readonly JournalBackfill $backfill
    ) {
    }

    /**
     * Journal and mask the existing entries
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->backfill->run();

        return $this;
    }

    /**
     * The secret list is merged first, so masking uses it
     *
     * @return list<class-string<DataPatchInterface>>
     */
    public static function getDependencies(): array
    {
        return [MoveSettingsToActivityLog::class];
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
