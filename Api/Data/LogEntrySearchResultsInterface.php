<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Search results interface for log entries
 */
interface LogEntrySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get log entries list
     *
     * @return \Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface[]
     */
    public function getItems(): array;

    /**
     * Set log entries list
     *
     * @param \Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface[] $items
     * @return $this
     */
    public function setItems(array $items): LogEntrySearchResultsInterface;
}