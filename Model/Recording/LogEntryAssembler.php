<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Model\Http\Masker;

/**
 * Builds the log entry of a call: what the settings say to keep, with every secret masked
 *
 * A route whose response is itself a secret (a token, a second factor's shared secret), which no field name gives
 * away, has its response body stored as the mask, whatever its shape. The endpoint is stored as the call carries it:
 * its secrets are masked before it reaches the assembler.
 *
 * @phpstan-import-type CapturedCall from RestCallCapture
 */
class LogEntryAssembler
{
    private const USER_AGENT = 'user-agent';
    private const USER_AGENT_MAX_LENGTH = 512;

    /**
     * @param ConfigInterface $config
     * @param Masker $masker
     * @param LogEntryInterfaceFactory $logEntryFactory
     * @param SecretResponsePolicy $secretResponsePolicy
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly Masker $masker,
        private readonly LogEntryInterfaceFactory $logEntryFactory,
        private readonly SecretResponsePolicy $secretResponsePolicy
    ) {
    }

    /**
     * The entry of a call that has its response
     *
     * @param CapturedCall $call
     * @param array{code:int,headers:array<string,string|list<string>>,body:string|null,exception:bool} $response
     * @param float $durationMs
     * @param int|null $storeId
     * @param string|null $ipAddress
     * @return LogEntryInterface
     */
    public function assemble(
        array $call,
        array $response,
        float $durationMs,
        ?int $storeId,
        ?string $ipAddress
    ): LogEntryInterface {
        $entry = $this->logEntryFactory->create();
        $entry->setEndpoint($call['endpoint'])
            ->setMethod($call['method'])
            ->setStoreId($storeId)
            ->setIpAddress($ipAddress)
            ->setUserAgent($this->userAgent($call['headers']))
            ->setResponseCode($response['code'])
            ->setDuration($durationMs)
            ->setIsException($response['exception']);

        if ($this->config->shouldLogRequestHeaders($storeId)) {
            $entry->setRequestHeaders($this->masker->headers($call['headers']));
        }

        if ($this->config->shouldLogRequestBody($storeId) && $call['body'] !== null) {
            $entry->setRequestBody($this->masker->text($call['body']));
        }

        if ($this->config->shouldLogResponseHeaders($storeId)) {
            $entry->setResponseHeaders($this->masker->headers($response['headers']));
        }

        if ($this->config->shouldLogResponseBody($storeId) && $response['body'] !== null) {
            $entry->setResponseBody(
                $this->secretResponsePolicy->masksWholeResponse($call['endpoint'])
                    ? SanitizerInterface::MASK
                    : $this->masker->text($response['body'])
            );
        }

        return $entry;
    }

    /**
     * The caller's user agent, cut to what the column holds
     *
     * @param array<string,string|list<string>> $headers
     * @return string|null
     */
    private function userAgent(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === self::USER_AGENT) {
                $agent = is_array($value) ? implode(', ', $value) : $value;

                return $agent === '' ? null : mb_substr($agent, 0, self::USER_AGENT_MAX_LENGTH);
            }
        }

        return null;
    }
}
