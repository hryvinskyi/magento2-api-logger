<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Rest;

use Hryvinskyi\ApiLogger\Api\Rest\ExtractorInterface;
use Magento\Framework\App\HttpRequestInterface as RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\ErrorProcessor;
use Magento\Framework\Webapi\Exception;

/**
 * REST Extractor Service
 *
 * Extracts data from Magento REST API request and response objects
 */
class Extractor implements ExtractorInterface
{
    public function __construct(
        private readonly State $appState,
        private readonly ErrorProcessor $errorProcessor,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function extractEndpoint(RequestInterface $request): string
    {
        return $request->getPathInfo();
    }

    /**
     * @inheritDoc
     */
    public function extractMethod(RequestInterface $request): string
    {
        return $request->getMethod();
    }

    /**
     * @inheritDoc
     */
    public function extractRequestHeaders(RequestInterface $request): array
    {
        $headers = [];

        if (!method_exists($request, 'getHeaders')) {
            return $headers;
        }

        $headersObject = $request->getHeaders();

        if (!$headersObject) {
            return $headers;
        }

        // Try to convert headers object to array
        if (method_exists($headersObject, 'toArray')) {
            return $headersObject->toArray();
        }

        // Fallback to string parsing if toArray is not available
        if (method_exists($headersObject, 'toString')) {
            $headersString = $headersObject->toString();
            $lines = explode("\r\n", $headersString);

            foreach ($lines as $line) {
                if (strpos($line, ':') === false) {
                    continue;
                }

                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }

            return $headers;
        }

        return ['_raw' => 'Unable to extract headers'];
    }

    /**
     * @inheritDoc
     */
    public function extractRequestBody(RequestInterface $request): ?string
    {
        $content = $request->getContent();

        return $content !== false ? $content : null;
    }

    /**
     * @inheritDoc
     */
    public function extractResponseCode(ResponseInterface $response): int
    {
        return (int)$response->getHttpResponseCode();
    }

    /**
     * @inheritDoc
     */
    public function extractResponseHeaders(ResponseInterface $response): array
    {
        $headers = [];

        if (!method_exists($response, 'getHeaders')) {
            return $headers;
        }

        $headersObject = $response->getHeaders();

        if (!$headersObject) {
            return $headers;
        }

        // Try to convert headers object to array
        if (method_exists($headersObject, 'toArray')) {
            return $headersObject->toArray();
        }

        return $headers;
    }

    /**
     * @inheritDoc
     */
    public function extractResponseBody(ResponseInterface $response): ?string
    {
        $body = $response->getBody();

        return $body !== null && $body !== false ? (string)$body : null;
    }

    /**
     * @inheritDoc
     */
    public function extractExceptionBody(ResponseInterface $response): ?string
    {
        foreach ($response->getException() as $exception) {
            $maskedException = $this->errorProcessor->maskException($exception);
            $messageData = ['message' => $maskedException->getMessage()];

            if ($maskedException->getErrors()) {
                $messageData['errors'] = [];

                foreach ($maskedException->getErrors() as $errorMessage) {
                    $errorData['message'] = $errorMessage->getRawMessage();
                    $errorData['parameters'] = $errorMessage->getParameters();
                    $messageData['errors'][] = $errorData;
                }
            }

            if ($maskedException->getCode()) {
                $messageData['code'] = $maskedException->getCode();
            }

            if ($maskedException->getDetails()) {
                $messageData['parameters'] = $maskedException->getDetails();
            }

            if ($this->appState->getMode() == State::MODE_DEVELOPER) {
                $messageData['trace'] = $exception instanceof Exception
                    ? $exception->getStackTrace()
                    : $exception->getTraceAsString();
            }

            return $this->serializer->serialize($messageData);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function isException(ResponseInterface $response): bool
    {
        if (!method_exists($response, 'isException')) {
            return false;
        }

        return (bool)$response->isException();
    }
}