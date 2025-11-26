<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api\Data;

/**
 * API Log Entry Data Interface
 *
 * Represents a single API request/response log entry
 */
interface LogEntryInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ENDPOINT = 'endpoint';
    public const METHOD = 'method';
    public const REQUEST_HEADERS = 'request_headers';
    public const REQUEST_BODY = 'request_body';
    public const RESPONSE_HEADERS = 'response_headers';
    public const RESPONSE_BODY = 'response_body';
    public const RESPONSE_CODE = 'response_code';
    public const DURATION = 'duration';
    public const IS_EXCEPTION = 'is_exception';
    public const STORE_ID = 'store_id';
    public const IP_ADDRESS = 'ip_address';
    public const USER_AGENT = 'user_agent';
    public const CREATED_AT = 'created_at';

    /**
     * Get API endpoint
     *
     * @return string
     */
    public function getEndpoint(): string;

    /**
     * Set API endpoint
     *
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint(string $endpoint): self;

    /**
     * Get HTTP method
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Set HTTP method
     *
     * @param string $method
     * @return $this
     */
    public function setMethod(string $method): self;

    /**
     * Get request headers as JSON
     *
     * @return string|null
     */
    public function getRequestHeaders(): ?string;

    /**
     * Set request headers as JSON
     *
     * @param string|null $headers
     * @return $this
     */
    public function setRequestHeaders(?string $headers): self;

    /**
     * Get request body
     *
     * @return string|null
     */
    public function getRequestBody(): ?string;

    /**
     * Set request body
     *
     * @param string|null $body
     * @return $this
     */
    public function setRequestBody(?string $body): self;

    /**
     * Get response headers as JSON
     *
     * @return string|null
     */
    public function getResponseHeaders(): ?string;

    /**
     * Set response headers as JSON
     *
     * @param string|null $headers
     * @return $this
     */
    public function setResponseHeaders(?string $headers): self;

    /**
     * Get response body
     *
     * @return string|null
     */
    public function getResponseBody(): ?string;

    /**
     * Set response body
     *
     * @param string|null $body
     * @return $this
     */
    public function setResponseBody(?string $body): self;

    /**
     * Get HTTP response code
     *
     * @return int|null
     */
    public function getResponseCode(): ?int;

    /**
     * Set HTTP response code
     *
     * @param int|null $code
     * @return $this
     */
    public function setResponseCode(?int $code): self;

    /**
     * Get request duration in milliseconds
     *
     * @return float|null
     */
    public function getDuration(): ?float;

    /**
     * Set request duration in milliseconds
     *
     * @param float|null $duration
     * @return $this
     */
    public function setDuration(?float $duration): self;

    /**
     * Check if response was an exception
     *
     * @return bool
     */
    public function isException(): bool;

    /**
     * Set exception flag
     *
     * @param bool $isException
     * @return $this
     */
    public function setIsException(bool $isException): self;

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Set store ID
     *
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId(?int $storeId): self;

    /**
     * Get IP address
     *
     * @return string|null
     */
    public function getIpAddress(): ?string;

    /**
     * Set IP address
     *
     * @param string|null $ipAddress
     * @return $this
     */
    public function setIpAddress(?string $ipAddress): self;

    /**
     * Get user agent
     *
     * @return string|null
     */
    public function getUserAgent(): ?string;

    /**
     * Set user agent
     *
     * @param string|null $userAgent
     * @return $this
     */
    public function setUserAgent(?string $userAgent): self;

    /**
     * Get creation timestamp
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set creation timestamp
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;
}