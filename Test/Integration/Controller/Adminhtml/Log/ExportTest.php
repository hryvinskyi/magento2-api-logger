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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A logged call sent as a file in each format the export pool offers
 */
#[DbIsolation(true)]
class ExportTest extends LogControllerTestCase
{
    private const ENDPOINT = '/default/V1/products?searchCriteria%5BpageSize%5D=5';
    private const PATH = '/rest/default/V1/products?searchCriteria%5BpageSize%5D=5';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/export';

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function formats(): array
    {
        return [
            'HAR' => ['har', 'har', 'application/json', '"log"'],
            'raw HTTP' => [
                'http',
                'txt',
                'text/plain',
                'GET /rest/default/V1/products?searchCriteria%5BpageSize%5D=5 HTTP/1.1',
            ],
            'cURL' => ['curl', 'sh', 'text/plain', 'curl -X GET'],
        ];
    }

    #[DataProvider('formats')]
    public function testEachFormatIsSentAsAnAttachment(
        string $format,
        string $extension,
        string $contentType,
        string $expectedContent
    ): void {
        $entry = $this->seed('GET', self::ENDPOINT, 200, null, '{"items":[]}');

        $content = $this->download(sprintf(
            'backend/api_logger/log/export/id/%d/format/%s',
            self::idOf($entry),
            $format
        ));

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertSame(
            sprintf('attachment; filename="rest-call-%d.%s"', self::idOf($entry), $extension),
            $this->header('Content-Disposition')
        );
        self::assertSame($contentType, $this->header('Content-Type'));
        self::assertSame((string) strlen($content), $this->header('Content-Length'));
        self::assertStringContainsString('no-store', (string) $this->header('Cache-Control'));
        self::assertStringContainsString($expectedContent, $content);
    }

    public function testTheHarFileCarriesTheStoresUrlNotTheStoredHostAndBothBodies(): void
    {
        $entry = $this->seed('POST', self::ENDPOINT, 201, '{"product":{"sku":"a"}}', '{"id":7}', [
            'Host' => 'evil.example.test',
            'X-Forwarded-Proto' => 'http',
        ]);

        $har = json_decode(
            $this->download(sprintf('backend/api_logger/log/export/id/%d/format/har', self::idOf($entry))),
            true
        );

        self::assertIsArray($har);
        self::assertIsArray($har['log']);
        self::assertIsArray($har['log']['entries']);
        self::assertIsArray($har['log']['entries'][0]);
        $harEntry = $har['log']['entries'][0];
        self::assertIsArray($harEntry['request']);
        self::assertIsArray($harEntry['response']);
        self::assertSame('POST', $harEntry['request']['method']);
        self::assertSame(self::secureBaseUrl() . self::PATH, $harEntry['request']['url']);
        self::assertIsArray($harEntry['request']['postData']);
        self::assertSame('{"product":{"sku":"a"}}', $harEntry['request']['postData']['text']);
        self::assertSame(201, $harEntry['response']['status']);
        self::assertIsArray($harEntry['response']['content']);
        self::assertSame('{"id":7}', $harEntry['response']['content']['text']);
    }

    public function testAnUnknownFormatGoesBackToTheGridWithAnError(): void
    {
        $entry = $this->seed('GET', self::ENDPOINT);

        $this->dispatch(sprintf('backend/api_logger/log/export/id/%d/format/postman', self::idOf($entry)));

        self::assertSame(302, $this->httpResponse()->getHttpResponseCode());
        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo(['This export format is not available.']),
            MessageInterface::TYPE_ERROR
        );
    }

    public function testAnUnknownCallGoesBackToTheGridWithAnError(): void
    {
        $this->dispatch('backend/api_logger/log/export/id/987654321/format/har');

        $this->assertRedirect(self::stringContains('api_logger/log/index'));
        $this->assertSessionMessages(
            self::equalTo(['The call is no longer logged.']),
            MessageInterface::TYPE_ERROR
        );
    }
}
