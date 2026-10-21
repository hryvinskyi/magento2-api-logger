<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Rest;

use Hryvinskyi\ApiLogger\Api\Rest\ExtractorInterface;
use Laminas\Http\Headers;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\ErrorProcessor;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Response as RestResponse;

/**
 * Reads a REST call from Magento's request and response
 *
 * The endpoint is the path after the REST front name Magento is configured with: the request handed to the REST front
 * controller still carries it. A failed call has no body or status yet when the action returns: they are written when
 * the response is sent. The error is turned here into the body and status the client receives, the way the response
 * will render it.
 */
class Extractor implements ExtractorInterface
{
    /**
     * @param State $appState
     * @param ErrorProcessor $errorProcessor
     * @param SerializerInterface $serializer
     * @param AreaList $areaList
     */
    public function __construct(
        private readonly State $appState,
        private readonly ErrorProcessor $errorProcessor,
        private readonly SerializerInterface $serializer,
        private readonly AreaList $areaList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function extractEndpoint(HttpRequest $request): string
    {
        $query = (string) $request->getUri()->getQuery();

        return $this->withoutFrontName((string) $request->getPathInfo()) . ($query !== '' ? '?' . $query : '');
    }

    /**
     * @inheritDoc
     */
    public function extractMethod(HttpRequest $request): string
    {
        return strtoupper((string) $request->getMethod());
    }

    /**
     * @inheritDoc
     */
    public function extractRequestHeaders(HttpRequest $request): array
    {
        $headers = $request->getHeaders();

        return $headers instanceof Headers ? $this->headers($headers) : [];
    }

    /**
     * @inheritDoc
     */
    public function extractRequestBody(HttpRequest $request): ?string
    {
        $content = $request->getContent();

        return $content !== '' ? $content : null;
    }

    /**
     * @inheritDoc
     */
    public function extractResponseCode(RestResponse $response): int
    {
        $exception = $this->firstException($response);

        return $exception !== null
            ? (int) $this->errorProcessor->maskException($exception)->getHttpCode()
            : (int) $response->getHttpResponseCode();
    }

    /**
     * @inheritDoc
     */
    public function extractResponseHeaders(RestResponse $response): array
    {
        return $this->headers($response->getHeaders());
    }

    /**
     * @inheritDoc
     */
    public function extractResponseBody(RestResponse $response): ?string
    {
        $exception = $this->firstException($response);
        if ($exception !== null) {
            return $this->errorBody($exception);
        }

        $body = $response->getBody();

        return $body !== '' ? $body : null;
    }

    /**
     * @inheritDoc
     */
    public function isException(RestResponse $response): bool
    {
        return (bool) $response->isException();
    }

    /**
     * The path without its leading REST front name; unchanged when the front name is unknown or not the first segment
     *
     * @param string $path
     * @return string
     */
    private function withoutFrontName(string $path): string
    {
        $frontName = (string) $this->areaList->getFrontName(Area::AREA_WEBAPI_REST);
        if ($frontName === '') {
            return $path;
        }

        $stripped = (string) preg_replace('#^/' . preg_quote($frontName, '#') . '(?=/|$)#', '', $path, 1);

        return $stripped !== '' ? $stripped : '/';
    }

    /**
     * The first error registered with the response, or null
     *
     * @param RestResponse $response
     * @return \Exception|null
     */
    private function firstException(RestResponse $response): ?\Exception
    {
        foreach ($response->getException() as $exception) {
            if ($exception instanceof \Exception) {
                return $exception;
            }
        }

        return null;
    }

    /**
     * The error as the client receives it
     *
     * @param \Exception $exception
     * @return string
     */
    private function errorBody(\Exception $exception): string
    {
        $masked = $this->errorProcessor->maskException($exception);
        $message = ['message' => $masked->getMessage()];

        $errors = [];
        foreach ($masked->getErrors() ?? [] as $error) {
            $errors[] = ['message' => $error->getRawMessage(), 'parameters' => $error->getParameters()];
        }
        if ($errors !== []) {
            $message['errors'] = $errors;
        }

        if ($masked->getCode()) {
            $message['code'] = $masked->getCode();
        }

        if ($masked->getDetails()) {
            $message['parameters'] = $masked->getDetails();
        }

        if ($this->appState->getMode() === State::MODE_DEVELOPER) {
            $message['trace'] = $exception instanceof WebapiException
                ? $exception->getStackTrace()
                : $exception->getTraceAsString();
        }

        return (string) $this->serializer->serialize($message);
    }

    /**
     * Headers by name; a header sent more than once has a list of values
     *
     * @param Headers $headers
     * @return array<string, string|list<string>>
     */
    private function headers(Headers $headers): array
    {
        $result = [];
        foreach ($headers->toArray() as $name => $value) {
            $result[(string) $name] = is_array($value)
                ? array_values(array_map([$this, 'text'], $value))
                : $this->text($value);
        }

        return $result;
    }

    /**
     * A header value as text
     *
     * @param mixed $value
     * @return string
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
