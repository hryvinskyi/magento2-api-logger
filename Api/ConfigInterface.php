<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Configuration Service Interface
 *
 * Provides access to module configuration settings
 */
interface ConfigInterface
{
    public const XML_PATH_ENABLED = 'api_logger/general/enabled';
    public const XML_PATH_ENABLED_ENDPOINTS = 'api_logger/general/enabled_endpoints';
    public const XML_PATH_ENABLED_RESPONSE_CODES = 'api_logger/general/enabled_response_codes';
    public const XML_PATH_LOG_REQUEST_HEADERS = 'api_logger/general/log_request_headers';
    public const XML_PATH_LOG_REQUEST_BODY = 'api_logger/general/log_request_body';
    public const XML_PATH_LOG_RESPONSE_HEADERS = 'api_logger/general/log_response_headers';
    public const XML_PATH_LOG_RESPONSE_BODY = 'api_logger/general/log_response_body';
    public const XML_PATH_SANITIZE_SECRETS = 'api_logger/general/sanitize_secrets';
    public const XML_PATH_SECRET_FIELDS = 'api_logger/general/secret_fields';
    public const XML_PATH_RETENTION_DAYS = 'api_logger/cleanup/retention_days';
    public const XML_PATH_CLEANUP_ENABLED = 'api_logger/cleanup/enabled';

    /**
     * Check if logging is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Get list of enabled endpoints for logging
     *
     * Returns array of endpoint patterns (e.g., /V1/products/:id)
     *
     * @param int|null $storeId
     * @return array<string>
     */
    public function getEnabledEndpoints(?int $storeId = null): array;

    /**
     * Get list of enabled response codes for logging
     *
     * Returns array of HTTP response codes (e.g., ['200', '404', '500'])
     * Empty array means log all response codes
     *
     * @param int|null $storeId
     * @return array<string>
     */
    public function getEnabledResponseCodes(?int $storeId = null): array;

    /**
     * Check if request headers should be logged
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogRequestHeaders(?int $storeId = null): bool;

    /**
     * Check if request body should be logged
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogRequestBody(?int $storeId = null): bool;

    /**
     * Check if response headers should be logged
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogResponseHeaders(?int $storeId = null): bool;

    /**
     * Check if response body should be logged
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogResponseBody(?int $storeId = null): bool;

    /**
     * Check if secrets should be sanitized
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldSanitizeSecrets(?int $storeId = null): bool;

    /**
     * Get list of field names considered secrets
     *
     * @param int|null $storeId
     * @return array<string>
     */
    public function getSecretFields(?int $storeId = null): array;

    /**
     * Get retention period in days
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRetentionDays(?int $storeId = null): int;

    /**
     * Check if automatic cleanup is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCleanupEnabled(?int $storeId = null): bool;
}