<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Block\Adminhtml\Log;

use Magento\Backend\Block\Template;

/**
 * Dashboard block for endpoint analytics
 */
class Dashboard extends Template
{
    /**
     * Get chart data AJAX URL
     *
     * @return string
     */
    public function getChartDataUrl(): string
    {
        return $this->getUrl('*/*/chartData');
    }
}
