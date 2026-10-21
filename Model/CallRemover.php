<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;

/**
 * Removes logged calls together with their activities, so the journal and this log never disagree
 *
 * A call's entry goes with its activity; an entry without one is removed on its own.
 */
class CallRemover
{
    /**
     * Removing calls removes activities, so whoever asks for it needs the activity log's permission to delete them
     * on top of this log's own
     */
    public const ACTIVITY_DELETE_RESOURCE = 'Hryvinskyi_ActivityLog::delete';

    /**
     * @param ActivityRepositoryInterface $activityRepository
     * @param LogEntryRepositoryInterface $logEntryRepository
     */
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly LogEntryRepositoryInterface $logEntryRepository
    ) {
    }

    /**
     * Remove the given calls and the activities they belong to
     *
     * @param iterable<mixed> $entries
     * @return int Calls removed
     */
    public function remove(iterable $entries): int
    {
        $activityIds = [];
        $removed = 0;
        foreach ($entries as $entry) {
            if (!$entry instanceof LogEntryInterface) {
                continue;
            }

            $activityId = $entry->getActivityId();
            if ($activityId === null) {
                $removed += $this->logEntryRepository->delete($entry) ? 1 : 0;
                continue;
            }

            $activityIds[] = $activityId;
        }

        return $removed + ($activityIds === [] ? 0 : $this->activityRepository->deleteByIds($activityIds));
    }
}
