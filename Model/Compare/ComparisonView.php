<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Compare;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Hryvinskyi\ApiLogger\Model\Body\ReadableBody;
use Hryvinskyi\ApiLogger\Model\ResourceModel\SameEndpointCalls;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * What the compare dialog shows: the calls to choose from, then the two calls side by side
 *
 * Bodies are compared as readable text (JSON pretty-printed) and cut to a size a browser diffs quickly. Each side
 * says whether a body was cut, so two bodies that match up to the cut are not reported as identical.
 */
class ComparisonView
{
    private const BODY_MAX_BYTES = 262144;
    private const BYTES_PER_KB = 1024;

    /**
     * @param SameEndpointCalls $sameEndpointCalls
     * @param ActivityRepositoryInterface $activityRepository
     * @param DateFormatterInterface $dateFormatter
     * @param ReadableBody $readableBody
     */
    public function __construct(
        private readonly SameEndpointCalls $sameEndpointCalls,
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly DateFormatterInterface $dateFormatter,
        private readonly ReadableBody $readableBody
    ) {
    }

    /**
     * Calls to the same endpoint, newest first
     *
     * @param LogEntryInterface $entry
     * @param string $search A status or the start of a correlation id
     * @return list<array{id:int,time:string,actor:string,status:int|null,durationMs:float|null,correlation:string}>
     */
    public function candidates(LogEntryInterface $entry, string $search): array
    {
        $candidates = [];
        foreach ($this->sameEndpointCalls->find($entry, $search) as $call) {
            $candidates[] = [
                'id' => $call['id'],
                'time' => $this->dateFormatter->formatDateTime($call['createdAt']),
                'actor' => $this->actorOf($call['activityId']),
                'status' => $call['responseCode'],
                'durationMs' => $call['durationMs'],
                'correlation' => substr((string) $call['correlationId'], 0, 8),
            ];
        }

        return $candidates;
    }

    /**
     * Two calls with what a diff needs
     *
     * @param LogEntryInterface $current
     * @param LogEntryInterface $other
     * @return array{current: array<string, mixed>, other: array<string, mixed>}
     */
    public function pair(LogEntryInterface $current, LogEntryInterface $other): array
    {
        return ['current' => $this->side($current), 'other' => $this->side($other)];
    }

    /**
     * How much of each body is compared, in KB
     *
     * @return int
     */
    public function bodyLimitKb(): int
    {
        return intdiv(self::BODY_MAX_BYTES, self::BYTES_PER_KB);
    }

    /**
     * Shape one side of the comparison
     *
     * @param LogEntryInterface $entry
     * @return array{
     *     id: int,
     *     time: string,
     *     status: int|null,
     *     durationMs: float|null,
     *     request: string,
     *     requestCut: bool,
     *     response: string,
     *     responseCut: bool
     * }
     */
    private function side(LogEntryInterface $entry): array
    {
        $request = $this->readableBody->text($entry->getRequestBody());
        $response = $this->readableBody->text($entry->getResponseBody());

        return [
            'id' => (int) $entry->getEntityId(),
            'time' => $this->dateFormatter->formatDateTime($entry->getCreatedAt()),
            'status' => $entry->getResponseCode(),
            'durationMs' => $entry->getDuration(),
            'request' => mb_strcut($request, 0, self::BODY_MAX_BYTES),
            'requestCut' => strlen($request) > self::BODY_MAX_BYTES,
            'response' => mb_strcut($response, 0, self::BODY_MAX_BYTES),
            'responseCut' => strlen($response) > self::BODY_MAX_BYTES,
        ];
    }

    /**
     * Who made a call, as the journal names them
     *
     * @param int|null $activityId
     * @return string
     */
    private function actorOf(?int $activityId): string
    {
        if ($activityId === null) {
            return '—';
        }

        try {
            $actor = $this->activityRepository->get($activityId)->getActor();
        } catch (NoSuchEntityException) {
            return '—';
        }

        return $actor->getViaClient() === null
            ? $actor->getName()
            : (string) __('%1 via %2', $actor->getName(), $actor->getViaClient());
    }
}
