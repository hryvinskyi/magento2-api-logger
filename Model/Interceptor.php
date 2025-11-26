<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterfaceFactory;
use Hryvinskyi\ApiLogger\Api\EndpointMatcherInterface;
use Hryvinskyi\ApiLogger\Api\InterceptorInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Api\SanitizerInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Interceptor Service
 *
 * Handles interception and logging of API requests
 */
class Interceptor implements InterceptorInterface
{
    /**
     * @param ConfigInterface $config
     * @param EndpointMatcherInterface $endpointMatcher
     * @param LogEntryInterfaceFactory $logEntryFactory
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param SanitizerInterface $sanitizer
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     * @param RemoteAddress $remoteAddress
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly EndpointMatcherInterface $endpointMatcher,
        private readonly LogEntryInterfaceFactory $logEntryFactory,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly SanitizerInterface $sanitizer,
        private readonly SerializerInterface $serializer,
        private readonly StoreManagerInterface $storeManager,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createLogEntry(
        string $endpoint,
        string $method,
        array $requestHeaders,
        ?string $requestBody
    ): LogEntryInterface {
        /** @var LogEntryInterface $logEntry */
        $logEntry = $this->logEntryFactory->create();

        $storeId = (int)$this->storeManager->getStore()->getId();
        $secretFields = $this->config->getSecretFields($storeId);

        // Set basic info
        $logEntry->setEndpoint($endpoint);
        $logEntry->setMethod($method);
        $logEntry->setStoreId($storeId);
        $logEntry->setIpAddress($this->remoteAddress->getRemoteAddress());

        // Set user agent
        if (isset($requestHeaders['User-Agent'])) {
            $logEntry->setUserAgent($requestHeaders['User-Agent']);
        }

        // Set request headers
        if ($this->config->shouldLogRequestHeaders($storeId)) {
            $headers = $this->config->shouldSanitizeSecrets($storeId)
                ? $this->sanitizer->sanitizeArray($requestHeaders, $secretFields)
                : $requestHeaders;

            $logEntry->setRequestHeaders($this->serializer->serialize($headers));
        }

        // Set request body
        if ($this->config->shouldLogRequestBody($storeId) && $requestBody !== null) {
            $body = $this->config->shouldSanitizeSecrets($storeId)
                ? $this->sanitizer->sanitize($requestBody, $secretFields)
                : $requestBody;

            $logEntry->setRequestBody(is_string($body) ? $body : $this->serializer->serialize($body));
        }
        return $logEntry;
    }

    /**
     * @inheritDoc
     */
    public function completeLogEntry(
        LogEntryInterface $logEntry,
        int $responseCode,
        array $responseHeaders,
        ?string $responseBody,
        float $duration,
        bool $isException
    ): void {
        try {
            $storeId = $logEntry->getStoreId();
            $secretFields = $this->config->getSecretFields($storeId);

            $logEntry->setResponseCode($responseCode);
            $logEntry->setDuration($duration);
            $logEntry->setIsException($isException);

            // Check if this response code should be logged
            if (!$this->shouldLogResponseCode($responseCode, $storeId)) {
                return;
            }

            // Set response headers
            if ($this->config->shouldLogResponseHeaders($storeId)) {
                $headers = $this->config->shouldSanitizeSecrets($storeId)
                    ? $this->sanitizer->sanitizeArray($responseHeaders, $secretFields)
                    : $responseHeaders;

                $logEntry->setResponseHeaders($this->serializer->serialize($headers));
            }

            // Set response body
            if ($this->config->shouldLogResponseBody($storeId) && $responseBody !== null) {
                $body = $this->config->shouldSanitizeSecrets($storeId)
                    ? $this->sanitizer->sanitize($responseBody, $secretFields)
                    : $responseBody;

                $logEntry->setResponseBody(is_string($body) ? $body : $this->serializer->serialize($body));
            }

            $this->logEntryRepository->save($logEntry);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Failed to save API log entry: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function shouldLogEndpoint(string $endpoint): bool
    {

        if (!$this->config->isEnabled()) {
            return false;
        }

        $enabledEndpoints = $this->config->getEnabledEndpoints();
        if (empty($enabledEndpoints)) {
            return false;
        }

        foreach ($enabledEndpoints as $pattern) {
            if ($this->endpointMatcher->matches($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if response code should be logged
     *
     * @param int $responseCode
     * @param int|null $storeId
     * @return bool
     */
    private function shouldLogResponseCode(int $responseCode, ?int $storeId = null): bool
    {
        $enabledResponseCodes = $this->config->getEnabledResponseCodes($storeId);

        // If no response codes are configured, log all response codes
        if (empty($enabledResponseCodes)) {
            return true;
        }

        // Check if the response code is in the enabled list
        return in_array((string)$responseCode, $enabledResponseCodes, true);
    }
}