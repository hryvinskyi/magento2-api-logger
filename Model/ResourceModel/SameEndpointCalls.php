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
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\DB\Select;

/**
 * Other logged calls to the same endpoint with the same method, newest first: the candidates a call is compared with
 *
 * The same endpoint is the same path, whatever the query: the path is a column of its own.
 *
 * @phpstan-type CallRow array{
 *     id: int,
 *     activityId: int|null,
 *     createdAt: string,
 *     responseCode: int|null,
 *     durationMs: float|null,
 *     correlationId: string|null
 * }
 */
class SameEndpointCalls
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper
    ) {
    }

    /**
     * Calls like the given one, optionally narrowed to a status or a correlation id
     *
     * @param LogEntryInterface $entry
     * @param string $search
     * @param int $limit
     * @return list<CallRow>
     */
    public function find(LogEntryInterface $entry, string $search = '', int $limit = 20): array
    {
        $path = explode('?', $entry->getEndpoint(), 2)[0];
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(LogEntryResource::TABLE), [
                LogEntryInterface::ENTITY_ID,
                LogEntryInterface::ACTIVITY_ID,
                LogEntryInterface::CREATED_AT,
                LogEntryInterface::RESPONSE_CODE,
                LogEntryInterface::DURATION,
                LogEntryInterface::CORRELATION_ID,
            ])
            ->where(LogEntryInterface::METHOD . ' = ?', $entry->getMethod())
            ->where(LogEntryInterface::ENDPOINT . ' = ?', $path)
            ->where(LogEntryInterface::ENTITY_ID . ' <> ?', (int) $entry->getEntityId())
            ->order(LogEntryInterface::ENTITY_ID . ' DESC')
            ->limit($limit);

        $this->narrow($select, trim($search));

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (is_array($row)) {
                $rows[] = $this->row($row);
            }
        }

        return $rows;
    }

    /**
     * Narrow the calls to what a person searched for
     *
     * Three digits are a status, other text is the start of an operation id, an empty search narrows nothing.
     *
     * @param Select $select
     * @param string $search
     * @return void
     */
    private function narrow(Select $select, string $search): void
    {
        if ($search === '') {
            return;
        }

        if (ctype_digit($search) && strlen($search) === 3) {
            $select->where(LogEntryInterface::RESPONSE_CODE . ' = ?', (int) $search);

            return;
        }

        $select->where(
            LogEntryInterface::CORRELATION_ID . ' LIKE ?',
            $this->dbHelper->escapeLikeValue($search, ['position' => 'start'])
        );
    }

    /**
     * Shape one fetched row into a call row
     *
     * @param array<mixed> $row
     * @return CallRow
     */
    private function row(array $row): array
    {
        return [
            'id' => (int) $this->number($row[LogEntryInterface::ENTITY_ID] ?? null),
            'activityId' => $this->number($row[LogEntryInterface::ACTIVITY_ID] ?? null),
            'createdAt' => $this->text($row[LogEntryInterface::CREATED_AT] ?? null) ?? '',
            'responseCode' => $this->number($row[LogEntryInterface::RESPONSE_CODE] ?? null),
            'durationMs' => is_numeric($row[LogEntryInterface::DURATION] ?? null)
                ? (float) $row[LogEntryInterface::DURATION]
                : null,
            'correlationId' => $this->text($row[LogEntryInterface::CORRELATION_ID] ?? null),
        ];
    }

    /**
     * Read a numeric value as an integer, anything else as null
     *
     * @param mixed $value
     * @return int|null
     */
    private function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Read a scalar value as a string, anything else as null
     *
     * @param mixed $value
     * @return string|null
     */
    private function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
