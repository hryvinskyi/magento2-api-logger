<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Endpoint Pattern Matcher Interface
 *
 * Responsible for matching API endpoint paths against configured patterns
 */
interface EndpointMatcherInterface
{
    /**
     * Check if endpoint matches the given pattern
     *
     * Supports wildcard patterns like:
     * - /V1/products/:id (matches any product ID)
     * - /V1/customers/* (matches any customer endpoint)
     *
     * @param string $endpoint The endpoint to check
     * @param string $method The HTTP method (e.g., GET, POST, etc.)
     * @param string $pattern The pattern to match against
     * @return bool
     */
    public function matches(string $endpoint, string $method, string $pattern): bool;
}