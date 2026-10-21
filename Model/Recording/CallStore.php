<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\PathProcessor;

/**
 * The store a REST call targets, read from the store code in its path
 *
 * A call is captured before Magento applies the store code of its URL, so the store it asks for is resolved here the
 * way Magento will: "all" is the admin store; a path without a store code, or with a code no store has, gives null,
 * which reads settings for the store Magento resolved for the request.
 */
class CallStore
{
    /**
     * @param RoutePath $routePath
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RoutePath $routePath,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Id of the store the call targets, or null when its path names none that exists
     *
     * @param string $endpoint
     * @return int|null
     */
    public function storeId(string $endpoint): ?int
    {
        $code = $this->routePath->storeCode($endpoint);
        if ($code === null) {
            return null;
        }

        if ($code === PathProcessor::ALL_STORE_CODE) {
            return Store::DEFAULT_STORE_ID;
        }

        try {
            return (int) $this->storeManager->getStore($code)->getId();
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
