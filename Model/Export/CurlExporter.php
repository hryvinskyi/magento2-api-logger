<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Export;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;

/**
 * The request as a cURL command to run again from a shell
 *
 * Headers curl sets itself are left out. A masked secret becomes $API_SECRET, so the command reads it from the
 * shell instead of carrying a mask that would be sent as is.
 */
class CurlExporter implements ExporterInterface
{
    private const SECRET_VARIABLE = '$API_SECRET';
    private const SKIPPED_HEADERS = ['host', 'content-length', 'connection', 'accept-encoding'];

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
        return (string) __('cURL command');
    }

    /**
     * @inheritDoc
     */
    public function export(LogEntryInterface $entry): array
    {
        $parts = ['curl', '-X', $entry->getMethod(), $this->quote($this->urlResolver->resolve($entry))];
        foreach ($this->storedHeaders->read($entry->getRequestHeaders()) as $header) {
            if (in_array(strtolower($header['name']), self::SKIPPED_HEADERS, true)) {
                continue;
            }

            $parts[] = '-H';
            $parts[] = $this->quote($header['name'] . ': ' . $header['value']);
        }

        $body = (string) $entry->getRequestBody();
        if ($body !== '') {
            $parts[] = '--data-raw';
            $parts[] = $this->quote($body);
        }

        return [
            'filename' => sprintf('rest-call-%d.sh', (int) $entry->getEntityId()),
            'contentType' => 'text/plain',
            'content' => implode(' ', $parts) . "\n",
        ];
    }

    /**
     * A shell argument; masked secrets are read from $API_SECRET when the command runs
     *
     * @param string $value
     * @return string
     */
    private function quote(string $value): string
    {
        $pieces = explode(SanitizerInterface::MASK, $value);
        $quoted = array_map(
            static fn (string $piece): string => "'" . str_replace("'", "'\\''", $piece) . "'",
            $pieces
        );

        return implode('"' . self::SECRET_VARIABLE . '"', $quoted);
    }
}
