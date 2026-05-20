<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * @inheritDoc
 */
class DateFormatter implements DateFormatterInterface
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritDoc
     */
    public function formatDateTime(?string $utcDate): string
    {
        $date = $this->createUtcDate($utcDate);
        if ($date === null) {
            return '';
        }

        // Use the locale's medium date-time pattern, matching the log grid Created At column.
        return $this->timezone->formatDateTime(
            $date,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::MEDIUM,
            null,
            null,
            $this->timezone->getDateTimeFormat(\IntlDateFormatter::MEDIUM)
        );
    }

    /**
     * @inheritDoc
     */
    public function formatIso8601(?string $utcDate): string
    {
        $date = $this->createUtcDate($utcDate);
        if ($date === null) {
            return '';
        }

        return $this->timezone->date($date)->format(\DateTimeInterface::ATOM);
    }

    /**
     * Build a DateTime in UTC from a stored timestamp string
     *
     * @param string|null $utcDate Timestamp as stored in the database (UTC)
     * @return \DateTime|null Null when the input is empty or cannot be parsed
     */
    private function createUtcDate(?string $utcDate): ?\DateTime
    {
        if ($utcDate === null) {
            return null;
        }

        $trimmed = trim($utcDate);
        if ($trimmed === '' || str_starts_with($trimmed, '0000-00-00')) {
            return null;
        }

        try {
            return new \DateTime($trimmed, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return null;
        }
    }
}
