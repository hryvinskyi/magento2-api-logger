<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\EndpointMatcherInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Webapi\Controller\PathProcessor;
use Magento\Webapi\Controller\Rest\Router\Route;

/**
 * Endpoint Pattern Matcher
 *
 * Matches API endpoints against patterns using Magento's route matching logic
 */
class EndpointMatcher implements EndpointMatcherInterface
{
    public function __construct(
        private readonly PathProcessor $pathProcessor,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @inheritDoc
     */
    public function matches(string $endpoint, string $method, string $pattern): bool
    {
        [$patternMethod, $patternEndpoint] = $this->parsePattern($pattern);

        if (!$this->methodMatches($method, $patternMethod)) {
            return false;
        }

        $processedPath = $this->pathProcessor->process($endpoint);

        return $this->pathMatches($processedPath, $patternEndpoint);
    }

    /**
     * Parse pattern into method and endpoint components
     *
     * Expected format: "METHOD|/endpoint/path"
     * Example: "GET|/V1/products/:id"
     *
     * @param string $pattern The pattern to parse
     * @return array{0: string, 1: string} Array containing [method, endpoint]
     */
    private function parsePattern(string $pattern): array
    {
        return explode('|', $pattern, 2);
    }

    /**
     * Check if HTTP method matches the pattern method (case-insensitive)
     *
     * @param string $method The HTTP method to check
     * @param string $patternMethod The pattern method to match against
     * @return bool True if methods match (case-insensitive), false otherwise
     */
    private function methodMatches(string $method, string $patternMethod): bool
    {
        return mb_strtolower($method) === mb_strtolower($patternMethod);
    }

    /**
     * Check if processed path matches the pattern endpoint
     *
     * First attempts exact match, then falls back to route pattern matching
     * using Magento's route matching logic for wildcards and parameters.
     *
     * @param string $processedPath The processed endpoint path
     * @param string $patternEndpoint The pattern endpoint to match against
     * @return bool True if path matches pattern, false otherwise
     */
    private function pathMatches(string $processedPath, string $patternEndpoint): bool
    {
        // Check for exact match first (optimization)
        if ($processedPath === $patternEndpoint) {
            return true;
        }

        // Fall back to Magento route pattern matching for wildcards/parameters
        return $this->routeMatches($processedPath, $patternEndpoint);
    }

    /**
     * Use Magento's route matching logic to check pattern match
     *
     * Supports patterns like:
     * - /V1/products/:id (parameter placeholder)
     *
     * @param string $processedPath The processed endpoint path
     * @param string $patternEndpoint The pattern endpoint with placeholders
     * @return bool True if route matches pattern, false otherwise
     */
    private function routeMatches(string $processedPath, string $patternEndpoint): bool
    {
        $route = new Route($patternEndpoint);
        $this->request->setPathInfo($processedPath);

        return (bool) $route->match($this->request);
    }
}