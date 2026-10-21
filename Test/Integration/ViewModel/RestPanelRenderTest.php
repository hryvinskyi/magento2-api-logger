<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\ViewModel;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Test\Integration\Fixture\RestCallSeeder;
use Magento\Framework\Url\ScopeInterface as UrlScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The REST panel on the activity detail page of a "rest" activity, rendered through the real admin layout
 *
 * The panel's script config is printed into an attribute escaped for HTML, so the page is parsed and the attribute
 * read decoded, as a browser reads it.
 */
#[DbIsolation(true)]
class RestPanelRenderTest extends AbstractBackendController
{
    private const HOST = 'api.example.test';
    private const SCRIPT = 'Hryvinskyi_ApiLogger/js/view/rest-panel';

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/view/id/1';

    /**
     * Stored endpoints as 2.0 writes them and as rows written before it read
     *
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'after the front name' => [
                '/default/V1/products?searchCriteria%5BpageSize%5D=5',
                '/rest/default/V1/products?searchCriteria%5BpageSize%5D=5',
            ],
            'written before 2.0' => [
                '/rest/default/V1/products',
                '/rest/default/V1/products',
            ],
        ];
    }

    #[DataProvider('endpoints')]
    public function testThePanelShowsTheCallWithItsUrlStatusAndMaskedSecrets(string $endpoint, string $path): void
    {
        $url = $this->secureBaseUrl() . $path;
        $entry = $this->seed($endpoint);

        $this->dispatch(sprintf('backend/activitylog/activity/view/id/%d', $this->activityIdOf($entry)));
        $xpath = $this->page();
        $panel = $this->single($xpath, '//section[contains(concat(" ", @class, " "), " rest-panel ")]');

        $config = json_decode($panel->getAttribute('data-mage-init'), true);
        self::assertIsArray($config);
        self::assertArrayHasKey(self::SCRIPT, $config);
        self::assertIsArray($config[self::SCRIPT]);
        self::assertIsString($config[self::SCRIPT]['bodyUrl'] ?? null);
        self::assertStringContainsString(
            sprintf('api_logger/log/body/id/%d/', (int) $entry->getEntityId()),
            $config[self::SCRIPT]['bodyUrl']
        );

        $shownUrl = trim($this->single($xpath, './/span[@data-role="url"]', $panel)->textContent);
        self::assertSame($url, $shownUrl, 'The URL must be the store\'s, never the stored host');
        self::assertSame(1, substr_count($shownUrl, '/rest/'));
        self::assertSame(
            $url,
            trim($this->single($xpath, './/span[@data-role="replay-target"]', $panel)->textContent),
            'The replay dialog must show the URL the replay is sent to'
        );
        $labels = $config[self::SCRIPT]['labels'] ?? null;
        self::assertIsArray($labels);
        self::assertSame('Identical in the first 256 KB', $labels['identicalInFirst'] ?? null);

        self::assertSame(
            '401 Unauthorized',
            trim($this->single($xpath, './/span[contains(@class, "rest-status")]', $panel)->textContent)
        );

        $authorization = $this->single(
            $xpath,
            './/div[@data-tab="request"]//table[@class="rest-pairs"]//tr[th="Authorization"]/td',
            $panel
        );
        self::assertStringContainsString('Bearer ' . SanitizerInterface::MASK, $authorization->textContent);
        self::assertStringContainsString('masked', $authorization->textContent);
    }

    /**
     * The secure base web URL of the default store view, which a call stored without a store is pointed at
     *
     * @return string
     */
    private function secureBaseUrl(): string
    {
        $storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        self::assertInstanceOf(StoreManagerInterface::class, $storeManager);
        $store = $storeManager->getDefaultStoreView();
        self::assertInstanceOf(UrlScopeInterface::class, $store);

        return rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true), '/');
    }

    /**
     * Store a failed call whose credential was masked when it was recorded
     *
     * @param string $endpoint
     * @return LogEntryInterface
     */
    private function seed(string $endpoint): LogEntryInterface
    {
        $seeder = Bootstrap::getObjectManager()->get(RestCallSeeder::class);
        self::assertInstanceOf(RestCallSeeder::class, $seeder);

        return $seeder->seed(
            'GET',
            $endpoint,
            401,
            null,
            '{"message":"The consumer isn\'t authorized to access Magento_Catalog::products."}',
            [
                'Host' => self::HOST,
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . SanitizerInterface::MASK,
            ],
            ['Content-Type' => 'application/json; charset=utf-8']
        );
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
     * The rendered page, parsed
     *
     * @return \DOMXPath
     */
    private function page(): \DOMXPath
    {
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);
        $html = (string) $response->getBody();
        self::assertNotSame('', $html);

        $document = new \DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    /**
     * The one element an expression finds
     *
     * @param \DOMXPath $xpath
     * @param string $expression
     * @param \DOMNode|null $context
     * @return \DOMElement
     */
    private function single(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): \DOMElement
    {
        $nodes = $xpath->query($expression, $context);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);
        self::assertSame(1, $nodes->length, 'Expected one element for ' . $expression);
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);

        return $node;
    }
}
