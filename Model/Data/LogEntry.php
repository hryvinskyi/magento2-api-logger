<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Data;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Log Entry Data Model
 */
class LogEntry extends AbstractModel implements LogEntryInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(\Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry::class);
    }

    /**
     * @inheritDoc
     */
    public function getEndpoint(): string
    {
        return (string)$this->getData(self::ENDPOINT);
    }

    /**
     * @inheritDoc
     */
    public function setEndpoint(string $endpoint): self
    {
        return $this->setData(self::ENDPOINT, $endpoint);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return (string)$this->getData(self::METHOD);
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
        return $this->getData(self::REQUEST_HEADERS);
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
        return $this->getData(self::REQUEST_BODY);
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
        return $this->getData(self::RESPONSE_HEADERS);
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
        return $this->getData(self::RESPONSE_BODY);
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
        $value = $this->getData(self::RESPONSE_CODE);
        return $value !== null ? (int)$value : null;
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
        $value = $this->getData(self::DURATION);
        return $value !== null ? (float)$value : null;
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
        return (bool)$this->getData(self::IS_EXCEPTION);
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
        $value = $this->getData(self::STORE_ID);
        return $value !== null ? (int)$value : null;
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
        return $this->getData(self::IP_ADDRESS);
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
        return $this->getData(self::USER_AGENT);
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
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}