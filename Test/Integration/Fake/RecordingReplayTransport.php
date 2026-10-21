<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Fake;

use Hryvinskyi\ApiLogger\Model\Replay\ReplayTransportInterface;

/**
 * Keeps every call it is asked to send and answers with a fixed response, without any network
 */
class RecordingReplayTransport implements ReplayTransportInterface
{
    /**
     * @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}>
     */
    private array $sent = [];

    /**
     * @param int $status
     * @param string $body
     */
    public function __construct(
        private readonly int $status = 201,
        private readonly string $body = '{"replayed":true}'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        return ['status' => $this->status, 'body' => $this->body, 'durationMs' => 7.0];
    }

    /**
     * The calls sent so far, oldest first
     *
     * @return list<array{method: string, url: string, headers: array<string, string>, body: string|null}>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
