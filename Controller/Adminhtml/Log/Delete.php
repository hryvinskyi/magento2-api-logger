<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\CallRemover;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Removes one logged call and its activity
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param CallRemover $callRemover
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly CallRemover $callRemover,
        private readonly AuthorizationInterface $authorization
    ) {
        parent::__construct($context);
    }

    /**
     * Remove the call and go back to the grid
     *
     * The call's activity goes with it, so an admin who may not delete activities removes nothing.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->authorization->isAllowed(CallRemover::ACTIVITY_DELETE_RESOURCE)) {
            $this->messageManager->addErrorMessage(
                __('The call was not removed: removing it also removes its activity, which your role may not delete.')
            );

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $entry = $this->logEntryRepository->getById($this->params->int('id'));
            $this->callRemover->remove([$entry]);
            $this->messageManager->addSuccessMessage(__('The call was removed from the log.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The call is no longer logged.'));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
