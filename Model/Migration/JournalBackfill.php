<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Migration;

use Hryvinskyi\ActivityLog\Api\ActivityRecorderInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\OriginInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\Http\Masker;
use Hryvinskyi\ApiLogger\Model\Recording\EndpointMasker;
use Hryvinskyi\ApiLogger\Model\Recording\EndpointPolicy;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\Recording\RestOutcome;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;
use Hryvinskyi\ApiLogger\Model\Recording\SecretResponsePolicy;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Journals the calls logged before 2.0, and masks what they stored
 *
 * 1.x never masked secrets: its masking setting was read from a path the settings page did not write. Every entry
 * without an activity has its stored headers and bodies masked, the response of a route whose response is a secret
 * replaced whole, and the secrets of its endpoint masked (the path segments a secret route parameter of the selected
 * endpoint pattern stands for, and the secret values of its query), whether or not the activity log records an
 * activity for it: it records none while it is switched off or when the insert fails. The activity's action is built
 * from the masked endpoint. An entry it does record, in the "rest" channel and attributed to an unknown caller at the
 * time the call was logged, is linked to that activity. Masking a masked value gives the same text, so a run can be
 * repeated: it journals what is still unlinked and leaves the rest as it is. Entries go in batches.
 *
 * @phpstan-type EntryRow array<string, mixed>
 * @phpstan-type StoredEndpoint array{path: string, query: string|null}
 */
class JournalBackfill
{
    public const UNKNOWN_ACTOR = 'unknown (logged before 2.0)';
    private const MASKED_FIELDS = [
        LogEntryInterface::REQUEST_HEADERS,
        LogEntryInterface::REQUEST_BODY,
        LogEntryInterface::RESPONSE_HEADERS,
        LogEntryInterface::RESPONSE_BODY,
    ];

    /**
     * @var array<int, true>|null
     */
    private ?array $storeIds = null;

    /**
     * @param ResourceConnection $resourceConnection
     * @param Masker $masker
     * @param ActivityInterfaceFactory $activityFactory
     * @param ActorInterfaceFactory $actorFactory
     * @param OriginInterfaceFactory $originFactory
     * @param ActivityRecorderInterface $activityRecorder
     * @param RestOutcome $outcome
     * @param RoutePath $routePath
     * @param StoreManagerInterface $storeManager
     * @param EndpointPolicy $endpointPolicy
     * @param EndpointMasker $endpointMasker
     * @param SecretResponsePolicy $secretResponsePolicy
     * @param int $batchSize
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Masker $masker,
        private readonly ActivityInterfaceFactory $activityFactory,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly OriginInterfaceFactory $originFactory,
        private readonly ActivityRecorderInterface $activityRecorder,
        private readonly RestOutcome $outcome,
        private readonly RoutePath $routePath,
        private readonly StoreManagerInterface $storeManager,
        private readonly EndpointPolicy $endpointPolicy,
        private readonly EndpointMasker $endpointMasker,
        private readonly SecretResponsePolicy $secretResponsePolicy,
        private readonly int $batchSize = 100
    ) {
    }

    /**
     * Mask every entry that has no activity yet, and journal those the activity log records
     *
     * @return int Entries journaled
     */
    public function run(): int
    {
        $journaled = 0;
        $afterId = 0;
        do {
            $rows = $this->batch($afterId);
            foreach ($rows as $row) {
                $afterId = $this->int($row[LogEntryInterface::ENTITY_ID] ?? 0);
                $journaled += $this->journal($row) ? 1 : 0;
            }
        } while (count($rows) === $this->batchSize);

        return $journaled;
    }

    /**
     * The next entries without an activity
     *
     * @param int $afterId
     * @return list<EntryRow>
     */
    private function batch(int $afterId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from($this->resourceConnection->getTableName(LogEntryResource::TABLE))
                ->where(LogEntryInterface::ACTIVITY_ID . ' IS NULL')
                ->where(LogEntryInterface::ENTITY_ID . ' > ?', $afterId)
                ->order(LogEntryInterface::ENTITY_ID . ' ASC')
                ->limit($this->batchSize)
        ) as $row) {
            if (is_array($row)) {
                $rows[] = array_combine(array_map('strval', array_keys($row)), array_values($row));
            }
        }

        return $rows;
    }

    /**
     * Mask what one entry stored, and journal it when the activity log records it
     *
     * @param EntryRow $row
     * @return bool Whether it was journaled
     */
    private function journal(array $row): bool
    {
        $correlationId = bin2hex(random_bytes(8));
        $endpoint = $this->maskedEndpoint($row);
        $activityId = $this->activityRecorder->record($this->activity($row, $this->join($endpoint), $correlationId));

        $update = $this->masked($row, $this->join($endpoint)) + $this->changed($row, $endpoint);
        if ($activityId !== null) {
            $update[LogEntryInterface::ACTIVITY_ID] = $activityId;
            $update[LogEntryInterface::CORRELATION_ID] = $correlationId;
        }

        if ($update !== []) {
            $this->resourceConnection->getConnection()->update(
                $this->resourceConnection->getTableName(LogEntryResource::TABLE),
                $update,
                [LogEntryInterface::ENTITY_ID . ' = ?' => $this->int($row[LogEntryInterface::ENTITY_ID] ?? 0)]
            );
        }

        return $activityId !== null;
    }

    /**
     * The activity an entry is journaled as
     *
     * @param EntryRow $row
     * @param string $endpoint
     * @param string $correlationId
     * @return ActivityInterface
     */
    private function activity(array $row, string $endpoint, string $correlationId): ActivityInterface
    {
        $code = $this->int($row[LogEntryInterface::RESPONSE_CODE] ?? 0);
        $occurredAt = strtotime($this->text($row[LogEntryInterface::CREATED_AT] ?? null) . ' UTC');
        $action = $this->text($row[LogEntryInterface::METHOD] ?? null) . ' ' . $this->routePath->route($endpoint);
        $duration = $row[LogEntryInterface::DURATION] ?? null;

        return $this->activityFactory->create([
            'correlationId' => $correlationId,
            'channel' => RestCallRecorder::CHANNEL,
            'action' => mb_substr(trim($action), 0, 255),
            'actor' => $this->actorFactory->create(['type' => ActorType::Anonymous, 'name' => self::UNKNOWN_ACTOR]),
            'outcome' => $this->outcome->of($code, (bool) ($row[LogEntryInterface::IS_EXCEPTION] ?? false)),
            'origin' => $this->originFactory->create([
                'storeId' => $this->existingStoreId($row[LogEntryInterface::STORE_ID] ?? null),
                'ip' => $this->nullableText($row[LogEntryInterface::IP_ADDRESS] ?? null),
                'userAgent' => $this->nullableText($row[LogEntryInterface::USER_AGENT] ?? null),
            ]),
            'occurredAtMs' => ($occurredAt === false ? time() : $occurredAt) * 1000,
            'durationMs' => is_numeric($duration) ? (float) $duration : null,
        ]);
    }

    /**
     * The stored fields whose masked text differs from what is stored
     *
     * The response of a route whose response is a secret is replaced whole.
     *
     * @param EntryRow $row
     * @param string $endpoint
     * @return array<string, string>
     */
    private function masked(array $row, string $endpoint): array
    {
        $masked = [];
        foreach (self::MASKED_FIELDS as $field) {
            $value = $row[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $masked[$field] = $this->masker->text($value);
            }
        }
        if (isset($masked[LogEntryInterface::RESPONSE_BODY])
            && $this->secretResponsePolicy->masksWholeResponse($endpoint)
        ) {
            $masked[LogEntryInterface::RESPONSE_BODY] = SanitizerInterface::MASK;
        }

        return array_filter(
            $masked,
            static fn (string $text, string $field): bool => $text !== ($row[$field] ?? null),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * The stored path and query with their secrets masked; the path cut to what its column holds
     *
     * @param EntryRow $row
     * @return StoredEndpoint
     */
    private function maskedEndpoint(array $row): array
    {
        $path = $this->text($row[LogEntryInterface::ENDPOINT] ?? null);
        $query = $this->nullableText($row[LogEntryInterface::QUERY_STRING] ?? null);
        $pattern = $this->endpointPolicy->selectedPattern(
            $this->join(['path' => $path, 'query' => $query]),
            $this->text($row[LogEntryInterface::METHOD] ?? null)
        );

        return [
            'path' => mb_substr(
                $this->endpointMasker->maskPath($path, $pattern),
                0,
                LogEntryResource::PATH_MAX_LENGTH
            ),
            'query' => $query !== null ? $this->endpointMasker->maskQuery($query) : null,
        ];
    }

    /**
     * The endpoint columns whose masked text differs from what is stored
     *
     * @param EntryRow $row
     * @param StoredEndpoint $endpoint
     * @return array<string, string>
     */
    private function changed(array $row, array $endpoint): array
    {
        $changed = [];
        if ($endpoint['path'] !== $this->text($row[LogEntryInterface::ENDPOINT] ?? null)) {
            $changed[LogEntryInterface::ENDPOINT] = $endpoint['path'];
        }
        if ($endpoint['query'] !== null && $endpoint['query'] !== ($row[LogEntryInterface::QUERY_STRING] ?? null)) {
            $changed[LogEntryInterface::QUERY_STRING] = $endpoint['query'];
        }

        return $changed;
    }

    /**
     * Path and query as one endpoint
     *
     * @param StoredEndpoint $endpoint
     * @return string
     */
    private function join(array $endpoint): string
    {
        return $endpoint['path'] . ($endpoint['query'] !== null ? '?' . $endpoint['query'] : '');
    }

    /**
     * The store id when that store still exists
     *
     * @param mixed $storeId
     * @return int|null
     */
    private function existingStoreId(mixed $storeId): ?int
    {
        if ($this->storeIds === null) {
            $this->storeIds = [];
            foreach ($this->storeManager->getStores(true) as $store) {
                $this->storeIds[(int) $store->getId()] = true;
            }
        }

        return is_numeric($storeId) && isset($this->storeIds[(int) $storeId]) ? (int) $storeId : null;
    }

    /**
     * A stored number, or 0
     *
     * @param mixed $value
     * @return int
     */
    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * A stored value as text, or an empty string
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A stored value as text, or null when it is empty
     *
     * @param mixed $value
     * @return string|null
     */
    private function nullableText(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
