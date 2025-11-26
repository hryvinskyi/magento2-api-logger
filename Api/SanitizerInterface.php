<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Sanitizer Service Interface
 *
 * Handles sanitization of sensitive data before logging
 */
interface SanitizerInterface
{
    /**
     * Sanitize data by replacing sensitive values with hashes
     *
     * @param mixed $data
     * @param array<string> $secretFields
     * @return mixed
     */
    public function sanitize(mixed $data, array $secretFields): mixed;

    /**
     * Sanitize JSON string
     *
     * @param string $json
     * @param array<string> $secretFields
     * @return string
     */
    public function sanitizeJson(string $json, array $secretFields): string;

    /**
     * Sanitize array data
     *
     * @param array<string, mixed> $data
     * @param array<string> $secretFields
     * @return array<string, mixed>
     */
    public function sanitizeArray(array $data, array $secretFields): array;

    /**
     * Check if field name is a secret field
     *
     * @param string $fieldName
     * @param array<string> $secretFields
     * @return bool
     */
    public function isSecretField(string $fieldName, array $secretFields): bool;

    /**
     * Hash sensitive value
     *
     * @param string $value
     * @return string
     */
    public function hashValue(string $value): string;
}