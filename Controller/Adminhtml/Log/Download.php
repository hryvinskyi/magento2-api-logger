<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Body\ReadableBody;
use Hryvinskyi\ApiLogger\Model\Body\BodyPart;
use Hryvinskyi\ApiLogger\Model\Export\AttachmentResult;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * A stored body as a file, whole, sent from memory
 */
class Download extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param ReadableBody $readableBody
     * @param AttachmentResult $attachmentResult
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly ReadableBody $readableBody,
        private readonly AttachmentResult $attachmentResult
    ) {
        parent::__construct($context);
    }

    /**
     * Send the body as a file, or back to the grid when it is gone
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $part = BodyPart::tryFrom($this->params->text('part')) ?? BodyPart::Response;
        $id = $this->params->int('id');
        try {
            $body = $part->of($this->logEntryRepository->getById($id));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('The call is no longer logged.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $readable = $this->readableBody->text($body);
        $extension = $readable !== (string) $body || json_decode((string) $body) !== null ? 'json' : 'txt';

        return $this->attachmentResult->create(
            sprintf('rest-call-%d-%s.%s', $id, $part->value, $extension),
            $extension === 'json' ? 'application/json' : 'text/plain',
            (string) $body
        );
    }
}
