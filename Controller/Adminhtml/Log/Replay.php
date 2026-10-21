<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Body\BodyPager;
use Hryvinskyi\ApiLogger\Model\Http\RequestParams;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Sends a logged call again and answers with both outcomes side by side
 *
 * Whatever the replayed server answered is made valid UTF-8 before it is encoded, and every failure, encoding
 * included, is answered as an error the dialog shows rather than as a broken response.
 */
class Replay extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ApiLogger::replay';
    private const BODY_PREVIEW_BYTES = 65536;
    private const ENCODING = 'UTF-8';

    /**
     * @param Context $context
     * @param RequestParams $params
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param ReplayService $replayService
     * @param BodyPager $pager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly RequestParams $params,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly ReplayService $replayService,
        private readonly BodyPager $pager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Replay the call
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
            $replay = $this->replayService->replay($entry);

            return $result->setData([
                'original' => ['status' => $entry->getResponseCode(), 'durationMs' => $entry->getDuration()],
                'replay' => [
                    'status' => $replay['status'],
                    'durationMs' => $replay['durationMs'],
                    'url' => mb_scrub($replay['url'], self::ENCODING),
                    'body' => mb_strcut(
                        mb_scrub($this->pager->readable($replay['body']), self::ENCODING),
                        0,
                        self::BODY_PREVIEW_BYTES,
                        self::ENCODING
                    ),
                ],
            ]);
        } catch (NoSuchEntityException) {
            return $result->setData(['error' => (string) __('The call is no longer logged.')]);
        } catch (LocalizedException $exception) {
            return $result->setData(['error' => (string) __('The replay failed: %1', $exception->getMessage())]);
        } catch (\Exception $exception) {
            $this->logger->error($exception);

            return $result->setData(['error' => (string) __('The replay failed. The error is in the system log.')]);
        }
    }
}
