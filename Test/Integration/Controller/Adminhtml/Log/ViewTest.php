<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Fixture\DbIsolation;

/**
 * Old links to a logged call land on the call's activity
 */
#[DbIsolation(true)]
class ViewTest extends LogControllerTestCase
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/view';

    public function testACallRedirectsToItsActivity(): void
    {
        $entry = $this->seed('GET', '/default/V1/products');

        $this->dispatch(sprintf('backend/api_logger/log/view/id/%d', self::idOf($entry)));

        $this->assertRedirect(self::stringContains(
            sprintf('activitylog/activity/view/id/%d/', self::activityIdOf($entry))
        ));
    }

    public function testAnUnknownCallGoesBackToTheGridWithAnError(): void
    {
        $this->dispatch('backend/api_logger/log/view/id/987654321');

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo(['The call is no longer logged.']),
            MessageInterface::TYPE_ERROR
        );
    }
}
