<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;

/**
 * Renders Magento's REST error body into the text a person reads
 *
 * The body carries the message with its placeholders and the values apart:
 * {"message": "... %resources ...", "parameters": {"resources": "..."}} names them, "parameters": ["a", "b"] numbers
 * them from %1. Placeholders are replaced the way Magento renders a phrase: in one pass, the longest placeholder
 * first, so "%10" is not read as "%1" followed by "0" and "%resources" is not read as "%resource" followed by "s".
 * A value that is not scalar leaves its placeholder in the text, and so does a secret: a parameter named as a secret
 * field, and the value of a field the parameters name as a secret ("fieldName": "password" keeps "%value" and
 * "%fieldValue" in place).
 */
class ErrorMessage
{
    private const PLACEHOLDER = '%';
    private const FIELD_NAME = 'fieldName';
    private const FIELD_VALUES = ['value', 'fieldValue'];

    /**
     * @param SanitizerInterface $sanitizer
     */
    public function __construct(
        private readonly SanitizerInterface $sanitizer
    ) {
    }

    /**
     * The message of the error body with its parameters in place, or null when the body holds no message
     *
     * @param string|null $body
     * @return string|null
     */
    public function fromBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $error = json_decode($body, true);
        $message = is_array($error) ? ($error['message'] ?? null) : null;
        if (!is_string($message) || $message === '') {
            return null;
        }

        $parameters = $error['parameters'] ?? null;

        return is_array($parameters) ? strtr($message, $this->replacements($parameters)) : $message;
    }

    /**
     * Placeholder → text of every scalar parameter that is no secret
     *
     * A named parameter fills "%name", a listed one "%1".."%n".
     *
     * @param array<mixed> $parameters
     * @return array<string, string>
     */
    private function replacements(array $parameters): array
    {
        $fieldName = $parameters[self::FIELD_NAME] ?? null;
        $hidden = is_string($fieldName) && $this->sanitizer->isSecretField($fieldName) ? self::FIELD_VALUES : [];

        $replacements = [];
        foreach ($parameters as $key => $value) {
            if (!is_scalar($value)
                || (is_string($key) && (in_array($key, $hidden, true) || $this->sanitizer->isSecretField($key)))
            ) {
                continue;
            }
            $replacements[self::PLACEHOLDER . (is_int($key) ? (string) ($key + 1) : $key)] = (string) $value;
        }

        return $replacements;
    }
}
