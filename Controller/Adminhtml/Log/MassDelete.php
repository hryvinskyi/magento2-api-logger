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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Mass delete log entries controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param LogEntryRepositoryInterface $logEntryRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly LogEntryRepositoryInterface $logEntryRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collectionSize = $collection->getSize();
            $deletedCount = 0;

            foreach ($collection as $logEntry) {
                try {
                    $this->logEntryRepository->delete($logEntry);
                    $deletedCount++;
                } catch (\Exception $e) {
                    // Continue deleting other entries
                }
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 log(s) have been deleted.', $deletedCount)
            );

            if ($deletedCount < $collectionSize) {
                $this->messageManager->addWarningMessage(
                    __('Failed to delete %1 log(s).', $collectionSize - $deletedCount)
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting logs: %1', $e->getMessage()));
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/index');
    }
}