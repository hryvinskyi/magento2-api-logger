<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;

/**
 * The call as raw HTTP text: the request, a blank line, the response
 */
class RawHttpExporter implements ExporterInterface
{
    private const EOL = "\r\n";

    /**
     * @param EndpointUrlResolverInterface $urlResolver
     * @param StoredHeaders $storedHeaders
     */
    public function __construct(
        private readonly EndpointUrlResolverInterface $urlResolver,
        private readonly StoredHeaders $storedHeaders
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __('Raw HTTP (.txt)');
    }

    /**
     * @inheritDoc
     */
    public function export(LogEntryInterface $entry): array
    {
        $url = $this->urlResolver->resolve($entry);
        $target = preg_replace('#^[a-z][a-z0-9+.-]*://[^/]*#i', '', $url) ?: '/';
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://([^/]*).*$#is', '$1', $url);

        $text = $entry->getMethod() . ' ' . $target . ' HTTP/1.1' . self::EOL;
        if ($this->storedHeaders->value($entry->getRequestHeaders(), 'host') === '' && $host !== $url) {
            $text .= 'Host: ' . $host . self::EOL;
        }

        $text .= $this->headers($entry->getRequestHeaders()) . self::EOL . (string) $entry->getRequestBody()
            . self::EOL . self::EOL
            . 'HTTP/1.1 ' . ($entry->getResponseCode() ?? '000') . self::EOL
            . $this->headers($entry->getResponseHeaders()) . self::EOL . (string) $entry->getResponseBody();

        return [
            'filename' => sprintf('rest-call-%d.txt', (int) $entry->getEntityId()),
            'contentType' => 'text/plain',
            'content' => $text,
        ];
    }

    /**
     * Header lines
     *
     * @param string|null $json
     * @return string
     */
    private function headers(?string $json): string
    {
        $lines = '';
        foreach ($this->storedHeaders->read($json) as $header) {
            $lines .= $header['name'] . ': ' . $header['value'] . self::EOL;
        }

        return $lines;
    }
}
