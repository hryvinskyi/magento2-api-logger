<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Data;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Magento\Framework\Model\AbstractModel;

/**
 * A logged REST call, stored in its own table and journaled as an activity
 *
 * The endpoint is stored in two columns: the path, indexed and cut to its column's length, and the query, kept whole.
 * Readers get the one string back.
 */
class LogEntry extends AbstractModel implements LogEntryInterface
{
    private const QUERY_SEPARATOR = '?';

    /**
     * Bind the model to its table
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(LogEntryResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return $this->nullableInt(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getEndpoint(): string
    {
        $query = $this->nullableText(self::QUERY_STRING);

        return $this->text(self::ENDPOINT) . ($query !== null ? self::QUERY_SEPARATOR . $query : '');
    }

    /**
     * @inheritDoc
     */
    public function setEndpoint(string $endpoint): self
    {
        $parts = explode(self::QUERY_SEPARATOR, $endpoint, 2);

        return $this->setData(self::ENDPOINT, mb_substr($parts[0], 0, LogEntryResource::PATH_MAX_LENGTH))
            ->setData(self::QUERY_STRING, $parts[1] ?? null);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->text(self::METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setMethod(string $method): self
    {
        return $this->setData(self::METHOD, $method);
    }

    /**
     * @inheritDoc
     */
    public function getRequestHeaders(): ?string
    {
        return $this->nullableText(self::REQUEST_HEADERS);
    }

    /**
     * @inheritDoc
     */
    public function setRequestHeaders(?string $headers): self
    {
        return $this->setData(self::REQUEST_HEADERS, $headers);
    }

    /**
     * @inheritDoc
     */
    public function getRequestBody(): ?string
    {
        return $this->nullableText(self::REQUEST_BODY);
    }

    /**
     * @inheritDoc
     */
    public function setRequestBody(?string $body): self
    {
        return $this->setData(self::REQUEST_BODY, $body);
    }

    /**
     * @inheritDoc
     */
    public function getResponseHeaders(): ?string
    {
        return $this->nullableText(self::RESPONSE_HEADERS);
    }

    /**
     * @inheritDoc
     */
    public function setResponseHeaders(?string $headers): self
    {
        return $this->setData(self::RESPONSE_HEADERS, $headers);
    }

    /**
     * @inheritDoc
     */
    public function getResponseBody(): ?string
    {
        return $this->nullableText(self::RESPONSE_BODY);
    }

    /**
     * @inheritDoc
     */
    public function setResponseBody(?string $body): self
    {
        return $this->setData(self::RESPONSE_BODY, $body);
    }

    /**
     * @inheritDoc
     */
    public function getResponseCode(): ?int
    {
        return $this->nullableInt(self::RESPONSE_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setResponseCode(?int $code): self
    {
        return $this->setData(self::RESPONSE_CODE, $code);
    }

    /**
     * @inheritDoc
     */
    public function getDuration(): ?float
    {
        return $this->nullableFloat(self::DURATION);
    }

    /**
     * @inheritDoc
     */
    public function setDuration(?float $duration): self
    {
        return $this->setData(self::DURATION, $duration);
    }

    /**
     * @inheritDoc
     */
    public function isException(): bool
    {
        return (bool) $this->getData(self::IS_EXCEPTION);
    }

    /**
     * @inheritDoc
     */
    public function setIsException(bool $isException): self
    {
        return $this->setData(self::IS_EXCEPTION, $isException);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->nullableInt(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(?int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getIpAddress(): ?string
    {
        return $this->nullableText(self::IP_ADDRESS);
    }

    /**
     * @inheritDoc
     */
    public function setIpAddress(?string $ipAddress): self
    {
        return $this->setData(self::IP_ADDRESS, $ipAddress);
    }

    /**
     * @inheritDoc
     */
    public function getUserAgent(): ?string
    {
        return $this->nullableText(self::USER_AGENT);
    }

    /**
     * @inheritDoc
     */
    public function setUserAgent(?string $userAgent): self
    {
        return $this->setData(self::USER_AGENT, $userAgent);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->nullableText(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getActivityId(): ?int
    {
        return $this->nullableInt(self::ACTIVITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setActivityId(?int $activityId): self
    {
        return $this->setData(self::ACTIVITY_ID, $activityId);
    }

    /**
     * @inheritDoc
     */
    public function getCorrelationId(): ?string
    {
        return $this->nullableText(self::CORRELATION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCorrelationId(?string $correlationId): self
    {
        return $this->setData(self::CORRELATION_ID, $correlationId);
    }

    /**
     * A stored value as text
     *
     * @param string $key
     * @return string
     */
    private function text(string $key): string
    {
        $value = $this->getData($key);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A stored value as text, or null when there is none
     *
     * @param string $key
     * @return string|null
     */
    private function nullableText(string $key): ?string
    {
        $value = $this->getData($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * A stored value as a whole number, or null when there is none
     *
     * @param string $key
     * @return int|null
     */
    private function nullableInt(string $key): ?int
    {
        $value = $this->getData($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * A stored value as a number, or null when there is none
     *
     * @param string $key
     * @return float|null
     */
    private function nullableFloat(string $key): ?float
    {
        $value = $this->getData($key);

        return is_numeric($value) ? (float) $value : null;
    }
}
