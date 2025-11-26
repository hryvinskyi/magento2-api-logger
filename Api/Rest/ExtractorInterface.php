<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api\Rest;

use Magento\Framework\App\HttpRequestInterface as RequestInterface;
use Magento\Framework\App\ResponseInterface;

/**
 * REST Extractor Interface
 *
 * Extracts data from Magento REST API request and response objects
 */
interface ExtractorInterface
{
    /**
     * Extract endpoint path from request
     *
     * @param RequestInterface $request
     * @return string
     */
    public function extractEndpoint(RequestInterface $request): string;

    /**
     * Extract HTTP method from request
     *
     * @param RequestInterface $request
     * @return string
     */
    public function extractMethod(RequestInterface $request): string;

    /**
     * Extract headers from request
     *
     * @param RequestInterface $request
     * @return array<string, string>
     */
    public function extractRequestHeaders(RequestInterface $request): array;

    /**
     * Extract body content from request
     *
     * @param RequestInterface $request
     * @return string|null
     */
    public function extractRequestBody(RequestInterface $request): ?string;

    /**
     * Extract HTTP response code from response
     *
     * @param ResponseInterface $response
     * @return int
     */
    public function extractResponseCode(ResponseInterface $response): int;

    /**
     * Extract headers from response
     *
     * @param ResponseInterface $response
     * @return array<string, string>
     */
    public function extractResponseHeaders(ResponseInterface $response): array;

    /**
     * Extract body content from response
     *
     * @param ResponseInterface $response
     * @return string|null
     */
    public function extractResponseBody(ResponseInterface $response): ?string;

    /**
     * Extract exception body from response
     *
     * @param ResponseInterface $response
     * @return string|null
     */
    public function extractExceptionBody(ResponseInterface $response): ?string;

    /**
     * Check if response represents an exception
     *
     * @param ResponseInterface $response
     * @return bool
     */
    public function isException(ResponseInterface $response): bool;
}