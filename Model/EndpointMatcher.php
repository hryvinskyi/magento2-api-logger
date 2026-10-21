<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\EndpointMatcherInterface;
use Hryvinskyi\ApiLogger\Model\Recording\RoutePath;

/**
 * Matches a called path against a "METHOD|/V1/route" pattern, the way Web API routes are written
 *
 * The store code the call was made with is ignored. A segment ":name" matches any one segment; a last segment "*"
 * matches the rest of the path. The method compares without case.
 */
class EndpointMatcher implements EndpointMatcherInterface
{
    /**
     * @param RoutePath $routePath
     */
    public function __construct(
        private readonly RoutePath $routePath
    ) {
    }

    /**
     * @inheritDoc
     */
    public function matches(string $endpoint, string $method, string $pattern): bool
    {
        if (!str_contains($pattern, self::SEPARATOR)) {
            return false;
        }

        [$patternMethod, $patternPath] = explode(self::SEPARATOR, $pattern, 2);
        if (strcasecmp(trim($patternMethod), $method) !== 0) {
            return false;
        }

        return $this->pathMatches(
            $this->segments($this->routePath->route($endpoint)),
            $this->segments($patternPath)
        );
    }

    /**
     * Whether the called segments fit the pattern's segments
     *
     * @param list<string> $path
     * @param list<string> $pattern
     * @return bool
     */
    private function pathMatches(array $path, array $pattern): bool
    {
        foreach ($pattern as $index => $part) {
            if ($part === self::REST_OF_PATH && $index === count($pattern) - 1) {
                return count($path) > $index;
            }

            $called = $path[$index] ?? null;
            if ($called === null || ($part !== $called && !str_starts_with($part, self::PARAMETER_PREFIX))) {
                return false;
            }
        }

        return count($path) === count($pattern);
    }

    /**
     * Non-empty segments of a path
     *
     * @param string $path
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim($path)), static fn (string $part): bool => $part !== ''));
    }
}
