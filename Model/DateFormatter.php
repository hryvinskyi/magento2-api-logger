<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\DateFormatterInterface;
use Magento\Framework\Intl\DateTimeFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * @inheritDoc
 */
class DateFormatter implements DateFormatterInterface
{
    private const STORED_TIMEZONE = 'UTC';

    /**
     * @param TimezoneInterface $timezone
     * @param DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly DateTimeFactory $dateTimeFactory
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
        return $this->timezone->formatDateTime($date, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
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
     * @param string|null $utcDate Timestamp as stored in the database: "Y-m-d H:i:s" in UTC
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
            return $this->dateTimeFactory->create($trimmed . ' ' . self::STORED_TIMEZONE);
        } catch (\Exception) {
            return null;
        }
    }
}
