<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Export\AttachmentResult;
use Hryvinskyi\ApiLogger\Model\Export\ExporterPool;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Sends a logged call as a file in the format asked for, from memory
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param ExporterPool $exporterPool
     * @param AttachmentResult $attachmentResult
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly ExporterPool $exporterPool,
        private readonly AttachmentResult $attachmentResult
    ) {
        parent::__construct($context);
    }

    /**
     * Send the file, or back to the grid when the call or the format is unknown
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $exporter = $this->exporterPool->get($this->params->text('format'));
        if ($exporter === null) {
            $this->messageManager->addErrorMessage(__('This export format is not available.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $entry = $this->logEntryRepository->getById($this->params->int('id'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The call is no longer logged.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $file = $exporter->export($entry);

        return $this->attachmentResult->create($file['filename'], $file['contentType'], $file['content']);
    }
}
