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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Search and compare log entries side-by-side
 */
class Compare extends Action implements HttpGetActionInterface, HttpPostActionInterface
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
        $otherId = (int)$this->getRequest()->getParam('other_id');
        $query = (string)$this->getRequest()->getParam('q', '');

        if ($otherId && $id) {
            return $this->compareMode($result, $id, $otherId);
        }

        return $this->searchMode($result, $id, $query);
    }

    /**
     * Search mode: return matching entries
     *
     * @param \Magento\Framework\Controller\Result\Json $result
     * @param int $currentId
     * @param string $query
     * @return ResultInterface
     */
    private function searchMode($result, int $currentId, string $query): ResultInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['neq' => $currentId]);

        if ($query !== '') {
            if (is_numeric($query)) {
                $collection->addFieldToFilter(
                    ['entity_id', 'endpoint', 'method'],
                    [
                        ['eq' => (int)$query],
                        ['like' => '%' . $query . '%'],
                        ['like' => '%' . $query . '%'],
                    ]
                );
            } else {
                $collection->addFieldToFilter(
                    ['endpoint', 'method'],
                    [
                        ['like' => '%' . $query . '%'],
                        ['like' => '%' . $query . '%'],
                    ]
                );
            }
        }

        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(20);

        $items = [];
        foreach ($collection as $item) {
            $responseCode = $item->getData('response_code');
            $items[] = [
                'entity_id' => $item->getData('entity_id'),
                'method' => $item->getData('method'),
                'endpoint' => $item->getData('endpoint'),
                'response_code' => $responseCode,
                'created_at' => $item->getData('created_at'),
                'badge_class' => $this->getBadgeClass($responseCode ? (int)$responseCode : null),
            ];
        }

        return $result->setData(['items' => $items]);
    }

    /**
     * Compare mode: return both entries' full data
     *
     * @param \Magento\Framework\Controller\Result\Json $result
     * @param int $currentId
     * @param int $otherId
     * @return ResultInterface
     */
    private function compareMode($result, int $currentId, int $otherId): ResultInterface
    {
        try {
            $current = $this->logEntryRepository->getById($currentId);
            $other = $this->logEntryRepository->getById($otherId);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['error' => true, 'message' => 'Entry not found.']);
        }

        return $result->setData([
            'current' => $this->entryToArray($current),
            'other' => $this->entryToArray($other),
        ]);
    }

    /**
     * Convert log entry to array for JSON response
     *
     * @param \Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface $entry
     * @return array<string, mixed>
     */
    private function entryToArray($entry): array
    {
        return [
            'entity_id' => $entry->getEntityId(),
            'method' => $entry->getMethod(),
            'endpoint' => $entry->getEndpoint(),
            'response_code' => $entry->getResponseCode(),
            'duration' => $entry->getDuration(),
            'request_headers' => $entry->getRequestHeaders(),
            'request_body' => $entry->getRequestBody(),
            'response_headers' => $entry->getResponseHeaders(),
            'response_body' => $entry->getResponseBody(),
            'created_at' => $entry->getCreatedAt(),
        ];
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
