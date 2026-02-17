<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Fetch related log entries for the same endpoint
 */
class Related extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param CollectionFactory $collectionFactory
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly CollectionFactory $collectionFactory,
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
            return $result->setData(['items' => []]);
        }

        try {
            $logEntry = $this->logEntryRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['items' => []]);
        }

        $endpoint = $logEntry->getEndpoint();
        $parsed = parse_url($endpoint);
        $path = $parsed['path'] ?? $endpoint;

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('endpoint', ['like' => $path . '%']);
        $collection->addFieldToFilter('entity_id', ['neq' => $id]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(10);

        $items = [];
        foreach ($collection as $item) {
            $responseCode = $item->getData('response_code');
            $items[] = [
                'entity_id' => $item->getData('entity_id'),
                'method' => $item->getData('method'),
                'endpoint' => $item->getData('endpoint'),
                'response_code' => $responseCode,
                'duration' => $item->getData('duration'),
                'created_at' => $item->getData('created_at'),
                'badge_class' => $this->getBadgeClass($responseCode ? (int)$responseCode : null),
            ];
        }

        return $result->setData(['items' => $items]);
    }

    /**
     * Get CSS class for status badge
     *
     * @param int|null $code
     * @return string
     */
    private function getBadgeClass(?int $code): string
    {
        if ($code === null) {
            return 'warning';
        }
        if ($code >= 200 && $code < 300) {
            return 'success';
        }
        if ($code >= 300 && $code < 400) {
            return 'info';
        }
        if ($code >= 400 && $code < 500) {
            return 'warning';
        }

        return 'error';
    }
}
