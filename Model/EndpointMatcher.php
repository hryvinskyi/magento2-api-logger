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
        // Extract method and endpoint from pattern
        [$patternMethod, $patternEndpoint] = explode('|', $pattern, 2);

        if (mb_strtolower($patternMethod) === mb_strtolower($method)) {
            $route = new Route($patternEndpoint);

            $processedPath = $this->pathProcessor->process($endpoint);
            $this->request->setPathInfo($processedPath);

            return (bool) $route->match($this->request);
        }

        return false;
    }
}