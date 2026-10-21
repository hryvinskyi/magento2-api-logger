<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

/**
 * Reads a logged REST path: "[/rest][/<store code>][/async[/bulk]]/V<n>/..." with an optional query
 *
 * Every part before "/V<n>/" is optional. The route is what Web API routes are written as, without the store code,
 * the async prefix, the query and a trailing slash: "/default/async/V1/products/?searchCriteria[pageSize]=5" →
 * "/V1/products". A path that does not follow this shape is its own route.
 */
class RoutePath
{
    /**
     * The REST front name that paths stored before 2.0, and by the 1.x module, start with
     *
     * New paths are stored without the front name. The two shapes give the same route, so old rows need no rewrite.
     * A store whose code is "rest" makes such a path ambiguous; it is read as this prefix.
     */
    private const LEGACY_FRONT_NAME = 'rest';

    /**
     * The segment Magento's asynchronous REST routes start with, after the store code: "/async/V1/..." and
     * "/async/bulk/V1/..."
     *
     * An async call has the route of the synchronous call it wraps. A store whose code is "async" is not supported:
     * its code is read as this prefix.
     */
    private const ASYNC = 'async';
    private const BULK = 'bulk';

    private const PATH = '#^'
        . '(?:/' . self::LEGACY_FRONT_NAME . '(?=/))?'
        . '(?:/(?!' . self::ASYNC . '/)(?!V\d+/)(?<store>[A-Za-z0-9_]+))?'
        . '(?:/' . self::ASYNC . '(?:/' . self::BULK . ')?)?'
        . '(?<route>/V\d+/.*)$#';

    /**
     * The route of the path, "/V<n>/...", without the query
     *
     * @param string $endpoint
     * @return string
     */
    public function route(string $endpoint): string
    {
        $path = $this->path($endpoint);

        return preg_match(self::PATH, $path, $parts) === 1 ? $parts['route'] : $path;
    }

    /**
     * The store code the path was called with, or null when it has none
     *
     * @param string $endpoint
     * @return string|null
     */
    public function storeCode(string $endpoint): ?string
    {
        if (preg_match(self::PATH, $this->path($endpoint), $parts) !== 1) {
            return null;
        }

        return $parts['store'] !== '' ? $parts['store'] : null;
    }

    /**
     * The path without its query and without a trailing slash; the root path stays "/"
     *
     * @param string $endpoint
     * @return string
     */
    private function path(string $endpoint): string
    {
        $path = rtrim(explode('?', $endpoint, 2)[0], '/');

        return $path !== '' ? $path : '/';
    }
}
