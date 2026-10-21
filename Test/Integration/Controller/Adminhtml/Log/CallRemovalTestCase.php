<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\CallRemover;
use Magento\Backend\Model\Authorization\RoleLocator;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Removing calls from the log, as an admin who may delete activities and as one who may not
 *
 * The admin's role is denied a resource in the access list built for this process; the list is rebuilt from the
 * stored roles when each test ends, so the denial never outlives the test.
 */
abstract class CallRemovalTestCase extends LogControllerTestCase
{
    protected function tearDown(): void
    {
        self::service(AclBuilder::class)->resetRuntimeAcl();
        parent::tearDown();
    }

    /**
     * Take away the permission to delete activities from the logged-in admin's role, keeping every other one
     *
     * @return void
     */
    protected function denyDeletingActivities(): void
    {
        $roleId = self::service(RoleLocator::class)->getAclRoleId();
        self::assertIsScalar($roleId, 'No admin is logged in.');
        $role = (string) $roleId;
        self::assertNotSame('', $role, 'No admin is logged in.');
        $acl = self::service(AclBuilder::class)->getAcl();
        $acl->deny($role, CallRemover::ACTIVITY_DELETE_RESOURCE);

        self::assertFalse($acl->isAllowed($role, CallRemover::ACTIVITY_DELETE_RESOURCE));
        self::assertTrue($acl->isAllowed($role, 'Hryvinskyi_ApiLogger::delete'));
    }

    /**
     * Dispatch a POST to an admin URL
     *
     * @param string $uri
     * @param array<string, mixed> $post
     * @return void
     */
    protected function post(string $uri, array $post = []): void
    {
        $this->httpRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->httpRequest()->setPostValue($post);
        $this->dispatch($uri);
    }

    /**
     * Whether the log still holds the entry
     *
     * @param int $entryId
     * @return bool
     */
    protected static function entryExists(int $entryId): bool
    {
        try {
            self::service(LogEntryRepositoryInterface::class)->getById($entryId);
        } catch (NoSuchEntityException) {
            return false;
        }

        return true;
    }

    /**
     * Whether the journal still holds the activity
     *
     * @param int $activityId
     * @return bool
     */
    protected static function activityExists(int $activityId): bool
    {
        try {
            self::service(ActivityRepositoryInterface::class)->get($activityId);
        } catch (NoSuchEntityException) {
            return false;
        }

        return true;
    }
}
