<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * A logged call is shown on its activity page; old links and bookmarks land there
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * Admin paths start with the route id, not the URL front name: the secret key in a URL is made from the route id
     */
    private const ACTIVITY_VIEW = 'hryvinskyi_activitylog/activity/view';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Send the admin to the call's activity, or back to the grid
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $activityId = $this->logEntryRepository->getById($this->params->int('id'))->getActivityId();
        } catch (NoSuchEntityException) {
            $activityId = null;
        }

        if ($activityId === null) {
            $this->messageManager->addErrorMessage(__('The call is no longer logged.'));

            return $redirect->setPath('*/*/index');
        }

        return $redirect->setPath(self::ACTIVITY_VIEW, ['id' => $activityId]);
    }
}
