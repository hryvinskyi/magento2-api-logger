<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Api;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntrySearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;

/**
 * Log Entry Repository Interface
 *
 * Handles CRUD operations for API log entries
 */
interface LogEntryRepositoryInterface
{
    /**
     * Save log entry
     *
     * @param LogEntryInterface $logEntry
     * @return LogEntryInterface
     * @throws CouldNotSaveException
     */
    public function save(LogEntryInterface $logEntry): LogEntryInterface;

    /**
     * Get log entry by ID
     *
     * @param int $entityId
     * @return LogEntryInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): LogEntryInterface;

    /**
     * Get list of log entries matching search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return LogEntrySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LogEntrySearchResultsInterface;

    /**
     * Delete log entry
     *
     * @param LogEntryInterface $logEntry
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(LogEntryInterface $logEntry): bool;

    /**
     * Delete log entry by ID
     *
     * @param int $entityId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}