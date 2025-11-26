<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Cleaner Service Interface
 *
 * Handles cleanup of old log entries
 */
interface CleanerInterface
{
    /**
     * Clean old log entries based on retention policy
     *
     * @return int Number of deleted entries
     */
    public function cleanOldLogs(): int;

    /**
     * Clean log entries older than specified days
     *
     * @param int $days
     * @return int Number of deleted entries
     */
    public function cleanLogsOlderThan(int $days): int;

    /**
     * Delete all log entries
     *
     * @return int Number of deleted entries
     */
    public function cleanAllLogs(): int;
}