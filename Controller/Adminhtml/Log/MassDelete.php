<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Model\CallRemover;
use Hryvinskyi\ApiLogger\Model\ResourceModel\LogEntry\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Removes the selected logged calls and their activities
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CallRemover $callRemover
     * @param LoggerInterface $logger
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CallRemover $callRemover,
        private readonly LoggerInterface $logger,
        private readonly AuthorizationInterface $authorization
    ) {
        parent::__construct($context);
    }

    /**
     * Remove the selected calls and their activities
     *
     * An admin who may not delete activities removes nothing.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->authorization->isAllowed(CallRemover::ACTIVITY_DELETE_RESOURCE)) {
            $this->messageManager->addErrorMessage(
                __('No call was removed: removing calls also removes their activities, which your role may not delete.')
            );

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $removed = $this->callRemover->remove($collection->getItems());
            $this->messageManager->addSuccessMessage(__('A total of %1 call(s) were removed from the log.', $removed));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(__('The calls could not be removed: %1', $exception->getMessage()));
        } catch (\Exception $exception) {
            $this->logger->error($exception);
            $this->messageManager->addErrorMessage(
                __('The calls could not be removed. The error is in the system log.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
