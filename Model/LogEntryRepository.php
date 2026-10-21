<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterfaceFactory;
use Hryvinskyi\ApiLogger\Api\Data\LogEntrySearchResultsInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntrySearchResultsInterfaceFactory;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Data\LogEntry;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Stores and finds logged REST calls in their table
 */
class LogEntryRepository implements LogEntryRepositoryInterface
{
    /**
     * @param LogEntryResource $resource
     * @param LogEntryInterfaceFactory $logEntryFactory
     * @param CollectionFactory $collectionFactory
     * @param LogEntrySearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly LogEntryResource $resource,
        private readonly LogEntryInterfaceFactory $logEntryFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly LogEntrySearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(LogEntryInterface $logEntry): LogEntryInterface
    {
        try {
            $this->resource->save($this->model($logEntry));
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('The log entry could not be saved.'),
                $exception
            );
        }

        return $logEntry;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): LogEntryInterface
    {
        return $this->load($entityId, LogEntryInterface::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getByActivityId(int $activityId): LogEntryInterface
    {
        return $this->load($activityId, LogEntryInterface::ACTIVITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LogEntrySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $items = [];
        foreach ($collection->getItems() as $item) {
            if ($item instanceof LogEntryInterface) {
                $items[] = $item;
            }
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(LogEntryInterface $logEntry): bool
    {
        try {
            $this->resource->delete($this->model($logEntry));
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('The log entry could not be deleted.'),
                $exception
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * Load the entry with the value in a unique field
     *
     * @param int $value
     * @param string $field
     * @return LogEntryInterface
     * @throws NoSuchEntityException
     */
    private function load(int $value, string $field): LogEntryInterface
    {
        $logEntry = $this->model($this->logEntryFactory->create());
        $this->resource->load($logEntry, $value, $field);

        if (!$logEntry->getId()) {
            throw new NoSuchEntityException(__('Log entry with %1 "%2" does not exist.', $field, $value));
        }

        return $logEntry;
    }

    /**
     * The stored model behind an entry
     *
     * @param LogEntryInterface $logEntry
     * @return LogEntry
     * @throws \InvalidArgumentException When the entry is not one this repository stores
     */
    private function model(LogEntryInterface $logEntry): LogEntry
    {
        if (!$logEntry instanceof LogEntry) {
            throw new \InvalidArgumentException(sprintf('%s cannot be stored here.', $logEntry::class));
        }

        return $logEntry;
    }
}
