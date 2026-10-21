<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\ResourceModel;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\ExpressionFactory;

/**
 * How many logged calls ended in each class of status (2xx, 3xx, 4xx, 5xx) over a period
 */
class ResponseCodeStatistics
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ExpressionFactory $expressionFactory
    ) {
    }

    /**
     * Calls per status class, keyed by the class's first digit
     *
     * @param int $fromMs
     * @param int $toMs
     * @return array<int, int>
     */
    public function countByClass(int $fromMs, int $toMs): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(LogEntryResource::TABLE), [
                'status_class' => $this->expressionFactory->create(
                    ['expression' => 'FLOOR(' . LogEntryInterface::RESPONSE_CODE . ' / 100)']
                ),
                'total' => $this->expressionFactory->create(['expression' => 'COUNT(*)']),
            ])
            ->where(LogEntryInterface::RESPONSE_CODE . ' IS NOT NULL')
            ->where(LogEntryInterface::CREATED_AT . ' >= ?', gmdate('Y-m-d H:i:s', intdiv($fromMs, 1000)))
            ->where(LogEntryInterface::CREATED_AT . ' < ?', gmdate('Y-m-d H:i:s', intdiv($toMs, 1000)))
            ->group('status_class');

        $counts = [];
        foreach ($connection->fetchPairs($select) as $class => $total) {
            if (is_numeric($class) && is_numeric($total)) {
                $counts[(int) $class] = (int) $total;
            }
        }

        return $counts;
    }
}
