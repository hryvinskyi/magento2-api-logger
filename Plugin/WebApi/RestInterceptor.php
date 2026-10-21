<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Plugin\WebApi;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ApiLogger\Api\Rest\ExtractorInterface;
use Hryvinskyi\ApiLogger\Model\Recording\EndpointPolicy;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallCapture;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Webapi\Rest\Response as RestResponse;
use Magento\Webapi\Controller\Rest;
use Psr\Log\LoggerInterface;

/**
 * Captures a REST call when it is dispatched and records it with its response
 *
 * The call's state lives in a request-scoped capture, not in this plugin. Every dispatch pushes one item to the
 * capture and takes back that item, so a dispatch inside another one is recorded with its own response. Logging never
 * breaks the call: a failure to capture or record is logged and the call's result passes through unchanged.
 *
 * @phpstan-import-type CapturedCall from RestCallCapture
 */
class RestInterceptor
{
    /**
     * @param EndpointPolicy $endpointPolicy
     * @param ExtractorInterface $extractor
     * @param RestCallCapture $capture
     * @param RestCallRecorder $recorder
     * @param MillisecondClockInterface $clock
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EndpointPolicy $endpointPolicy,
        private readonly ExtractorInterface $extractor,
        private readonly RestCallCapture $capture,
        private readonly RestCallRecorder $recorder,
        private readonly MillisecondClockInterface $clock,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Capture the call when it goes to a logged endpoint
     *
     * @param Rest $subject
     * @param RequestInterface $request
     * @return void
     */
    public function beforeDispatch(Rest $subject, RequestInterface $request): void
    {
        $this->capture->push($this->loggedCall($request));
    }

    /**
     * Record the captured call with its response
     *
     * @param Rest $subject
     * @param ResponseInterface $result
     * @return ResponseInterface
     */
    public function afterDispatch(Rest $subject, ResponseInterface $result): ResponseInterface
    {
        $call = $this->capture->pop();
        if ($call === null || !$result instanceof RestResponse) {
            return $result;
        }

        try {
            $this->recorder->record($call, [
                'code' => $this->extractor->extractResponseCode($result),
                'headers' => $this->extractor->extractResponseHeaders($result),
                'body' => $this->extractor->extractResponseBody($result),
                'exception' => $this->extractor->isException($result),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('The REST call could not be logged.', ['exception' => $exception]);
        }

        return $result;
    }

    /**
     * The call as captured at dispatch, or null when it is not logged or could not be read
     *
     * @param RequestInterface $request
     * @return CapturedCall|null
     */
    private function loggedCall(RequestInterface $request): ?array
    {
        try {
            if (!$request instanceof HttpRequest) {
                return null;
            }

            $endpoint = $this->extractor->extractEndpoint($request);
            $method = $this->extractor->extractMethod($request);
            $pattern = $this->endpointPolicy->matchingPattern($endpoint, $method);
            if ($pattern === null) {
                return null;
            }

            return [
                'endpoint' => $endpoint,
                'method' => $method,
                'pattern' => $pattern,
                'headers' => $this->extractor->extractRequestHeaders($request),
                'body' => $this->extractor->extractRequestBody($request),
                'startedAtMs' => $this->clock->nowMs(),
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('The REST call could not be captured.', ['exception' => $exception]);

            return null;
        }
    }
}
