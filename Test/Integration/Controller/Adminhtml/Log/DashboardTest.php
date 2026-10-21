<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

/**
 * Old links to the REST dashboard land on the activity log dashboard
 */
class DashboardTest extends LogControllerTestCase
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/dashboard';

    public function testRedirectsToTheActivityLogDashboard(): void
    {
        $this->dispatch('backend/api_logger/log/dashboard');

        $this->assertRedirect(self::stringContains('activitylog/dashboard/index'));
    }
}
