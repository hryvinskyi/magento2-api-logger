<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * REST logging settings read from the store configuration
 */
class Config implements ConfigInterface
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getEnabledEndpoints(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_ENABLED_ENDPOINTS, ScopeInterface::SCOPE_STORE, $storeId);
        if (!is_string($value) || $value === '') {
            return [];
        }

        try {
            $endpoints = $this->serializer->unserialize($value);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($endpoints) ? $this->texts($endpoints) : [];
    }

    /**
     * @inheritDoc
     */
    public function getEnabledResponseCodes(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ENABLED_RESPONSE_CODES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return is_string($value) ? $this->texts(explode(',', $value)) : [];
    }

    /**
     * @inheritDoc
     */
    public function shouldLogRequestHeaders(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_LOG_REQUEST_HEADERS, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function shouldLogRequestBody(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_LOG_REQUEST_BODY, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function shouldLogResponseHeaders(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_LOG_RESPONSE_HEADERS, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function shouldLogResponseBody(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_LOG_RESPONSE_BODY, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getReplayBaseUrl(): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_REPLAY_BASE_URL);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return rtrim(trim($value), '/') . '/';
    }

    /**
     * @inheritDoc
     */
    public function getReplayToken(): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_REPLAY_TOKEN);
        if (!is_string($value) || $value === '') {
            return null;
        }

        $token = trim($this->encryptor->decrypt($value));

        return $token === '' ? null : $token;
    }

    /**
     * A store-scoped yes/no setting
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Trimmed, non-empty texts of a list
     *
     * @param array<mixed> $values
     * @return list<string>
     */
    private function texts(array $values): array
    {
        $texts = [];
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $texts[] = trim((string) $value);
            }
        }

        return $texts;
    }
}
