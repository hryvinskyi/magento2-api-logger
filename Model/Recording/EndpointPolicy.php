<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Hryvinskyi\ApiLogger\Api\ConfigInterface;
use Hryvinskyi\ApiLogger\Api\EndpointMatcherInterface;

/**
 * Decides which REST calls are logged: those to an enabled endpoint, answered with an enabled response code
 *
 * Whether a call is logged is decided before Magento applies the store code of its URL, so the settings are read for
 * the store the call's path names; a path without one reads them for the store Magento resolved for the request.
 */
class EndpointPolicy
{
    /**
     * @param ConfigInterface $config
     * @param EndpointMatcherInterface $endpointMatcher
     * @param CallStore $callStore
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly EndpointMatcherInterface $endpointMatcher,
        private readonly CallStore $callStore
    ) {
    }

    /**
     * Whether a call to the endpoint with the method is logged
     *
     * @param string $endpoint
     * @param string $method
     * @return bool
     */
    public function logsCall(string $endpoint, string $method): bool
    {
        return $this->matchingPattern($endpoint, $method) !== null;
    }

    /**
     * The enabled pattern a call is logged under, or null when it is not logged
     *
     * @param string $endpoint
     * @param string $method
     * @return string|null
     */
    public function matchingPattern(string $endpoint, string $method): ?string
    {
        $storeId = $this->callStore->storeId($endpoint);
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }

        return $this->selected($endpoint, $method, $storeId);
    }

    /**
     * The pattern selected for the call's endpoint, whether or not logging is switched on
     *
     * Calls stored earlier are read against the selection this way: the selection names their route parameters even
     * while nothing new is logged.
     *
     * @param string $endpoint
     * @param string $method
     * @return string|null
     */
    public function selectedPattern(string $endpoint, string $method): ?string
    {
        return $this->selected($endpoint, $method, $this->callStore->storeId($endpoint));
    }

    /**
     * Whether a call answered with the code is kept; every code is kept when none is chosen
     *
     * @param int $responseCode
     * @param int|null $storeId
     * @return bool
     */
    public function keepsResponse(int $responseCode, ?int $storeId): bool
    {
        $codes = $this->config->getEnabledResponseCodes($storeId);

        return $codes === [] || in_array((string) $responseCode, $codes, true);
    }

    /**
     * The selected pattern a call matches, read for a store
     *
     * A pattern that names every segment wins over one that ends in a wildcard, whatever their order: only the named
     * one says which segment holds which route parameter.
     *
     * @param string $endpoint
     * @param string $method
     * @param int|null $storeId
     * @return string|null
     */
    private function selected(string $endpoint, string $method, ?int $storeId): ?string
    {
        $wildcard = null;
        foreach ($this->config->getEnabledEndpoints($storeId) as $pattern) {
            if (!$this->endpointMatcher->matches($endpoint, $method, $pattern)) {
                continue;
            }
            if (!str_ends_with(rtrim($pattern), EndpointMatcherInterface::REST_OF_PATH)) {
                return $pattern;
            }
            $wildcard ??= $pattern;
        }

        return $wildcard;
    }
}
