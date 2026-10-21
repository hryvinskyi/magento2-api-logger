<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Layout;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Test\Integration\Fixture\RestCallSeeder;
use Magento\Backend\Model\Authorization\RoleLocator;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The REST panel on the activity page of a "rest" activity is shown only to an admin who may view the call log
 *
 * The activity page itself needs only the activity log's permission, so an admin without the call log's sees the
 * activity without the call's headers and bodies. The admin's role is denied the resource in the access list built
 * for this process; the list is rebuilt from the stored roles when each test ends.
 */
#[DbIsolation(true)]
class RestPanelPermissionTest extends AbstractBackendController
{
    private const LOGS_RESOURCE = 'Hryvinskyi_ApiLogger::logs';
    private const PANEL = '//section[contains(concat(" ", normalize-space(@class), " "), " rest-panel ")]';
    private const ACTION = 'GET /V1/products/sku-1';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/view/id/1';

    protected function tearDown(): void
    {
        $acl = Bootstrap::getObjectManager()->get(AclBuilder::class);
        self::assertInstanceOf(AclBuilder::class, $acl);
        $acl->resetRuntimeAcl();
        parent::tearDown();
    }

    public function testAnAdminWhoMayViewTheLogSeesThePanel(): void
    {
        $entry = $this->seed();

        $this->dispatch(sprintf('backend/activitylog/activity/view/id/%d', $this->activityIdOf($entry)));

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertStringContainsString(self::ACTION, $this->body());
        self::assertSame(1, $this->panels());
    }

    public function testAnAdminWhoMayNotViewTheLogSeesTheActivityWithoutThePanel(): void
    {
        $entry = $this->seed();
        $this->denyViewingTheLog();

        $this->dispatch(sprintf('backend/activitylog/activity/view/id/%d', $this->activityIdOf($entry)));

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertStringContainsString(self::ACTION, $this->body());
        self::assertSame(0, $this->panels());
        self::assertStringNotContainsString('Hryvinskyi_ApiLogger/js/view/rest-panel', $this->body());
    }

    /**
     * Take away the permission to view the call log from the logged-in admin's role, keeping the activity log's
     *
     * @return void
     */
    private function denyViewingTheLog(): void
    {
        $locator = Bootstrap::getObjectManager()->get(RoleLocator::class);
        self::assertInstanceOf(RoleLocator::class, $locator);
        $roleId = $locator->getAclRoleId();
        self::assertIsScalar($roleId, 'No admin is logged in.');
        $role = (string) $roleId;
        self::assertNotSame('', $role, 'No admin is logged in.');

        $builder = Bootstrap::getObjectManager()->get(AclBuilder::class);
        self::assertInstanceOf(AclBuilder::class, $builder);
        $acl = $builder->getAcl();
        $acl->deny($role, self::LOGS_RESOURCE);

        self::assertFalse($acl->isAllowed($role, self::LOGS_RESOURCE));
        self::assertTrue($acl->isAllowed($role, 'Hryvinskyi_ActivityLog::view'));
    }

    /**
     * Store a call with its activity
     *
     * @return LogEntryInterface
     */
    private function seed(): LogEntryInterface
    {
        $seeder = Bootstrap::getObjectManager()->get(RestCallSeeder::class);
        self::assertInstanceOf(RestCallSeeder::class, $seeder);

        return $seeder->seed('GET', '/default/V1/products/sku-1', 200, null, '{"sku":"sku-1"}');
    }

    /**
     * @param LogEntryInterface $entry
     * @return int
     */
    private function activityIdOf(LogEntryInterface $entry): int
    {
        $id = $entry->getActivityId();
        self::assertNotNull($id);

        return $id;
    }

    /**
     * @return Response
     */
    private function httpResponse(): Response
    {
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * @return string
     */
    private function body(): string
    {
        return (string) $this->httpResponse()->getBody();
    }

    /**
     * How many REST panels the rendered page holds, read from the parsed page
     *
     * @return int
     */
    private function panels(): int
    {
        $html = $this->body();
        self::assertNotSame('', $html);

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $panels = (new \DOMXPath($document))->query(self::PANEL);
        self::assertInstanceOf(\DOMNodeList::class, $panels);

        return $panels->length;
    }
}
