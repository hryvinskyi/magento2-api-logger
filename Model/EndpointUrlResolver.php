<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\EndpointUrlResolverInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url\ScopeInterface as UrlScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Rebuilds the URL of a logged call
 *
 * The logged path is what follows the REST front name, so the front name Magento is configured with is put back
 * between the base URL and the path. A path that already starts with it (stored before 2.0) is left as it is, and
 * when no front name is configured the path is appended as stored.
 *
 * The base is the one asked for, else the secure base URL of the store the call was made to, or of the default store
 * view when the call has no store or its store is gone. Nothing stored with the call decides where it points: the
 * stored headers (Host, X-Forwarded-Proto) came from whoever sent the call and are never trusted as a destination.
 */
class EndpointUrlResolver implements EndpointUrlResolverInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param AreaList $areaList
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly AreaList $areaList
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(LogEntryInterface $entry, ?string $baseUrl = null): string
    {
        return rtrim($baseUrl ?? $this->baseUrl($entry->getStoreId()), '/') . $this->path($entry->getEndpoint());
    }

    /**
     * The logged path behind the REST front name
     *
     * @param string $endpoint
     * @return string
     */
    private function path(string $endpoint): string
    {
        $path = '/' . ltrim($endpoint, '/');
        $frontName = (string) $this->areaList->getFrontName(Area::AREA_WEBAPI_REST);
        if ($frontName === '' || str_starts_with($path, '/' . $frontName . '/')) {
            return $path;
        }

        return '/' . $frontName . $path;
    }

    /**
     * The secure base web URL of the call's store, or an empty string when no store has one
     *
     * @param int|null $storeId
     * @return string
     */
    private function baseUrl(?int $storeId): string
    {
        $store = $this->store($storeId);

        return $store instanceof UrlScopeInterface
            ? (string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)
            : '';
    }

    /**
     * The store of the call, or the default store view when it has none or it no longer exists
     *
     * @param int|null $storeId
     * @return StoreInterface|null
     */
    private function store(?int $storeId): ?StoreInterface
    {
        if ($storeId === null) {
            return $this->storeManager->getDefaultStoreView();
        }

        try {
            return $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return $this->storeManager->getDefaultStoreView();
        }
    }
}
