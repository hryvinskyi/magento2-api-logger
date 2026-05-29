<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Ui\Component\Listing\Column;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Prepares a headers JSON field (request_headers / response_headers) for the grid.
 *
 * The raw JSON payload is decoded into an ordered list of {key, value} rows that the
 * "headers" JS column component renders as a structured, expandable ("read more") cell.
 * Values are emitted as plain data and escaped by the Knockout "text" binding on the
 * frontend, so no HTML escaping is performed here.
 */
class Headers extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Json $serializer
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Json $serializer,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Convert the headers field of every row into a structured list of {key, value} rows.
     *
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = (string)$this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $raw = $item[$fieldName] ?? null;
            $item[$fieldName] = $this->toRows(is_string($raw) ? $raw : '');
        }

        return $dataSource;
    }

    /**
     * Decode the payload into an ordered list of header rows.
     *
     * Non-JSON payloads are returned as a single keyless row so they are still displayed.
     *
     * @param string $raw Raw headers payload as stored in the database (usually JSON).
     * @return array<int, array{key: string, value: string}>
     */
    private function toRows(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = $this->decode($raw);
        if ($decoded === null) {
            return [['key' => '', 'value' => $raw]];
        }

        $rows = [];
        foreach ($decoded as $key => $value) {
            $rows[] = ['key' => (string)$key, 'value' => $this->stringifyValue($value)];
        }

        return $rows;
    }

    /**
     * Decode the payload into an associative array, or null when it is not JSON object data.
     *
     * @param string $raw
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        try {
            $decoded = $this->serializer->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Flatten a single header value (which may be an array) into a string.
     *
     * @param mixed $value
     * @return string
     */
    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'stringifyValue'], $value));
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string)$value;
    }
}
