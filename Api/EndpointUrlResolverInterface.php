<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Resolves relative API endpoint paths to full absolute URLs
 */
interface EndpointUrlResolverInterface
{
    /**
     * Resolve a potentially relative endpoint to a full absolute URL
     *
     * Uses the Host and X-Forwarded-Proto headers from the original request
     * to reconstruct the full URL. Falls back to the store base URL.
     *
     * @param string $endpoint
     * @param string|null $requestHeadersJson
     * @param int|null $storeId
     * @return string
     */
    public function resolve(string $endpoint, ?string $requestHeadersJson, ?int $storeId): string;
}
