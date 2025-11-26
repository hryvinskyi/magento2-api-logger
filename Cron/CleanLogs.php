<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Cron;

use Hryvinskyi\ApiLogger\Api\CleanerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron job to clean old API logs
 */
class CleanLogs
{
    /**
     * @param CleanerInterface $cleaner
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CleanerInterface $cleaner,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $count = $this->cleaner->cleanOldLogs();

            if ($count > 0) {
                $this->logger->info(sprintf('API Logger: Cleaned %d old log entries', $count));
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('API Logger: Failed to clean old logs: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }
}