<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\EndpointMatcherInterface;

/**
 * Masks the secrets a called endpoint carries in its path and in its query
 *
 * A path segment is a secret when the pattern the call matched names it with a route parameter that is a secret
 * field: "/V1/customers/42/password/resetLinkToken/abc" called under
 * "GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken" keeps "42" and loses "abc". Every
 * other byte of the path stays as it was. A path read without a pattern has no known parameters and stays whole. The
 * query is masked as a form body: the value of each secret key is replaced in place.
 */
class EndpointMasker
{
    private const QUERY = '?';
    private const SEGMENT = '/';

    /**
     * @param RoutePath $routePath
     * @param SanitizerInterface $sanitizer
     */
    public function __construct(
        private readonly RoutePath $routePath,
        private readonly SanitizerInterface $sanitizer
    ) {
    }

    /**
     * The endpoint, path and query, with its secrets masked
     *
     * @param string $endpoint
     * @param string|null $pattern The "METHOD|/V1/route" pattern the call matched, if any
     * @return string
     */
    public function mask(string $endpoint, ?string $pattern): string
    {
        $parts = explode(self::QUERY, $endpoint, 2);
        $path = $this->maskPath($parts[0], $pattern);

        return isset($parts[1]) ? $path . self::QUERY . $this->maskQuery($parts[1]) : $path;
    }

    /**
     * The path with each segment a secret route parameter stands for replaced by the mask
     *
     * @param string $path A path without query
     * @param string|null $pattern The "METHOD|/V1/route" pattern the call matched, if any
     * @return string
     */
    public function maskPath(string $path, ?string $pattern): string
    {
        $secretSegments = $pattern !== null ? $this->secretSegments($pattern) : [];
        if ($secretSegments === []) {
            return $path;
        }

        // The route is the end of the path, before any trailing slash
        $route = $this->routePath->route($path);
        $routeStart = strlen(rtrim($path, self::SEGMENT)) - strlen($route);
        if ($routeStart < 0 || substr($path, $routeStart, strlen($route)) !== $route) {
            return $path;
        }

        $index = 0;
        $segments = [];
        foreach (explode(self::SEGMENT, substr($path, $routeStart)) as $segment) {
            if ($segment !== '' && isset($secretSegments[$index++])) {
                $segment = SanitizerInterface::MASK;
            }
            $segments[] = $segment;
        }

        return substr($path, 0, $routeStart) . implode(self::SEGMENT, $segments);
    }

    /**
     * The query with the value of each secret key masked in place
     *
     * @param string $query
     * @return string
     */
    public function maskQuery(string $query): string
    {
        return $query !== '' ? $this->sanitizer->sanitizeBody($query) : $query;
    }

    /**
     * Positions, among the pattern's non-empty route segments, of the parameters that are secret fields
     *
     * @param string $pattern
     * @return array<int, true>
     */
    private function secretSegments(string $pattern): array
    {
        $parts = explode(EndpointMatcherInterface::SEPARATOR, $pattern, 2);
        if (!isset($parts[1])) {
            return [];
        }

        $segments = array_values(array_filter(
            explode(self::SEGMENT, trim($parts[1])),
            static fn (string $segment): bool => $segment !== ''
        ));

        $secret = [];
        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, EndpointMatcherInterface::PARAMETER_PREFIX)
                && $this->sanitizer->isSecretField(substr($segment, strlen(EndpointMatcherInterface::PARAMETER_PREFIX)))
            ) {
                $secret[$index] = true;
            }
        }

        return $secret;
    }
}
