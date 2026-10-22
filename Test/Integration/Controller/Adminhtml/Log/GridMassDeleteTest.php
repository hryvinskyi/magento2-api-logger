<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\Backend\Model\Authorization\RoleLocator;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Response;

/**
 * The REST call grid offers "Delete" only to an admin who may delete both the calls and their activities
 *
 * Removing calls removes their activities, so the delete action refuses an admin without either permission; the
 * grid leaves the action out for that admin rather than offer one that always fails. The admin's role is denied a
 * resource in the access list built for this process; the list is rebuilt from the stored roles when each test ends.
 */
#[DbIsolation(true)]
class GridMassDeleteTest extends LogControllerTestCase
{
    private const MASS_DELETE = 'api_logger\/log\/massDelete';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/index';

    protected function tearDown(): void
    {
        $acl = Bootstrap::getObjectManager()->get(AclBuilder::class);
        self::assertInstanceOf(AclBuilder::class, $acl);
        $acl->resetRuntimeAcl();
        parent::tearDown();
    }

    public function testAnAdminWhoMayDeleteCallsAndActivitiesIsOfferedTheDelete(): void
    {
        $this->dispatch($this->uri);

        self::assertStringContainsString(self::MASS_DELETE, $this->gridPage());
    }

    public function testAnAdminWhoMayNotDeleteActivitiesIsNotOfferedTheDelete(): void
    {
        $this->deny('Hryvinskyi_ActivityLog::delete');

        $this->dispatch($this->uri);

        self::assertStringNotContainsString(self::MASS_DELETE, $this->gridPage());
    }

    public function testAnAdminWhoMayNotDeleteCallsIsNotOfferedTheDelete(): void
    {
        $this->deny('Hryvinskyi_ApiLogger::delete');

        $this->dispatch($this->uri);

        self::assertStringNotContainsString(self::MASS_DELETE, $this->gridPage());
    }

    /**
     * The grid page, checked to be the page that holds the grid
     *
     * @return string
     */
    private function gridPage(): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);
        $body = (string) $response->getBody();
        self::assertSame(200, $response->getHttpResponseCode());
        self::assertStringContainsString('api_logger_log_listing', $body);

        return $body;
    }

    /**
     * Take one resource away from the logged-in admin's role
     *
     * @param string $resource
     * @return void
     */
    private function deny(string $resource): void
    {
        $locator = Bootstrap::getObjectManager()->get(RoleLocator::class);
        self::assertInstanceOf(RoleLocator::class, $locator);
        $role = $locator->getAclRoleId();
        self::assertIsScalar($role, 'No admin is logged in.');

        $builder = Bootstrap::getObjectManager()->get(AclBuilder::class);
        self::assertInstanceOf(AclBuilder::class, $builder);
        $acl = $builder->getAcl();
        $acl->deny((string) $role, $resource);
        self::assertFalse($acl->isAllowed((string) $role, $resource));
        self::assertTrue($acl->isAllowed((string) $role, $this->resource));
    }
}
