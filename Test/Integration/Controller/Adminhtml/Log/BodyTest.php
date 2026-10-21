<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\TestFramework\Fixture\DbIsolation;

/**
 * A page of a stored body, and the lines a search over the whole body matches
 */
#[DbIsolation(true)]
class BodyTest extends LogControllerTestCase
{
    private const RESPONSE_BODY = '{"items":[{"sku":"a"},{"sku":"b"}],"total_count":2}';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/body';

    public function testAPageOfTheResponseBodyComesWithItsPaging(): void
    {
        $entry = $this->seed('GET', '/default/V1/products', 200, null, self::RESPONSE_BODY);

        $this->dispatch(sprintf('backend/api_logger/log/body/id/%d/part/response/page/1', self::idOf($entry)));
        $data = $this->json();

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertSame(['lines', 'page', 'pages', 'totalLines', 'bytes', 'hits'], array_keys($data));
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['pages']);
        self::assertSame(11, $data['totalLines']);
        self::assertSame(strlen(self::RESPONSE_BODY), $data['bytes']);
        self::assertSame([], $data['hits']);
        self::assertIsArray($data['lines']);
        self::assertCount(11, $data['lines']);
        self::assertSame(['n' => 1, 'text' => '{'], $data['lines'][0]);
        self::assertSame(['n' => 4, 'text' => '            "sku": "a"'], $data['lines'][3]);
    }

    public function testASearchAnswersWithTheNumbersOfTheMatchingLines(): void
    {
        $entry = $this->seed('GET', '/default/V1/products', 200, null, self::RESPONSE_BODY);

        $this->httpRequest()->setParam('q', 'SKU');
        $this->dispatch(sprintf('backend/api_logger/log/body/id/%d/part/response', self::idOf($entry)));

        self::assertSame([4, 7], $this->json()['hits']);
    }

    public function testTheRequestPartPagesTheRequestBody(): void
    {
        $entry = $this->seed('POST', '/default/V1/products', 200, '{"product":{"sku":"new"}}', self::RESPONSE_BODY);

        $this->dispatch(sprintf('backend/api_logger/log/body/id/%d/part/request', self::idOf($entry)));
        $data = $this->json();

        self::assertSame(5, $data['totalLines']);
        self::assertSame(strlen('{"product":{"sku":"new"}}'), $data['bytes']);
    }

    public function testAnUnknownPartAnswersAJsonResponseWithNoBody(): void
    {
        $entry = $this->seed('GET', '/default/V1/products', 200, null, self::RESPONSE_BODY);

        $this->dispatch(sprintf('backend/api_logger/log/body/id/%d/part/headers', self::idOf($entry)));

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertSame('application/json', $this->header('Content-Type'));
        self::assertSame('', $this->body());
    }

    public function testAnUnknownCallAnswersNotFoundWithAnError(): void
    {
        $this->dispatch('backend/api_logger/log/body/id/987654321/part/response');

        self::assertSame(404, $this->httpResponse()->getHttpResponseCode());
        self::assertArrayHasKey('error', $this->json());
    }
}
