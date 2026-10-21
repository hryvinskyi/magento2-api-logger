<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\ViewModel;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Compare\ComparisonView;
use Hryvinskyi\ApiLogger\Model\Export\ExporterPool;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The REST call panel of the activity detail page
 */
class RestCallPanel implements ArgumentInterface
{
    private const REPLAY_RESOURCE = 'Hryvinskyi_ApiLogger::replay';
    private const URL_SHORT_LENGTH = 120;
    private const REASONS = [
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content', 301 => 'Moved Permanently',
        302 => 'Found', 304 => 'Not Modified', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
        404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict', 422 => 'Unprocessable Entity',
        429 => 'Too Many Requests', 500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
    ];

    /**
     * The logged call of the activity, once looked up
     *
     * @var LogEntryInterface|null
     */
    private ?LogEntryInterface $entry = null;

    /**
     * Whether the call has been looked up; it may be gone, so a null entry does not tell
     *
     * @var bool
     */
    private bool $entryLookedUp = false;

    /**
     * @param RequestInterface $request
     * @param LogEntryRepositoryInterface $logEntryRepository
     * @param EndpointUrlResolverInterface $urlResolver
     * @param StoredHeaders $storedHeaders
     * @param ExporterPool $exporterPool
     * @param ReplayService $replayService
     * @param AuthorizationInterface $authorization
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param JsonHexTag $json
     * @param ComparisonView $comparisonView
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LogEntryRepositoryInterface $logEntryRepository,
        private readonly EndpointUrlResolverInterface $urlResolver,
        private readonly StoredHeaders $storedHeaders,
        private readonly ExporterPool $exporterPool,
        private readonly ReplayService $replayService,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly JsonHexTag $json,
        private readonly ComparisonView $comparisonView
    ) {
    }

    /**
     * The logged call of the activity on the page, or null when its entry is gone
     *
     * @return LogEntryInterface|null
     */
    public function entry(): ?LogEntryInterface
    {
        if ($this->entryLookedUp) {
            return $this->entry;
        }

        $this->entryLookedUp = true;
        $activityId = $this->request->getParam('id');
        try {
            $this->entry = is_numeric($activityId)
                ? $this->logEntryRepository->getByActivityId((int) $activityId)
                : null;
        } catch (NoSuchEntityException) {
            $this->entry = null;
        }

        return $this->entry;
    }

    /**
     * Full URL of the call
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function url(LogEntryInterface $entry): string
    {
        return $this->urlResolver->resolve($entry);
    }

    /**
     * Whether the URL is long enough to be clipped until clicked
     *
     * @param LogEntryInterface $entry
     * @return bool
     */
    public function isLongUrl(LogEntryInterface $entry): bool
    {
        return mb_strlen($this->url($entry)) > self::URL_SHORT_LENGTH;
    }

    /**
     * Stored headers of the request or the response, with whether each value is masked
     *
     * @param string|null $json
     * @return list<array{name: string, value: string, masked: bool}>
     */
    public function headers(?string $json): array
    {
        return $this->storedHeaders->read($json);
    }

    /**
     * Content type the headers declare, or an empty string
     *
     * @param string|null $json
     * @return string
     */
    public function contentType(?string $json): string
    {
        return explode(';', $this->storedHeaders->value($json, 'content-type'))[0];
    }

    /**
     * A size in bytes as people read it
     *
     * @param string|null $body
     * @return string
     */
    public function size(?string $body): string
    {
        $bytes = strlen((string) $body);

        return match (true) {
            $bytes >= 1048576 => sprintf('%.1f MB', $bytes / 1048576),
            $bytes >= 1024 => sprintf('%.1f KB', $bytes / 1024),
            default => $bytes . ' B',
        };
    }

    /**
     * Status line of the response: "201 Created"
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function statusText(LogEntryInterface $entry): string
    {
        $code = $entry->getResponseCode();

        return $code === null ? '—' : trim($code . ' ' . (self::REASONS[$code] ?? ''));
    }

    /**
     * CSS modifier of the status: ok, warning or error
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function statusTone(LogEntryInterface $entry): string
    {
        $code = (int) $entry->getResponseCode();

        return match (true) {
            $code >= 500 || $code === 0 => 'error',
            $code >= 400 => 'warning',
            default => 'ok',
        };
    }

    /**
     * Export formats the menu offers
     *
     * @param LogEntryInterface $entry
     * @return list<array{label: string, url: string}>
     */
    public function exports(LogEntryInterface $entry): array
    {
        $exports = [];
        foreach ($this->exporterPool->all() as $format => $exporter) {
            $exports[] = [
                'label' => $exporter->getLabel(),
                'url' => $this->urlBuilder->getUrl(
                    'api_logger/log/export',
                    ['id' => $entry->getEntityId(), 'format' => $format]
                ),
            ];
        }

        return $exports;
    }

    /**
     * Where a body is downloaded whole
     *
     * @param LogEntryInterface $entry
     * @param string $part "request" or "response"
     * @return string
     */
    public function downloadUrl(LogEntryInterface $entry, string $part): string
    {
        return $this->urlBuilder->getUrl('api_logger/log/download', ['id' => $entry->getEntityId(), 'part' => $part]);
    }

    /**
     * Whether the admin may replay calls
     *
     * @return bool
     */
    public function canReplay(): bool
    {
        return $this->authorization->isAllowed(self::REPLAY_RESOURCE);
    }

    /**
     * Whether replaying the call may change data
     *
     * @param LogEntryInterface $entry
     * @return bool
     */
    public function replayWrites(LogEntryInterface $entry): bool
    {
        return $this->replayService->writes($entry);
    }

    /**
     * The URL a replay of the call is sent to, exactly as the replay sends it
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function replayTarget(LogEntryInterface $entry): string
    {
        return $this->replayService->target($entry);
    }

    /**
     * Whether replays go to this store as the admin, rather than to the configured staging store
     *
     * @return bool
     */
    public function replaysToThisStore(): bool
    {
        return $this->replayService->replaysToThisStore();
    }

    /**
     * Script config of the panel, for data-mage-init
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function scriptConfig(LogEntryInterface $entry): string
    {
        $id = ['id' => $entry->getEntityId()];

        return (string) $this->json->serialize([
            'Hryvinskyi_ApiLogger/js/view/rest-panel' => [
                'bodyUrl' => $this->urlBuilder->getUrl('api_logger/log/body', $id),
                'downloadUrl' => $this->urlBuilder->getUrl('api_logger/log/download', $id),
                'replayUrl' => $this->urlBuilder->getUrl('api_logger/log/replay', $id),
                'compareUrl' => $this->urlBuilder->getUrl('api_logger/log/compare', $id),
                'formKey' => $this->formKey->getFormKey(),
                'labels' => $this->labels(),
            ],
        ]);
    }

    /**
     * Wording the scripts show, translated
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        return [
            'hits' => (string) __('%1 hits'),
            'oneHit' => (string) __('1 hit'),
            'noHits' => (string) __('No hits'),
            'showing' => (string) __('Showing lines 1–%1 of %2. Search covers the whole body.'),
            'copied' => (string) __('Copied'),
            'loadFailed' => (string) __('The body could not be loaded.'),
            'replay' => (string) __('Replay'),
            'cancel' => (string) __('Cancel'),
            'close' => (string) __('Close'),
            'sending' => (string) __('Sending…'),
            'original' => (string) __('Original'),
            'replayed' => (string) __('Replay'),
            'replayFailed' => (string) __('The replay failed.'),
            'compare' => (string) __('Compare'),
            'back' => (string) __('Back'),
            'noCandidates' => (string) __('No other call to this endpoint is logged.'),
            'pickFirst' => (string) __('Pick a call to compare with.'),
            'thisCall' => (string) __('This call'),
            'otherCall' => (string) __('Call #%1'),
            'status' => (string) __('Status'),
            'duration' => (string) __('Duration'),
            'responseBody' => (string) __('Response body'),
            'requestBody' => (string) __('Request body'),
            'identical' => (string) __('Identical'),
            'identicalInFirst' => (string) __('Identical in the first %1 KB', $this->comparisonView->bodyLimitKb()),
            'loading' => (string) __('Loading…'),
        ];
    }
}
