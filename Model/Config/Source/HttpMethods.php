<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for HTTP methods
 */
class HttpMethods implements OptionSourceInterface
{
    /**
     * HTTP methods a call can be made with
     *
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'GET', 'label' => __('GET')],
            ['value' => 'POST', 'label' => __('POST')],
            ['value' => 'PUT', 'label' => __('PUT')],
            ['value' => 'DELETE', 'label' => __('DELETE')],
            ['value' => 'PATCH', 'label' => __('PATCH')],
            ['value' => 'HEAD', 'label' => __('HEAD')],
            ['value' => 'OPTIONS', 'label' => __('OPTIONS')],
        ];
    }
}
