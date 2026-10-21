<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Http;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;

/**
 * Reads headers stored as JSON: in order, one entry per header, values of a repeated header joined
 */
class StoredHeaders
{
    /**
     * Headers with whether each value is masked
     *
     * @param string|null $json
     * @return list<array{name: string, value: string, masked: bool}>
     */
    public function read(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $headers = json_decode($json, true);
        if (!is_array($headers)) {
            return [];
        }

        $result = [];
        foreach ($headers as $name => $value) {
            $text = is_array($value) ? implode(', ', array_map([$this, 'text'], $value)) : $this->text($value);
            $result[] = [
                'name' => (string) $name,
                'value' => $text,
                'masked' => str_contains($text, SanitizerInterface::MASK),
            ];
        }

        return $result;
    }

    /**
     * The value of one header, matched without case, or an empty string
     *
     * @param string|null $json
     * @param string $name
     * @return string
     */
    public function value(?string $json, string $name): string
    {
        foreach ($this->read($json) as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }

        return '';
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
