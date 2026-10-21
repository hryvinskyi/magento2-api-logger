<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Replay;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Hryvinskyi\ApiLogger\Model\Http\StoredHeaders;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\Data\UserTokenParametersInterfaceFactory;
use Magento\Integration\Api\UserTokenIssuerInterface;

/**
 * Sends a logged call again
 *
 * Stored secrets are masked, so the call cannot go as its original caller: it goes as the admin who replays it, with
 * a token issued for that admin. Against a staging store it carries the token set for replays there, if any.
 *
 * Where it goes is decided by configuration alone: the staging base URL, or this store's secure base URL. Nothing
 * stored with the call (its Host or forwarding headers) can point it, and with it the admin token, anywhere else.
 * Headers the transport sets, cookies, and every masked header are left out. A call whose stored URL carries a
 * masked secret is refused: sent as is, it would ask for the mask instead of the resource. A replay against this
 * store is logged like any call.
 */
class ReplayService
{
    private const SKIPPED_HEADERS = [
        'host',
        'cookie',
        'accept-encoding',
        'content-length',
        'transfer-encoding',
        'connection',
        'authorization',
    ];
    private const AUTHORIZATION = 'Authorization';
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param ConfigInterface $config
     * @param EndpointUrlResolverInterface $urlResolver
     * @param StoredHeaders $storedHeaders
     * @param ReplayTransportInterface $transport
     * @param UserContextInterface $userContext
     * @param UserTokenIssuerInterface $tokenIssuer
     * @param UserTokenParametersInterfaceFactory $tokenParametersFactory
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly EndpointUrlResolverInterface $urlResolver,
        private readonly StoredHeaders $storedHeaders,
        private readonly ReplayTransportInterface $transport,
        private readonly UserContextInterface $userContext,
        private readonly UserTokenIssuerInterface $tokenIssuer,
        private readonly UserTokenParametersInterfaceFactory $tokenParametersFactory
    ) {
    }

    /**
     * Whether replaying the call may change data
     *
     * @param LogEntryInterface $entry
     * @return bool
     */
    public function writes(LogEntryInterface $entry): bool
    {
        return in_array(strtoupper($entry->getMethod()), self::WRITE_METHODS, true);
    }

    /**
     * Whether a replay goes to this store, as the admin who replays it, rather than to the configured staging store
     *
     * @return bool
     */
    public function replaysToThisStore(): bool
    {
        return $this->config->getReplayBaseUrl() === null;
    }

    /**
     * The URL a replay of the call is sent to
     *
     * @param LogEntryInterface $entry
     * @return string
     */
    public function target(LogEntryInterface $entry): string
    {
        return $this->urlResolver->resolve($entry, $this->config->getReplayBaseUrl());
    }

    /**
     * Whether the call can be sent again: not when its stored URL carries a masked secret
     *
     * @param LogEntryInterface $entry
     * @return bool
     */
    public function canReplay(LogEntryInterface $entry): bool
    {
        return !str_contains($entry->getEndpoint(), SanitizerInterface::MASK);
    }

    /**
     * Send the call again
     *
     * @param LogEntryInterface $entry
     * @return array{status: int, body: string, durationMs: float, url: string}
     * @throws LocalizedException When the call cannot be replayed or the target could not be reached
     */
    public function replay(LogEntryInterface $entry): array
    {
        if (!$this->canReplay($entry)) {
            throw new LocalizedException(
                __('This call cannot be replayed: its URL carries a secret that was masked when it was logged.')
            );
        }

        $url = $this->target($entry);
        $headers = $this->headers($entry);
        $token = $this->replaysToThisStore() ? $this->adminToken() : $this->config->getReplayToken();
        if ($token !== null && $token !== '') {
            $headers[self::AUTHORIZATION] = 'Bearer ' . $token;
        }

        return $this->transport->send(strtoupper($entry->getMethod()), $url, $headers, $entry->getRequestBody())
            + ['url' => $url];
    }

    /**
     * The stored request headers that can be sent again
     *
     * @param LogEntryInterface $entry
     * @return array<string, string>
     */
    private function headers(LogEntryInterface $entry): array
    {
        $headers = [];
        foreach ($this->storedHeaders->read($entry->getRequestHeaders()) as $header) {
            if ($header['masked'] || in_array(strtolower($header['name']), self::SKIPPED_HEADERS, true)) {
                continue;
            }

            $headers[$header['name']] = $header['value'];
        }

        return $headers;
    }

    /**
     * A token for the admin replaying the call
     *
     * @return string
     */
    private function adminToken(): string
    {
        return $this->tokenIssuer->create($this->userContext, $this->tokenParametersFactory->create());
    }
}
