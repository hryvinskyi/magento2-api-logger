<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

/**
 * Converts stored UTC log timestamps into the configured Magento timezone for admin display
 */
interface DateFormatterInterface
{
    /**
     * Format a stored UTC timestamp as a localized, human-readable datetime
     *
     * @param string|null $utcDate Timestamp as stored in the database (UTC)
     * @return string Localized datetime, or an empty string when the input is empty or invalid
     */
    public function formatDateTime(?string $utcDate): string;

    /**
     * Format a stored UTC timestamp as an ISO 8601 string in the configured timezone
     *
     * @param string|null $utcDate Timestamp as stored in the database (UTC)
     * @return string ISO 8601 datetime, or an empty string when the input is empty or invalid
     */
    public function formatIso8601(?string $utcDate): string;
}
