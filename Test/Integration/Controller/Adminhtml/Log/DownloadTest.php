<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * A stored body sent whole as a file
 */
#[DbIsolation(true)]
class DownloadTest extends LogControllerTestCase
{
    private const RESPONSE_BODY = '{"items":[{"sku":"a"}],"total_count":1}';
    private const FORM_BODY = 'sku=a&qty=1';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/download';

    public function testAJsonBodyIsSentAsAJsonAttachment(): void
    {
        $entry = $this->seed('GET', '/default/V1/products', 200, self::FORM_BODY, self::RESPONSE_BODY);
        $id = self::idOf($entry);

        $content = $this->download(sprintf('backend/api_logger/log/download/id/%d/part/response', $id));

        self::assertSame(200, $this->httpResponse()->getHttpResponseCode());
        self::assertSame(self::RESPONSE_BODY, $content);
        self::assertSame(
            sprintf('attachment; filename="rest-call-%d-response.json"', $id),
            $this->header('Content-Disposition')
        );
        self::assertSame('application/json', $this->header('Content-Type'));
    }

    public function testNothingIsWrittenToDiskAndNoCopyMayBeKept(): void
    {
        $entry = $this->seed('GET', '/default/V1/products', 200, self::FORM_BODY, self::RESPONSE_BODY);
        $id = self::idOf($entry);
        $filesystem = Bootstrap::getObjectManager()->get(Filesystem::class);
        self::assertInstanceOf(Filesystem::class, $filesystem);
        $var = $filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $before = self::callFiles($var->read(), $id);

        $content = $this->download(sprintf('backend/api_logger/log/download/id/%d/part/response', $id));

        self::assertSame(self::RESPONSE_BODY, $content);
        self::assertSame((string) strlen(self::RESPONSE_BODY), $this->header('Content-Length'));
        self::assertStringContainsString('no-store', (string) $this->header('Cache-Control'));
        self::assertSame('no-cache', $this->header('Pragma'));
        self::assertSame(
            $before,
            self::callFiles($var->read(), $id),
            'The download left a file in var/'
        );
    }

    /**
     * Entries of var/ named after the call
     *
     * @param array<mixed> $paths
     * @param int $id
     * @return list<string>
     */
    private static function callFiles(array $paths, int $id): array
    {
        return array_values(array_filter(
            $paths,
            static fn (mixed $path): bool => is_string($path) && str_contains($path, sprintf('rest-call-%d', $id))
        ));
    }

    public function testABodyThatIsNotJsonIsSentAsText(): void
    {
        $entry = $this->seed('POST', '/default/V1/products', 200, self::FORM_BODY, self::RESPONSE_BODY);
        $id = self::idOf($entry);

        $content = $this->download(sprintf('backend/api_logger/log/download/id/%d/part/request', $id));

        self::assertSame(self::FORM_BODY, $content);
        self::assertSame(
            sprintf('attachment; filename="rest-call-%d-request.txt"', $id),
            $this->header('Content-Disposition')
        );
        self::assertSame('text/plain', $this->header('Content-Type'));
    }

    public function testAnUnknownPartFallsBackToTheResponseBody(): void
    {
        $entry = $this->seed('POST', '/default/V1/products', 200, self::FORM_BODY, self::RESPONSE_BODY);
        $id = self::idOf($entry);

        $content = $this->download(sprintf('backend/api_logger/log/download/id/%d/part/headers', $id));

        self::assertSame(self::RESPONSE_BODY, $content);
        self::assertSame(
            sprintf('attachment; filename="rest-call-%d-response.json"', $id),
            $this->header('Content-Disposition')
        );
    }
}
