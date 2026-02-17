<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Performance statistics for an endpoint
 */
class Stats extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param ResourceConnection $resourceConnection
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $id = (int)$this->getRequest()->getParam('id');

        if (!$id) {
            return $result->setData(['request_count' => 0]);
        }

        try {
            $logEntry = $this->logEntryRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['request_count' => 0]);
        }

        $endpoint = $logEntry->getEndpoint();
        $parsed = parse_url($endpoint);
        $path = $parsed['path'] ?? $endpoint;
        $currentDuration = $logEntry->getDuration();

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('hryvinskyi_api_log_entry');

        $select = $connection->select()
            ->from($tableName, [
                'avg_duration' => new \Zend_Db_Expr('AVG(duration)'),
                'min_duration' => new \Zend_Db_Expr('MIN(duration)'),
                'max_duration' => new \Zend_Db_Expr('MAX(duration)'),
                'request_count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->where('endpoint LIKE ?', $path . '%')
            ->where('duration IS NOT NULL');

        $stats = $connection->fetchRow($select);

        $percentileRank = null;
        if ($currentDuration !== null && (int)($stats['request_count'] ?? 0) > 0) {
            $countFaster = $connection->select()
                ->from($tableName, [new \Zend_Db_Expr('COUNT(*)')])
                ->where('endpoint LIKE ?', $path . '%')
                ->where('duration IS NOT NULL')
                ->where('duration > ?', $currentDuration);

            $fasterCount = (int)$connection->fetchOne($countFaster);
            $totalCount = (int)$stats['request_count'];
            $percentileRank = ($fasterCount / $totalCount) * 100;
        }

        return $result->setData([
            'avg_duration' => (float)($stats['avg_duration'] ?? 0),
            'min_duration' => (float)($stats['min_duration'] ?? 0),
            'max_duration' => (float)($stats['max_duration'] ?? 0),
            'request_count' => (int)($stats['request_count'] ?? 0),
            'percentile_rank' => $percentileRank,
        ]);
    }
}
