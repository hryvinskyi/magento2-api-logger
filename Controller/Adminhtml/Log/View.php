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
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Log detail view controller
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param LogEntryRepositoryInterface $logEntryRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
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

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Invalid log entry ID.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $logEntry = $this->logEntryRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Log entry not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Hryvinskyi_ApiLogger::logs');
        $resultPage->getConfig()->getTitle()->prepend(
            __('Log Entry #%1 - %2 %3', $id, $logEntry->getMethod(), $logEntry->getEndpoint())
        );

        return $resultPage;
    }
}