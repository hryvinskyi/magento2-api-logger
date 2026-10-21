<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Replay;

use Magento\Framework\Exception\LocalizedException;

/**
 * Sends a rebuilt REST call over HTTP
 */
interface ReplayTransportInterface
{
    /**
     * Send the call and answer with what came back
     *
     * @param string $method
     * @param string $url
     * @param array<string,string> $headers
     * @param string|null $body
     * @return array{status: int, body: string, durationMs: float}
     * @throws LocalizedException When the target could not be reached
     */
    public function send(string $method, string $url, array $headers, ?string $body): array;
}
