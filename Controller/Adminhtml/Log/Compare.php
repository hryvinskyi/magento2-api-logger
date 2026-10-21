<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Compare\ComparisonView;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Lists the calls to the same endpoint a call can be compared with; with "other", answers with both calls
 */
class Compare extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param ComparisonView $comparisonView
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly ComparisonView $comparisonView
    ) {
        parent::__construct($context);
    }

    /**
     * Answer with the candidates or the pair
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if (!$result instanceof Json) {
            return $result;
        }

        try {
            $entry = $this->logEntryRepository->getById($this->params->int('id'));
            $otherId = $this->params->int('other');

            return $result->setData($otherId > 0
                ? $this->comparisonView->pair($entry, $this->logEntryRepository->getById($otherId))
                : ['candidates' => $this->comparisonView->candidates(
                    $entry,
                    $this->params->text('q')
                )]);
        } catch (NoSuchEntityException) {
            return $result->setData(['error' => (string) __('The call is no longer logged.')]);
        }
    }
}
