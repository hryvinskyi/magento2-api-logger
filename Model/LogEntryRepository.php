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
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry as LogEntryResource;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Log Entry Repository
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
            $this->resource->save($logEntry);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the log entry: %1', $exception->getMessage()),
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
        $logEntry = $this->logEntryFactory->create();
        $this->resource->load($logEntry, $entityId);

        if (!$logEntry->getEntityId()) {
            throw new NoSuchEntityException(__('Log entry with id "%1" does not exist.', $entityId));
        }

        return $logEntry;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LogEntrySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(LogEntryInterface $logEntry): bool
    {
        try {
            $this->resource->delete($logEntry);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the log entry: %1', $exception->getMessage()),
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
}