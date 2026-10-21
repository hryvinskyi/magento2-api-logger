<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The call as an HTTP Archive (HAR 1.2), which browsers' developer tools and HTTP clients open
 */
class HarExporter implements ExporterInterface
{
    private const DEFAULT_MIME = 'application/json';

    /**
     * @param EndpointUrlResolverInterface $urlResolver
     * @param StoredHeaders $storedHeaders
     * @param DateFormatterInterface $dateFormatter
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly EndpointUrlResolverInterface $urlResolver,
        private readonly StoredHeaders $storedHeaders,
        private readonly DateFormatterInterface $dateFormatter,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __('HAR (.har)');
    }

    /**
     * @inheritDoc
     */
    public function export(LogEntryInterface $entry): array
    {
        $url = $this->urlResolver->resolve($entry);
        $requestBody = (string) $entry->getRequestBody();
        $responseBody = (string) $entry->getResponseBody();
        $duration = $entry->getDuration() ?? 0.0;

        $har = ['log' => [
            'version' => '1.2',
            'creator' => ['name' => 'Hryvinskyi API Logger', 'version' => '2.0.0'],
            'entries' => [[
                'startedDateTime' => $this->dateFormatter->formatIso8601($entry->getCreatedAt()) ?: gmdate('c'),
                'time' => $duration,
                'request' => [
                    'method' => $entry->getMethod(),
                    'url' => $url,
                    'httpVersion' => 'HTTP/1.1',
                    'headers' => $this->pairs($entry->getRequestHeaders()),
                    'queryString' => $this->query($url),
                    'postData' => [
                        'mimeType' => $this->mime($entry->getRequestHeaders()),
                        'text' => $requestBody,
                    ],
                    'headersSize' => -1,
                    'bodySize' => strlen($requestBody),
                ],
                'response' => [
                    'status' => $entry->getResponseCode() ?? 0,
                    'statusText' => '',
                    'httpVersion' => 'HTTP/1.1',
                    'headers' => $this->pairs($entry->getResponseHeaders()),
                    'content' => [
                        'size' => strlen($responseBody),
                        'mimeType' => $this->mime($entry->getResponseHeaders()),
                        'text' => $responseBody,
                    ],
                    'redirectURL' => '',
                    'headersSize' => -1,
                    'bodySize' => strlen($responseBody),
                ],
                'cache' => [],
                'timings' => ['send' => 0, 'wait' => $duration, 'receive' => 0],
            ]],
        ]];

        return [
            'filename' => sprintf('rest-call-%d.har', (int) $entry->getEntityId()),
            'contentType' => 'application/json',
            'content' => (string) $this->serializer->serialize($har),
        ];
    }

    /**
     * Headers as HAR name/value pairs
     *
     * @param string|null $json
     * @return list<array{name: string, value: string}>
     */
    private function pairs(?string $json): array
    {
        $pairs = [];
        foreach ($this->storedHeaders->read($json) as $header) {
            $pairs[] = ['name' => $header['name'], 'value' => $header['value']];
        }

        return $pairs;
    }

    /**
     * The query parameters of the URL as HAR name/value pairs
     *
     * @param string $url
     * @return list<array{name: string, value: string}>
     */
    private function query(string $url): array
    {
        $query = explode('?', $url, 2)[1] ?? '';
        $pairs = [];
        foreach ($query === '' ? [] : explode('&', $query) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            $pairs[] = ['name' => urldecode($name), 'value' => urldecode($value)];
        }

        return $pairs;
    }

    /**
     * The content type the headers declare
     *
     * @param string|null $json
     * @return string
     */
    private function mime(?string $json): string
    {
        $type = $this->storedHeaders->value($json, 'content-type');

        return $type === '' ? self::DEFAULT_MIME : $type;
    }
}
