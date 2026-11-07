<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Test\Integration\Controller\Fixture\ActivityLogMenu;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The entries this module puts into the Activity Log menu, as the Activity Log dashboard prints the menu
 *
 * Each test dispatches once: the test application serves a second dispatch of one test from the first one's state.
 */
class ActivityLogMenuTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::dashboard';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/dashboard/index';

    public function testTheRestCallLogIsInLogs(): void
    {
        $log = $this->menu()->entry('REST API');

        self::assertSame('Logs', $log['heading']);
        self::assertStringContainsString('/api_logger/log/index/', $log['href']);
        self::assertTrue($log['icon'], 'REST API has an icon');
    }

    public function testTheRestLoggingSettingsAreInSettings(): void
    {
        $settings = $this->menu()->entry('REST API logging');

        self::assertSame('Settings', $settings['heading']);
        self::assertStringContainsString('/system_config/edit/section/api_logger/', $settings['href']);
        self::assertTrue($settings['icon'], 'REST API logging has an icon');
    }

    /**
     * The Activity Log menu of the dashboard, which must have been answered
     *
     * @return ActivityLogMenu
     */
    private function menu(): ActivityLogMenu
    {
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());

        return new ActivityLogMenu((string) $response->getBody());
    }
}
