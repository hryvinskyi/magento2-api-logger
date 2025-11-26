<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Plugin\WebApi;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\InterceptorInterface;
use Hryvinskyi\ApiLogger\Api\Rest\ExtractorInterface;
use Magento\Framework\App\HttpRequestInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Webapi\Controller\Rest;
use Psr\Log\LoggerInterface;

/**
 * REST API Interceptor Plugin
 *
 * Intercepts REST API requests and responses for logging
 */
class RestInterceptor
{
    private ?LogEntryInterface $currentLogEntry = null;
    private ?float $startTime = null;

    /**
     * @param InterceptorInterface $interceptor
     * @param ExtractorInterface $extractor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly InterceptorInterface $interceptor,
        private readonly ExtractorInterface $extractor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Before dispatch - capture request data
     *
     * @param Rest $subject
     * @param RequestInterface $request
     * @return array<RequestInterface>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(Rest $subject, RequestInterface $request): array
    {
        try {
            if (!$request instanceof HttpRequestInterface) {
                return [$request];
            }

            $endpoint = $this->extractor->extractEndpoint($request);
            $method = $this->extractor->extractMethod($request);

            if (!$this->interceptor->shouldLogEndpoint($endpoint, $method)) {
                return [$request];
            }

            $this->startTime = microtime(true);

            // Extract request data using extractor service
            $headers = $this->extractor->extractRequestHeaders($request);
            $body = $this->extractor->extractRequestBody($request);

            // Create log entry
            $this->currentLogEntry = $this->interceptor->createLogEntry(
                $endpoint,
                $method,
                $headers,
                $body
            );
        } catch (\Throwable $exception) {
            // Log exception during logging process
            $this->logger->critical(
                'API Logger RestInterceptor error: ' . $exception->getMessage(),
                ['exception' => $exception->getTrace()]
            );
        }

        return [$request];
    }

    /**
     * After Dispatch - capture response data
     *
     * @param Rest $subject
     * @param \Magento\Framework\App\ResponseInterface $result
     * @return \Magento\Framework\App\ResponseInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDispatch(Rest $subject, mixed $result): mixed
    {
        try {
            if ($this->currentLogEntry === null || $this->startTime === null) {
                return $result;
            }

            $endTime = microtime(true);
            $duration = ($endTime - $this->startTime) * 1000; // Convert to milliseconds

            // Extract response data using extractor service
            $isException = $this->extractor->isException($result);
            $responseCode = $this->extractor->extractResponseCode($result);
            $responseHeaders = $this->extractor->extractResponseHeaders($result);
            $responseBody = $this->extractor->extractResponseBody($result);
            if ($isException) {
                $responseBody = $this->extractor->extractExceptionBody($result);
            }

            // Complete and save log entry
            $this->interceptor->completeLogEntry(
                $this->currentLogEntry,
                $responseCode,
                $responseHeaders,
                $responseBody,
                $duration,
                $isException
            );

            // Reset for next request
            $this->currentLogEntry = null;
            $this->startTime = null;
        } catch (\Throwable $exception) {
            // Log exception during logging process
            $this->logger->critical(
                'API Logger RestInterceptor error: ' . $exception->getMessage(),
                ['exception' => $exception->getTrace()]
            );
        }

        return $result;
    }
}