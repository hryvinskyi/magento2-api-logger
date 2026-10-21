<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ActivityLog\Api\Data\Outcome;

/**
 * How a REST call ended
 *
 * 401 and 403 are the API refusing the caller: denied. Any other error status, or an error raised by the call, is a
 * failure.
 */
class RestOutcome
{
    private const DENIED_STATUSES = [401, 403];
    private const FIRST_ERROR_STATUS = 400;

    /**
     * Classify a response code, and whether an exception was thrown, into an outcome
     *
     * @param int $responseCode
     * @param bool $exception
     * @return Outcome
     */
    public function of(int $responseCode, bool $exception): Outcome
    {
        if (in_array($responseCode, self::DENIED_STATUSES, true)) {
            return Outcome::Denied;
        }

        return $exception || $responseCode >= self::FIRST_ERROR_STATUS ? Outcome::Failure : Outcome::Success;
    }
}
