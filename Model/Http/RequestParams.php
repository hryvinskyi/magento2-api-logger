<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Http;

use Magento\Framework\App\RequestInterface;

/**
 * Request parameters read as the type an action needs
 */
class RequestParams
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    /**
     * A whole number, or the default when the parameter is missing or not a number
     *
     * @param string $name
     * @param int $default
     * @return int
     */
    public function int(string $name, int $default = 0): int
    {
        $value = $this->request->getParam($name);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Text, or an empty string when the parameter is missing or not text
     *
     * @param string $name
     * @return string
     */
    public function text(string $name): string
    {
        $value = $this->request->getParam($name);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
