<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for HTTP response codes
 *
 * Provides list of common HTTP response codes for filtering
 */
class ResponseCodes implements OptionSourceInterface
{
    /**
     * Get options as array with value and label
     *
     * @return array<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            // Success codes (2xx)
            ['value' => '200', 'label' => '200 - OK'],
            ['value' => '201', 'label' => '201 - Created'],
            ['value' => '202', 'label' => '202 - Accepted'],
            ['value' => '204', 'label' => '204 - No Content'],

            // Redirection codes (3xx)
            ['value' => '301', 'label' => '301 - Moved Permanently'],
            ['value' => '302', 'label' => '302 - Found'],
            ['value' => '304', 'label' => '304 - Not Modified'],

            // Client error codes (4xx)
            ['value' => '400', 'label' => '400 - Bad Request'],
            ['value' => '401', 'label' => '401 - Unauthorized'],
            ['value' => '403', 'label' => '403 - Forbidden'],
            ['value' => '404', 'label' => '404 - Not Found'],
            ['value' => '405', 'label' => '405 - Method Not Allowed'],
            ['value' => '409', 'label' => '409 - Conflict'],
            ['value' => '422', 'label' => '422 - Unprocessable Entity'],
            ['value' => '429', 'label' => '429 - Too Many Requests'],

            // Server error codes (5xx)
            ['value' => '500', 'label' => '500 - Internal Server Error'],
            ['value' => '502', 'label' => '502 - Bad Gateway'],
            ['value' => '503', 'label' => '503 - Service Unavailable'],
            ['value' => '504', 'label' => '504 - Gateway Timeout'],
        ];
    }
}