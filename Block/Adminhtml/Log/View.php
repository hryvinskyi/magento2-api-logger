<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Block\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Log entry detail view block
 */
class View extends Template
{
    private ?LogEntryInterface $logEntry = null;

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     * @param EndpointUrlResolverInterface $endpointUrlResolver
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly SerializerInterface $serializer,
        private readonly StoreManagerInterface $storeManager,
        private readonly EndpointUrlResolverInterface $endpointUrlResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get log entry
     *
     * @return LogEntryInterface|null
     */
    public function getLogEntry(): ?LogEntryInterface
    {
        if ($this->logEntry === null) {
            $id = (int)$this->getRequest()->getParam('id');

            if ($id) {
                try {
                    $this->logEntry = $this->logEntryRepository->getById($id);
                } catch (NoSuchEntityException $e) {
                    return null;
                }
            }
        }

        return $this->logEntry;
    }

    /**
     * Format JSON for display
     *
     * @param string|null $json
     * @return string
     */
    public function formatJson(?string $json): string
    {
        if (empty($json)) {
            return '';
        }

        try {
            $data = $this->serializer->unserialize($json);
            return $this->serializer->serialize($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            return $json;
        }
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * Get delete URL
     *
     * @return string
     */
    public function getDeleteUrl(): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/delete', ['id' => $id]);
    }

    /**
     * Format body size for display
     *
     * @param string|null $body
     * @return string
     */
    public function formatBodySize(?string $body): string
    {
        if ($body === null || $body === '') {
            return '0 B';
        }

        $bytes = strlen($body);

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Get status badge class
     *
     * @param int|null $responseCode
     * @return string
     */
    public function getStatusBadgeClass(?int $responseCode): string
    {
        if ($responseCode === null) {
            return 'warning';
        }

        if ($responseCode >= 200 && $responseCode < 300) {
            return 'success';
        }

        if ($responseCode >= 300 && $responseCode < 400) {
            return 'info';
        }

        if ($responseCode >= 400 && $responseCode < 500) {
            return 'warning';
        }

        return 'error';
    }

    /**
     * Get store name by store ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getStoreName(?int $storeId): string
    {
        if ($storeId === null) {
            return 'N/A';
        }

        try {
            $store = $this->storeManager->getStore($storeId);
            return $store->getName() . ' (' . $store->getCode() . ')';
        } catch (\Exception $e) {
            return (string)$storeId;
        }
    }

    /**
     * Parse query parameters from endpoint URL
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array
    {
        $logEntry = $this->getLogEntry();
        if ($logEntry === null) {
            return [];
        }

        $endpoint = $logEntry->getEndpoint();
        $parsed = parse_url($endpoint);

        if (!isset($parsed['query'])) {
            return [];
        }

        parse_str($parsed['query'], $params);

        return $params;
    }

    /**
     * Get content-type badge label from headers JSON
     *
     * @param string|null $headersJson
     * @return string
     */
    public function getContentTypeBadge(?string $headersJson): string
    {
        if (empty($headersJson)) {
            return '';
        }

        try {
            $headers = $this->serializer->unserialize($headersJson);
        } catch (\Exception $e) {
            return '';
        }

        $contentType = '';
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'content-type') {
                $contentType = is_array($value) ? (string)reset($value) : (string)$value;
                break;
            }
        }

        if ($contentType === '') {
            return '';
        }

        if (str_contains($contentType, 'application/json')) {
            return 'JSON';
        }
        if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
            return 'XML';
        }
        if (str_contains($contentType, 'text/html')) {
            return 'HTML';
        }
        if (str_contains($contentType, 'text/plain')) {
            return 'TEXT';
        }
        if (str_contains($contentType, 'multipart/form-data')) {
            return 'FORM-DATA';
        }
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            return 'FORM';
        }

        return strtoupper(explode('/', explode(';', $contentType)[0])[1] ?? '');
    }

    /**
     * Get line count of a body string
     *
     * @param string|null $body
     * @return int
     */
    public function getLineCount(?string $body): int
    {
        if ($body === null || $body === '') {
            return 0;
        }

        return substr_count($body, "\n") + 1;
    }

    /**
     * Resolve the full absolute URL for the logged endpoint
     *
     * @return string
     */
    public function getFullEndpointUrl(): string
    {
        $logEntry = $this->getLogEntry();
        if ($logEntry === null) {
            return '';
        }

        return $this->endpointUrlResolver->resolve(
            $logEntry->getEndpoint(),
            $logEntry->getRequestHeaders(),
            $logEntry->getStoreId()
        );
    }

    /**
     * Get the export URL
     *
     * @param string $format
     * @return string
     */
    public function getExportUrl(string $format): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/export', ['id' => $id, 'format' => $format]);
    }

    /**
     * Get the related logs AJAX URL
     *
     * @return string
     */
    public function getRelatedUrl(): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/related', ['id' => $id]);
    }

    /**
     * Get the stats AJAX URL
     *
     * @return string
     */
    public function getStatsUrl(): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/stats', ['id' => $id]);
    }

    /**
     * Get the replay AJAX URL
     *
     * @return string
     */
    public function getReplayUrl(): string
    {
        $id = (int)$this->getRequest()->getParam('id');
        return $this->getUrl('*/*/replay', ['id' => $id]);
    }

    /**
     * Get the compare AJAX URL
     *
     * @return string
     */
    public function getCompareUrl(): string
    {
        return $this->getUrl('*/*/compare');
    }
}