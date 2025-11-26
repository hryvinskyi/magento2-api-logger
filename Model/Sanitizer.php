<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\SanitizerInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Sanitizer Service
 *
 * Handles sanitization of sensitive data before logging
 */
class Sanitizer implements SanitizerInterface
{
    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sanitize(mixed $data, array $secretFields): mixed
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data, $secretFields);
        }

        if (is_string($data) && $this->isJson($data)) {
            return $this->sanitizeJson($data, $secretFields);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function sanitizeJson(string $json, array $secretFields): string
    {
        try {
            $data = $this->serializer->unserialize($json);
            $sanitized = $this->sanitizeArray($data, $secretFields);
            return $this->serializer->serialize($sanitized);
        } catch (\Exception $e) {
            return $json;
        }
    }

    /**
     * @inheritDoc
     */
    public function sanitizeArray(array $data, array $secretFields): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if ($this->isSecretField((string)$key, $secretFields)) {
                $sanitized[$key] = $this->hashValue((string)$value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $secretFields);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * @inheritDoc
     */
    public function isSecretField(string $fieldName, array $secretFields): bool
    {
        $fieldNameLower = strtolower($fieldName);

        foreach ($secretFields as $secretField) {
            $secretFieldLower = strtolower(trim($secretField));

            // Exact match or contains pattern
            if ($fieldNameLower === $secretFieldLower || str_contains($fieldNameLower, $secretFieldLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function hashValue(string $value): string
    {
        if (empty($value)) {
            return '[EMPTY]';
        }

        // Show first 4 and last 4 characters for debugging, hash the middle
        $length = strlen($value);

        if ($length <= 8) {
            return 'SHA256:' . hash('sha256', $value);
        }

        $prefix = substr($value, 0, 2);
        $suffix = substr($value, -2);
        $hash = substr(hash('sha256', $value), 0, 16);

        return sprintf('%s***%s***%s', $prefix, $hash, $suffix);
    }

    /**
     * Check if string is valid JSON
     *
     * @param string $string
     * @return bool
     */
    private function isJson(string $string): bool
    {
        if (empty($string) || !in_array($string[0], ['{', '['], true)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}