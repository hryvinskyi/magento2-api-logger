<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Fixture\DbIsolation;

/**
 * Removing one logged call, which removes its activity too
 */
#[DbIsolation(true)]
class DeleteTest extends CallRemovalTestCase
{
    private const ENDPOINT = '/default/V1/products/sku-1';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/delete/id/987654321';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    public function testTheCallAndItsActivityAreRemoved(): void
    {
        $entry = $this->seed('DELETE', self::ENDPOINT);

        $this->post(sprintf('backend/api_logger/log/delete/id/%d', self::idOf($entry)));

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo(['The call was removed from the log.']),
            MessageInterface::TYPE_SUCCESS
        );
        self::assertFalse(self::entryExists(self::idOf($entry)));
        self::assertFalse(self::activityExists(self::activityIdOf($entry)));
    }

    public function testAnAdminWhoMayNotDeleteActivitiesRemovesNothing(): void
    {
        $entry = $this->seed('DELETE', self::ENDPOINT);
        $this->denyDeletingActivities();

        $this->post(sprintf('backend/api_logger/log/delete/id/%d', self::idOf($entry)));

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo([
                'The call was not removed: removing it also removes its activity, which your role may not delete.',
            ]),
            MessageInterface::TYPE_ERROR
        );
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_SUCCESS);
        self::assertTrue(self::entryExists(self::idOf($entry)));
        self::assertTrue(self::activityExists(self::activityIdOf($entry)));
    }
}
