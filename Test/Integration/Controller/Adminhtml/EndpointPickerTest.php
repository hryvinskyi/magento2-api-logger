<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The endpoint picker of the REST logging settings, rendered on the real configuration page
 */
class EndpointPickerTest extends AbstractBackendController
{
    public function testEachGroupToggleCarriesTheChevronIcon(): void
    {
        $this->dispatch('backend/admin/system_config/edit/section/api_logger');
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        $html = (string) $response->getBody();

        self::assertStringContainsString('endpoint-selector-container', $html);
        self::assertGreaterThan(
            0,
            preg_match_all(
                '#<span class="endpoint-group-toggle" aria-hidden="true"><svg aria-hidden="true" focusable="false" '
                . 'class="endpoint-group-toggle__icon lucide lucide-chevron-down"#',
                $html
            )
        );
        self::assertStringNotContainsString('▼', $html);
    }
}
