<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Tells whether a REST call is one of the endpoints chosen for logging
 *
 * @api
 */
interface EndpointMatcherInterface
{
    /**
     * What separates the method from the route in a pattern: "GET|/V1/products"
     */
    public const SEPARATOR = '|';

    /**
     * What starts a segment of a pattern that stands for any one called segment: ":sku" of "/V1/products/:sku"
     */
    public const PARAMETER_PREFIX = ':';

    /**
     * A last segment of a pattern that stands for the rest of the called path: "/V1/customers/*"
     */
    public const REST_OF_PATH = '*';

    /**
     * Check if endpoint matches the given pattern
     *
     * Patterns are "METHOD|/V1/route": "GET|/V1/products/:sku" matches any one SKU, "GET|/V1/customers/*" any path
     * under customers.
     *
     * @param string $endpoint The endpoint to check
     * @param string $method The HTTP method (e.g., GET, POST, etc.)
     * @param string $pattern The pattern to match against
     * @return bool
     */
    public function matches(string $endpoint, string $method, string $pattern): bool;
}
