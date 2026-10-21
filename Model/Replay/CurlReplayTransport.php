<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Replay;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * Sends replayed calls with Magento's cURL client; methods other than GET go as their own verb, with the body
 */
class CurlReplayTransport implements ReplayTransportInterface
{
    private const READ_METHOD = 'GET';

    /**
     * @param CurlFactory $curlFactory
     * @param MillisecondClockInterface $clock
     * @param int $timeoutSeconds
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly MillisecondClockInterface $clock,
        private readonly int $timeoutSeconds = 30
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->timeoutSeconds);
        $curl->setHeaders($headers);

        $startedAtMs = $this->clock->nowMs();
        try {
            if ($method === self::READ_METHOD) {
                $curl->get($url);
            } else {
                $curl->setOptions([CURLOPT_CUSTOMREQUEST => $method]);
                $curl->post($url, (string) $body);
            }
        } catch (\Exception $exception) {
            throw new LocalizedException(__('%1 could not be reached.', strtok($url, '?')), $exception);
        }

        return [
            'status' => (int) $curl->getStatus(),
            'body' => (string) $curl->getBody(),
            'durationMs' => (float) ($this->clock->nowMs() - $startedAtMs),
        ];
    }
}
