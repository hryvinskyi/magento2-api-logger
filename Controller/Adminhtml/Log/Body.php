<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Body\BodyPager;
use Hryvinskyi\ApiLogger\Model\Body\BodyPart;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * A page of a stored body's lines, and the lines matching a search over the whole body
 */
class Body extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param BodyPager $pager
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly BodyPager $pager
    ) {
        parent::__construct($context);
    }

    /**
     * Answer with the page asked for
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $part = BodyPart::tryFrom($this->params->text('part'));
        if (!$result instanceof Json || $part === null) {
            return $result;
        }

        try {
            $entry = $this->logEntryRepository->getById($this->params->int('id'));
        } catch (NoSuchEntityException) {
            return $result->setHttpResponseCode(404)
                ->setData(['error' => (string) __('The call is no longer logged.')]);
        }

        return $result->setData($this->pager->page(
            $part->of($entry),
            $this->params->int('page', 1),
            $this->params->text('q')
        ));
    }
}
