<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Rest;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;

/**
 * Fake Request Object
 *
 * A lightweight request object used for endpoint pattern matching.
 * Extends Magento's REST request to provide a compatible request instance
 * for route matching without the overhead of a full HTTP request.
 *
 * This class is primarily used by the EndpointMatcher to simulate requests
 * when matching API endpoint patterns against actual endpoint paths.
 */
class FakeRequest extends DataObject implements RequestInterface
{
    /**
     * @var string|null
     */
    private ?string $moduleName = null;

    /**
     * @var string|null
     */
    private ?string $actionName = null;

    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * @var string|null
     */
    private ?string $pathInfo = null;

    /**
     * @inheritDoc
     */
    public function getModuleName(): ?string
    {
        return $this->moduleName;
    }

    /**
     * @inheritDoc
     */
    public function setModuleName($name): self
    {
        $this->moduleName = $name;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    /**
     * @inheritDoc
     */
    public function setActionName($name): self
    {
        $this->actionName = $name;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getParam($key, $defaultValue = null)
    {
        return $this->params[$key] ?? $defaultValue;
    }

    /**
     * @inheritDoc
     */
    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @inheritDoc
     */
    public function isSecure(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getCookie($name, $default = null)
    {
        return $default;
    }

    /**
     * Set the path info for route matching
     *
     * @param string $pathInfo
     * @return $this
     */
    public function setPathInfo($pathInfo): self
    {
        $this->pathInfo = $pathInfo;
        return $this;
    }

    /**
     * Get the path info
     *
     * @return string|null
     */
    public function getPathInfo(): ?string
    {
        return $this->pathInfo;
    }
}