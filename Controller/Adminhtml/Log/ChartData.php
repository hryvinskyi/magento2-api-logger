<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Aggregate chart data for the dashboard
 */
class ChartData extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::dashboard';

    /**
     * @param Context $context
     * @param ResourceConnection $resourceConnection
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
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
        $days = (int)$this->getRequest()->getParam('days', 7);
        $days = in_array($days, [7, 14, 30]) ? $days : 7;

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('hryvinskyi_api_log_entry');
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

        $volumeSelect = $connection->select()
            ->from($tableName, [
                'date' => new \Zend_Db_Expr('DATE(created_at)'),
                'count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->where('created_at >= ?', $dateFrom)
            ->group(new \Zend_Db_Expr('DATE(created_at)'))
            ->order('date ASC');
        $volume = $connection->fetchAll($volumeSelect);

        $slowestSelect = $connection->select()
            ->from($tableName, [
                'endpoint' => 'endpoint',
                'avg_duration' => new \Zend_Db_Expr('AVG(duration)'),
                'request_count' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->where('created_at >= ?', $dateFrom)
            ->where('duration IS NOT NULL')
            ->group('endpoint')
            ->order('avg_duration DESC')
            ->limit(10);
        $slowest = $connection->fetchAll($slowestSelect);

        $summarySelect = $connection->select()
            ->from($tableName, [
                'total_requests' => new \Zend_Db_Expr('COUNT(*)'),
                'avg_duration' => new \Zend_Db_Expr('AVG(duration)'),
                'error_count' => new \Zend_Db_Expr('SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END)'),
                'exception_count' => new \Zend_Db_Expr('SUM(CASE WHEN is_exception = 1 THEN 1 ELSE 0 END)'),
            ])
            ->where('created_at >= ?', $dateFrom);
        $summary = $connection->fetchRow($summarySelect);

        $totalRequests = (int)($summary['total_requests'] ?? 0);
        $errorCount = (int)($summary['error_count'] ?? 0);
        $errorRate = $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 1) : 0;

        return $result->setData([
            'volume' => $volume,
            'slowest' => $slowest,
            'summary' => [
                'total_requests' => $totalRequests,
                'avg_duration' => round((float)($summary['avg_duration'] ?? 0), 1),
                'error_rate' => $errorRate,
                'error_count' => $errorCount,
                'exception_count' => (int)($summary['exception_count'] ?? 0),
            ],
        ]);
    }
}
