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
 * Removing the calls selected in the grid, which removes their activities too
 */
#[DbIsolation(true)]
class MassDeleteTest extends CallRemovalTestCase
{
    private const LISTING = 'api_logger_log_listing';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/massDelete';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    public function testTheSelectedCallsAndTheirActivitiesAreRemovedAndOthersKept(): void
    {
        $first = $this->seed('GET', '/default/V1/products/sku-1');
        $second = $this->seed('GET', '/default/V1/products/sku-2');
        $kept = $this->seed('GET', '/default/V1/products/sku-3');

        $this->post('backend/api_logger/log/massDelete', [
            'namespace' => self::LISTING,
            'selected' => [(string) self::idOf($first), (string) self::idOf($second)],
        ]);

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo(['A total of 2 call(s) were removed from the log.']),
            MessageInterface::TYPE_SUCCESS
        );
        foreach ([$first, $second] as $removed) {
            self::assertFalse(self::entryExists(self::idOf($removed)));
            self::assertFalse(self::activityExists(self::activityIdOf($removed)));
        }
        self::assertTrue(self::entryExists(self::idOf($kept)));
        self::assertTrue(self::activityExists(self::activityIdOf($kept)));
    }

    public function testAnAdminWhoMayNotDeleteActivitiesRemovesNothing(): void
    {
        $first = $this->seed('GET', '/default/V1/products/sku-1');
        $second = $this->seed('GET', '/default/V1/products/sku-2');
        $this->denyDeletingActivities();

        $this->post('backend/api_logger/log/massDelete', [
            'namespace' => self::LISTING,
            'selected' => [(string) self::idOf($first), (string) self::idOf($second)],
        ]);

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo([
                'No call was removed: removing calls also removes their activities, which your role may not delete.',
            ]),
            MessageInterface::TYPE_ERROR
        );
        $this->assertSessionMessages(self::isEmpty(), MessageInterface::TYPE_SUCCESS);
        foreach ([$first, $second] as $entry) {
            self::assertTrue(self::entryExists(self::idOf($entry)));
            self::assertTrue(self::activityExists(self::activityIdOf($entry)));
        }
    }
}
