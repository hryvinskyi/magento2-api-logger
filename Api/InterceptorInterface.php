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
 * Interceptor Service Interface
 *
 * Handles interception and logging of API requests
 */
interface InterceptorInterface
{
    /**
     * Create log entry from request data
     *
     * @param string $endpoint
     * @param string $method
     * @param array<string, string> $requestHeaders
     * @param string|null $requestBody
     * @return LogEntryInterface
     */
    public function createLogEntry(
        string $endpoint,
        string $method,
        array $requestHeaders,
        ?string $requestBody
    ): LogEntryInterface;

    /**
     * Complete log entry with response data
     *
     * @param LogEntryInterface $logEntry
     * @param int $responseCode
     * @param array<string, string> $responseHeaders
     * @param string|null $responseBody
     * @param float $duration
     * @param bool $isException
     * @return void
     */
    public function completeLogEntry(
        LogEntryInterface $logEntry,
        int $responseCode,
        array $responseHeaders,
        ?string $responseBody,
        float $duration,
        bool $isException
    ): void;

    /**
     * Check if endpoint should be logged
     *
     * @param string $endpoint
     * @return bool
     */
    public function shouldLogEndpoint(string $endpoint): bool;
}