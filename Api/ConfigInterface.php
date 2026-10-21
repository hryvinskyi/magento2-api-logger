<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * REST logging settings
 *
 * Secrets are masked by the activity log's own secret list, and the REST channel's retention is applied by the
 * activity log; neither is a setting of this module any more.
 *
 * @api
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
    public const XML_PATH_RETENTION_DAYS = 'api_logger/retention/days';
    public const XML_PATH_REPLAY_BASE_URL = 'api_logger/replay/base_url';
    public const XML_PATH_REPLAY_TOKEN = 'api_logger/replay/token';

    /**
     * Whether REST calls are logged
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Endpoint patterns that are logged, as "METHOD|/V1/route"
     *
     * @param int|null $storeId
     * @return list<string>
     */
    public function getEnabledEndpoints(?int $storeId = null): array;

    /**
     * Response codes that are logged; every code when empty
     *
     * @param int|null $storeId
     * @return list<string>
     */
    public function getEnabledResponseCodes(?int $storeId = null): array;

    /**
     * Whether request headers are stored
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogRequestHeaders(?int $storeId = null): bool;

    /**
     * Whether request bodies are stored
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogRequestBody(?int $storeId = null): bool;

    /**
     * Whether response headers are stored
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogResponseHeaders(?int $storeId = null): bool;

    /**
     * Whether response bodies are stored
     *
     * @param int|null $storeId
     * @return bool
     */
    public function shouldLogResponseBody(?int $storeId = null): bool;

    /**
     * Base URL replayed calls are sent to, e.g. a staging store; null to replay against this store
     *
     * @return string|null
     */
    public function getReplayBaseUrl(): ?string;

    /**
     * Bearer token replays to the replay base URL carry, or null
     *
     * @return string|null
     */
    public function getReplayToken(): ?string;
}
