<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api\Rest;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Webapi\Rest\Response as RestResponse;

/**
 * Reads what a REST call sent and what it answered
 *
 * @api
 */
interface ExtractorInterface
{
    /**
     * Path of the call after the REST front name, store code and query included: "/default/V1/products?page=2"
     *
     * @param HttpRequest $request
     * @return string
     */
    public function extractEndpoint(HttpRequest $request): string;

    /**
     * HTTP method, upper case
     *
     * @param HttpRequest $request
     * @return string
     */
    public function extractMethod(HttpRequest $request): string;

    /**
     * Request headers by name; a header sent more than once has a list of values
     *
     * @param HttpRequest $request
     * @return array<string, string|list<string>>
     */
    public function extractRequestHeaders(HttpRequest $request): array;

    /**
     * Request body, or null when there is none
     *
     * @param HttpRequest $request
     * @return string|null
     */
    public function extractRequestBody(HttpRequest $request): ?string;

    /**
     * HTTP status of the response
     *
     * @param RestResponse $response
     * @return int
     */
    public function extractResponseCode(RestResponse $response): int;

    /**
     * Response headers by name; a header sent more than once has a list of values
     *
     * @param RestResponse $response
     * @return array<string, string|list<string>>
     */
    public function extractResponseHeaders(RestResponse $response): array;

    /**
     * Response body; for a call that failed, the error the client receives
     *
     * @param RestResponse $response
     * @return string|null
     */
    public function extractResponseBody(RestResponse $response): ?string;

    /**
     * Whether the call ended in an error
     *
     * @param RestResponse $response
     * @return bool
     */
    public function isException(RestResponse $response): bool;
}
