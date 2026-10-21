<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Http;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Masks the secrets of what a call carried before it is stored: headers become JSON; a body keeps its format (JSON,
 * XML, form-encoded), and a body of no format it recognises becomes the mask when it names a secret
 */
class Masker
{
    /**
     * @param SanitizerInterface $sanitizer
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly SanitizerInterface $sanitizer,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Headers as JSON, secrets masked
     *
     * @param array<string,string|list<string>> $headers
     * @return string
     */
    public function headers(array $headers): string
    {
        $masked = $this->sanitizer->sanitize($headers);

        return (string) $this->serializer->serialize(is_array($masked) ? $masked : []);
    }

    /**
     * A body with its secrets masked, in its own format
     *
     * @param string $text
     * @return string
     */
    public function text(string $text): string
    {
        return $this->sanitizer->sanitizeBody($text);
    }
}
