<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves relative API endpoint paths to full absolute URLs
 */
class EndpointUrlResolver implements EndpointUrlResolverInterface
{
    /**
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $endpoint, ?string $requestHeadersJson, ?int $storeId): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        $host = $this->extractHeaderValue($requestHeadersJson, 'host');

        if ($host !== '') {
            $proto = $this->extractHeaderValue($requestHeadersJson, 'x-forwarded-proto');
            $scheme = (strtolower($proto) === 'http') ? 'http' : 'https';

            return $scheme . '://' . $host . '/' . ltrim($endpoint, '/');
        }

        try {
            $baseUrl = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(), '/');

            return $baseUrl . '/' . ltrim($endpoint, '/');
        } catch (\Exception $e) {
            return $endpoint;
        }
    }

    /**
     * Extract a header value from request headers JSON by name (case-insensitive)
     *
     * @param string|null $headersJson
     * @param string $headerName
     * @return string
     */
    private function extractHeaderValue(?string $headersJson, string $headerName): string
    {
        if (empty($headersJson)) {
            return '';
        }

        try {
            $headers = $this->serializer->unserialize($headersJson);
        } catch (\Exception $e) {
            return '';
        }

        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === $headerName) {
                return is_array($value) ? (string)reset($value) : (string)$value;
            }
        }

        return '';
    }
}
