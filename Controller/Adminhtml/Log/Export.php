<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\File;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Export log entry as HAR or Raw HTTP
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param FileFactory $fileFactory
     * @param SerializerInterface $serializer
     * @param EndpointUrlResolverInterface $endpointUrlResolver
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly FileFactory $fileFactory,
        private readonly SerializerInterface $serializer,
        private readonly EndpointUrlResolverInterface $endpointUrlResolver
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface|ResponseInterface|File
    {
        $id = (int)$this->getRequest()->getParam('id');
        $format = (string)$this->getRequest()->getParam('format', 'http');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid log entry ID.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $logEntry = $this->logEntryRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Log entry not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        if ($format === 'har') {
            return $this->exportHar($logEntry, $id);
        }

        return $this->exportRawHttp($logEntry, $id);
    }

    /**
     * Export as HAR format
     *
     * @param \Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface $logEntry
     * @param int $id
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function exportHar($logEntry, int $id)
    {
        $requestHeaders = $this->parseHeaders($logEntry->getRequestHeaders());
        $responseHeaders = $this->parseHeaders($logEntry->getResponseHeaders());
        $fullUrl = $this->endpointUrlResolver->resolve(
            $logEntry->getEndpoint(),
            $logEntry->getRequestHeaders(),
            $logEntry->getStoreId()
        );

        $har = [
            'log' => [
                'version' => '1.2',
                'creator' => [
                    'name' => 'Hryvinskyi API Logger',
                    'version' => '1.0.0',
                ],
                'entries' => [
                    [
                        'startedDateTime' => $logEntry->getCreatedAt() ?? date('c'),
                        'time' => $logEntry->getDuration() ?? 0,
                        'request' => [
                            'method' => $logEntry->getMethod(),
                            'url' => $fullUrl,
                            'httpVersion' => 'HTTP/1.1',
                            'headers' => $requestHeaders,
                            'queryString' => $this->parseQueryString($logEntry->getEndpoint()),
                            'postData' => [
                                'mimeType' => $this->getContentType($logEntry->getRequestHeaders()) ?: 'application/json',
                                'text' => $logEntry->getRequestBody() ?? '',
                            ],
                            'headersSize' => -1,
                            'bodySize' => strlen($logEntry->getRequestBody() ?? ''),
                        ],
                        'response' => [
                            'status' => $logEntry->getResponseCode() ?? 0,
                            'statusText' => '',
                            'httpVersion' => 'HTTP/1.1',
                            'headers' => $responseHeaders,
                            'content' => [
                                'size' => strlen($logEntry->getResponseBody() ?? ''),
                                'mimeType' => $this->getContentType($logEntry->getResponseHeaders()) ?: 'application/json',
                                'text' => $logEntry->getResponseBody() ?? '',
                            ],
                            'headersSize' => -1,
                            'bodySize' => strlen($logEntry->getResponseBody() ?? ''),
                        ],
                        'cache' => [],
                        'timings' => [
                            'send' => 0,
                            'wait' => $logEntry->getDuration() ?? 0,
                            'receive' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $content = $this->serializer->serialize($har);
        $fileName = 'api_log_' . $id . '.har';

        return $this->fileFactory->create(
            $fileName,
            $content,
            DirectoryList::VAR_DIR,
            'application/json'
        );
    }

    /**
     * Export as Raw HTTP format
     *
     * @param \Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface $logEntry
     * @param int $id
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function exportRawHttp($logEntry, int $id)
    {
        $fullUrl = $this->endpointUrlResolver->resolve(
            $logEntry->getEndpoint(),
            $logEntry->getRequestHeaders(),
            $logEntry->getStoreId()
        );
        $parsed = parse_url($fullUrl);
        $path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $output = $logEntry->getMethod() . ' ' . $path . " HTTP/1.1\r\n";

        if (isset($parsed['host'])) {
            $output .= 'Host: ' . $parsed['host'] . "\r\n";
        }

        $headers = $this->parseHeaders($logEntry->getRequestHeaders());
        foreach ($headers as $header) {
            $output .= $header['name'] . ': ' . $header['value'] . "\r\n";
        }

        $output .= "\r\n";

        if ($logEntry->getRequestBody()) {
            $output .= $logEntry->getRequestBody() . "\r\n\r\n";
        }

        $output .= "HTTP/1.1 " . ($logEntry->getResponseCode() ?? '000') . "\r\n";

        $responseHeaders = $this->parseHeaders($logEntry->getResponseHeaders());
        foreach ($responseHeaders as $header) {
            $output .= $header['name'] . ': ' . $header['value'] . "\r\n";
        }

        $output .= "\r\n";

        if ($logEntry->getResponseBody()) {
            $output .= $logEntry->getResponseBody();
        }

        $fileName = 'api_log_' . $id . '.txt';

        return $this->fileFactory->create(
            $fileName,
            $output,
            DirectoryList::VAR_DIR,
            'text/plain'
        );
    }

    /**
     * Parse JSON headers into HAR-compatible array
     *
     * @param string|null $headersJson
     * @return array<int, array{name: string, value: string}>
     */
    private function parseHeaders(?string $headersJson): array
    {
        if (empty($headersJson)) {
            return [];
        }

        try {
            $headers = $this->serializer->unserialize($headersJson);
        } catch (\Exception $e) {
            return [];
        }

        $result = [];
        foreach ($headers as $name => $value) {
            $result[] = [
                'name' => (string)$name,
                'value' => is_array($value) ? implode(', ', $value) : (string)$value,
            ];
        }

        return $result;
    }

    /**
     * Parse query string from endpoint URL
     *
     * @param string $endpoint
     * @return array<int, array{name: string, value: string}>
     */
    private function parseQueryString(string $endpoint): array
    {
        $parsed = parse_url($endpoint);
        if (!isset($parsed['query'])) {
            return [];
        }

        parse_str($parsed['query'], $params);
        $result = [];
        foreach ($params as $name => $value) {
            $result[] = [
                'name' => (string)$name,
                'value' => is_array($value) ? implode(', ', $value) : (string)$value,
            ];
        }

        return $result;
    }

    /**
     * Extract Content-Type from headers JSON
     *
     * @param string|null $headersJson
     * @return string
     */
    private function getContentType(?string $headersJson): string
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
            if (strtolower((string)$key) === 'content-type') {
                return is_array($value) ? (string)reset($value) : (string)$value;
            }
        }

        return '';
    }
}
