<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntrySearchResultsInterface;
use Magento\Framework\Api\Search\SearchResult;

class LogEntrySearchResults extends SearchResult implements LogEntrySearchResultsInterface
{
    /**
     * @inheritdoc
     */
    #[\Override]
    public function getItems(): array
    {
        return array_values(
            array_filter(parent::getItems(), static fn ($item): bool => $item instanceof LogEntryInterface)
        );
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function setItems(?array $items = null): LogEntrySearchResultsInterface
    {
        $this->setData(self::ITEMS, $items);

        return $this;
    }
}
