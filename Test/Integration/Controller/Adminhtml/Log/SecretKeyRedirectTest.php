<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\TestFramework\Fixture\DbIsolation;

/**
 * With "Add Secret Key to URLs" on, the old REST links land on activity log pages that open
 *
 * The admin router checks a link's key against the key of the request's route id, controller and action. A link to
 * the activity log built from its URL front name carries another key and is answered with a redirect to the admin
 * start page. Each test dispatches once: the test application serves a second dispatch of one test from the first
 * one's state.
 */
#[DbIsolation(true)]
class SecretKeyRedirectTest extends LogControllerTestCase
{
    private const ROUTE_ID = 'hryvinskyi_activitylog';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->backendUrl()->turnOnSecretKey();
        $this->uri = $this->path($this->backendUrl()->getUrl('api_logger/log/dashboard'));
    }

    public function testTheDashboardLinkCarriesTheKeyTheActivityLogRouterAccepts(): void
    {
        $this->dispatch($this->uri);

        $this->assertKeyedFor('dashboard', 'index');
    }

    public function testACallLinkCarriesTheKeyTheActivityLogRouterAccepts(): void
    {
        $entry = $this->seed('GET', '/default/V1/products');

        $this->dispatch($this->path(
            $this->backendUrl()->getUrl('api_logger/log/view', ['id' => self::idOf($entry)])
        ));

        $this->assertKeyedFor('activity', 'view');
    }

    /**
     * Assert the response redirects to the activity log page with the key its router checks
     *
     * @param string $controller
     * @param string $action
     * @return void
     */
    private function assertKeyedFor(string $controller, string $action): void
    {
        $location = (string) $this->header('Location');
        self::assertSame(
            1,
            preg_match('#/activitylog/' . $controller . '/' . $action . '/.*key/([0-9a-f]+)/#', $location, $parts),
            $location . ' leads to the activity log page with a secret key'
        );
        self::assertSame(
            $this->backendUrl()->getSecretKey(self::ROUTE_ID, $controller, $action),
            $parts[1],
            $location . ' carries the key the router checks'
        );
    }

    /**
     * The path the test application dispatches for an absolute admin URL
     *
     * @param string $url
     * @return string
     */
    private function path(string $url): string
    {
        $start = strpos($url, 'backend/');
        self::assertNotFalse($start, $url . ' is an admin URL');

        return substr($url, $start);
    }

    /**
     * @return BackendUrl
     */
    private function backendUrl(): BackendUrl
    {
        return self::service(BackendUrl::class);
    }
}
