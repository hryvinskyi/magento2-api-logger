<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ActivityLog\Api\ActivityBuilderInterface;
use Hryvinskyi\ActivityLog\Api\ActivityRecorderInterface;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Records a REST call: an activity of the "rest" channel in the journal, and the call's entry linked to it
 *
 * Nothing is stored when the journal records nothing: an entry lives as long as its activity. The secrets the
 * endpoint carries are masked before anything is built from it, so the stored endpoint and the activity's action
 * never hold them. Recording never breaks the call; a failure to record is logged.
 *
 * @phpstan-import-type CapturedCall from RestCallCapture
 */
class RestCallRecorder
{
    public const CHANNEL = 'rest';
    private const ACTION_MAX_LENGTH = 255;
    private const MESSAGE_MAX_LENGTH = 500;

    /**
     * @param EndpointPolicy $endpointPolicy
     * @param LogEntryAssembler $assembler
     * @param RestOutcome $outcome
     * @param RoutePath $routePath
     * @param ActivityBuilderInterface $activityBuilder
     * @param ActivityRecorderInterface $activityRecorder
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param StoreManagerInterface $storeManager
     * @param RemoteAddress $remoteAddress
     * @param MillisecondClockInterface $clock
     * @param LoggerInterface $logger
     * @param ErrorMessage $errorMessage
     * @param EndpointMasker $endpointMasker
     */
    public function __construct(
        private readonly EndpointPolicy $endpointPolicy,
        private readonly LogEntryAssembler $assembler,
        private readonly RestOutcome $outcome,
        private readonly RoutePath $routePath,
        private readonly ActivityBuilderInterface $activityBuilder,
        private readonly ActivityRecorderInterface $activityRecorder,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly RemoteAddress $remoteAddress,
        private readonly MillisecondClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly ErrorMessage $errorMessage,
        private readonly EndpointMasker $endpointMasker
    ) {
    }

    /**
     * Record a call that has its response
     *
     * @param CapturedCall $call
     * @param array{code:int,headers:array<string,string|list<string>>,body:string|null,exception:bool} $response
     * @return void
     */
    public function record(array $call, array $response): void
    {
        try {
            $call['endpoint'] = $this->endpointMasker->mask($call['endpoint'], $call['pattern']);
            $storeId = (int) $this->storeManager->getStore()->getId();
            if (!$this->endpointPolicy->keepsResponse($response['code'], $storeId)) {
                return;
            }

            $durationMs = (float) max(0, $this->clock->nowMs() - $call['startedAtMs']);
            $action = $call['method'] . ' ' . $this->routePath->route($call['endpoint']);
            $activity = $this->activityBuilder->build(
                self::CHANNEL,
                mb_substr($action, 0, self::ACTION_MAX_LENGTH),
                $this->outcome->of($response['code'], $response['exception']),
                null,
                $durationMs,
                $this->message($response),
                $call['startedAtMs']
            );
            $activityId = $this->activityRecorder->record($activity);
            if ($activityId === null) {
                return;
            }

            $ipAddress = (string) $this->remoteAddress->getRemoteAddress();
            $entry = $this->assembler->assemble(
                $call,
                $response,
                $durationMs,
                $storeId,
                $ipAddress !== '' ? $ipAddress : null
            );
            $entry->setActivityId($activityId)->setCorrelationId($activity->getCorrelationId());
            $this->logEntryRepository->save($entry);
        } catch (\Throwable $exception) {
            $this->logger->error('The REST call could not be logged.', ['exception' => $exception]);
        }
    }

    /**
     * The error message of a failed call, for the activity's outcome
     *
     * @param array{code:int,headers:array<string,string|list<string>>,body:string|null,exception:bool} $response
     * @return string|null
     */
    private function message(array $response): ?string
    {
        if (!$response['exception'] || $response['body'] === null) {
            return null;
        }

        $message = $this->errorMessage->fromBody($response['body']);

        return $message !== null ? mb_substr($message, 0, self::MESSAGE_MAX_LENGTH) : null;
    }
}
