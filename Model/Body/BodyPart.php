<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Body;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;

/**
 * Which body of a call a page or a download is about: the request's or the response's
 */
enum BodyPart: string
{
    case Request = 'request';
    case Response = 'response';

    /**
     * The stored body of this part
     *
     * @param LogEntryInterface $entry
     * @return string|null
     */
    public function of(LogEntryInterface $entry): ?string
    {
        return match ($this) {
            self::Request => $entry->getRequestBody(),
            self::Response => $entry->getResponseBody(),
        };
    }
}
