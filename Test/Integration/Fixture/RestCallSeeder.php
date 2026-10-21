<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Fixture;

use Hryvinskyi\ActivityLog\Api\ActivityBuilderInterface;
use Hryvinskyi\ActivityLog\Api\ActivityRecorderInterface;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterfaceFactory;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;

/**
 * Stores a logged REST call the way the recorder leaves it: an activity of the "rest" channel in the journal, and
 * the call's entry linked to it by activity id and correlation id
 *
 * Header values are stored as given, so a test passes a secret already masked, as it would be after recording.
 */
class RestCallSeeder
{
    /**
     * @param ActivityBuilderInterface $activityBuilder
     * @param ActivityRecorderInterface $activityRecorder
     * @param LogEntryInterfaceFactory $logEntryFactory
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param RoutePath $routePath
     */
    public function __construct(
        private readonly ActivityBuilderInterface $activityBuilder,
        private readonly ActivityRecorderInterface $activityRecorder,
        private readonly LogEntryInterfaceFactory $logEntryFactory,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly RoutePath $routePath
    ) {
    }

    /**
     * Store one call and its activity
     *
     * @param string $method
     * @param string $endpoint Path after the REST front name, with the store code and the query
     * @param int $responseCode
     * @param string|null $requestBody
     * @param string|null $responseBody
     * @param array<string, string> $requestHeaders
     * @param array<string, string> $responseHeaders
     * @return LogEntryInterface The stored entry, with its id and its activity id
     * @throws \RuntimeException When the journal records nothing
     */
    public function seed(
        string $method,
        string $endpoint,
        int $responseCode = 200,
        ?string $requestBody = null,
        ?string $responseBody = null,
        array $requestHeaders = [],
        array $responseHeaders = []
    ): LogEntryInterface {
        $activity = $this->activityBuilder->build(
            RestCallRecorder::CHANNEL,
            $method . ' ' . $this->routePath->route($endpoint),
            $responseCode < 400 ? Outcome::Success : Outcome::Failure,
            null,
            12.5
        );
        $activityId = $this->activityRecorder->record($activity);
        if ($activityId === null) {
            throw new \RuntimeException('The journal recorded no activity for the seeded call.');
        }

        $entry = $this->logEntryFactory->create();
        $entry->setMethod($method)
            ->setEndpoint($endpoint)
            ->setRequestHeaders($this->json($requestHeaders))
            ->setRequestBody($requestBody)
            ->setResponseHeaders($this->json($responseHeaders))
            ->setResponseBody($responseBody)
            ->setResponseCode($responseCode)
            ->setDuration(12.5)
            ->setIsException($responseCode >= 400)
            ->setActivityId($activityId)
            ->setCorrelationId($activity->getCorrelationId());

        $saved = $this->logEntryRepository->save($entry);

        return $this->logEntryRepository->getById((int) $saved->getEntityId());
    }

    /**
     * @param array<string, string> $headers
     * @return string|null
     */
    private function json(array $headers): ?string
    {
        if ($headers === []) {
            return null;
        }

        $json = json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? null : $json;
    }
}
