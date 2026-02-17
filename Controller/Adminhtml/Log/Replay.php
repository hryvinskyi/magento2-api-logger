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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Replay an API request
 */
class Replay extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::replay';

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param CurlFactory $curlFactory
     * @param SerializerInterface $serializer
     * @param JsonFactory $jsonFactory
     * @param EndpointUrlResolverInterface $endpointUrlResolver
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly CurlFactory $curlFactory,
        private readonly SerializerInterface $serializer,
        private readonly JsonFactory $jsonFactory,
        private readonly EndpointUrlResolverInterface $endpointUrlResolver
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        if (!$id) {
            return $result->setData(['error' => true, 'message' => 'Invalid log entry ID.']);
        }

        try {
            $logEntry = $this->logEntryRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['error' => true, 'message' => 'Log entry not found.']);
        }

        $endpoint = $this->endpointUrlResolver->resolve(
            $logEntry->getEndpoint(),
            $logEntry->getRequestHeaders(),
            $logEntry->getStoreId()
        );
        $method = strtoupper($logEntry->getMethod());
        $requestBody = $logEntry->getRequestBody();

        $curl = $this->curlFactory->create();
        $curl->setTimeout(30);

        $headers = $this->parseHeaders($logEntry->getRequestHeaders());
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            if (in_array($lowerName, ['host', 'content-length', 'transfer-encoding'])) {
                continue;
            }
            $curl->addHeader($name, $value);
        }

        $startTime = microtime(true);

        try {
            switch ($method) {
                case 'POST':
                    $curl->post($endpoint, $requestBody ?? '');
                    break;
                case 'PUT':
                    $curl->addHeader('X-HTTP-Method-Override', 'PUT');
                    $curl->post($endpoint, $requestBody ?? '');
                    break;
                case 'DELETE':
                    $curl->addHeader('X-HTTP-Method-Override', 'DELETE');
                    $curl->post($endpoint, $requestBody ?? '');
                    break;
                case 'PATCH':
                    $curl->addHeader('X-HTTP-Method-Override', 'PATCH');
                    $curl->post($endpoint, $requestBody ?? '');
                    break;
                default:
                    $curl->get($endpoint);
                    break;
            }

            $endTime = microtime(true);
            $replayDuration = ($endTime - $startTime) * 1000;

            return $result->setData([
                'error' => false,
                'original_status' => $logEntry->getResponseCode(),
                'original_duration' => $logEntry->getDuration(),
                'replay_status' => $curl->getStatus(),
                'replay_duration' => round($replayDuration, 2),
                'replay_body' => $curl->getBody(),
            ]);
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $replayDuration = ($endTime - $startTime) * 1000;

            return $result->setData([
                'error' => true,
                'message' => 'Request failed: ' . $e->getMessage(),
                'original_status' => $logEntry->getResponseCode(),
                'original_duration' => $logEntry->getDuration(),
                'replay_duration' => round($replayDuration, 2),
            ]);
        }
    }

    /**
     * Parse headers JSON into key-value array
     *
     * @param string|null $headersJson
     * @return array<string, string>
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
            $result[(string)$name] = is_array($value) ? implode(', ', $value) : (string)$value;
        }

        return $result;
    }
}
