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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Delete single log entry controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @param Context $context
     * @param LogEntryRepositoryInterface $logEntryRepository
     */
    public function __construct(
        Context $context,
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
        $id = (int)$this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid log entry ID.'));
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $this->logEntryRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Log entry deleted successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Failed to delete log entry: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}