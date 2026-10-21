<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;

/**
 * The full URL a logged call was made to
 *
 * @api
 */
interface EndpointUrlResolverInterface
{
    /**
     * Full URL of the call: the base URL given, else the secure base URL of the call's store, plus REST prefix and path
     *
     * Nothing stored with the call (its Host or forwarding headers) decides where the URL points.
     *
     * @param LogEntryInterface $entry
     * @param string|null $baseUrl Another store to point the call at, e.g. a staging store
     * @return string
     */
    public function resolve(LogEntryInterface $entry, ?string $baseUrl = null): string;
}
