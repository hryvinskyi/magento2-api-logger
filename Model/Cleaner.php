<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\CleanerInterface;
use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Cleaner Service
 *
 * Handles cleanup of old log entries
 */
class Cleaner implements CleanerInterface
{
    private const BATCH_SIZE = 1000;

    /**
     * @param ConfigInterface $config
     * @param LogEntryResource $logEntryResource
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LogEntryResource $logEntryResource,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function cleanOldLogs(): int
    {
        if (!$this->config->isCleanupEnabled()) {
            return 0;
        }

        $retentionDays = $this->config->getRetentionDays();

        if ($retentionDays <= 0) {
            return 0;
        }

        return $this->cleanLogsOlderThan($retentionDays);
    }

    /**
     * @inheritDoc
     */
    public function cleanLogsOlderThan(int $days): int
    {
        try {
            $date = new \DateTime();
            $date->modify("-$days days");
            $dateThreshold = $date->format('Y-m-d H:i:s');

            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->logEntryResource->getMainTable();

            // Count total records to delete
            $select = $connection->select()
                ->from($tableName, 'COUNT(*)')
                ->where('created_at < ?', $dateThreshold);
            $totalCount = (int)$connection->fetchOne($select);

            if ($totalCount === 0) {
                return 0;
            }

            // Delete in batches to avoid locking the table for too long
            $deletedCount = 0;
            while ($deletedCount < $totalCount) {
                $affected = $connection->delete(
                    $tableName,
                    [
                        'created_at < ?' => $dateThreshold,
                        'entity_id IN (?)' => $connection->select()
                            ->from($tableName, 'entity_id')
                            ->where('created_at < ?', $dateThreshold)
                            ->limit(self::BATCH_SIZE)
                    ]
                );

                if ($affected === 0) {
                    break;
                }

                $deletedCount += $affected;
                $this->logger->debug(
                    sprintf('Deleted batch of %d records (total: %d/%d)', $affected, $deletedCount, $totalCount)
                );
            }

            $this->logger->info(sprintf('Deleted %d old API log entries older than %d days', $deletedCount, $days));

            return $deletedCount;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to clean old API logs: %s', $e->getMessage()),
                ['exception' => $e]
            );
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function cleanAllLogs(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->logEntryResource->getMainTable();

            // Count total records to delete
            $totalCount = (int)$connection->fetchOne(
                $connection->select()->from($tableName, 'COUNT(*)')
            );

            if ($totalCount === 0) {
                return 0;
            }

            // Delete in batches to avoid locking the table for too long
            $deletedCount = 0;
            while ($deletedCount < $totalCount) {
                // Get batch of IDs
                $ids = $connection->fetchCol(
                    $connection->select()
                        ->from($tableName, 'entity_id')
                        ->limit(self::BATCH_SIZE)
                );

                if (empty($ids)) {
                    break;
                }

                $affected = $connection->delete(
                    $tableName,
                    ['entity_id IN (?)' => $ids]
                );

                $deletedCount += $affected;
                $this->logger->debug(
                    sprintf('Deleted batch of %d records (total: %d/%d)', $affected, $deletedCount, $totalCount)
                );
            }

            $this->logger->info(sprintf('Deleted all %d API log entries', $deletedCount));

            return $deletedCount;
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to clean all API logs: %s', $e->getMessage()),
                ['exception' => $e]
            );
            return 0;
        }
    }
}