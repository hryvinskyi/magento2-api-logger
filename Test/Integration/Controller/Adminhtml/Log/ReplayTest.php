<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayService;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayTransportInterface;
use Hryvinskyi\ApiLogger\Test\Integration\Fake\RecordingReplayTransport;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Replaying a logged call: it goes as the admin who replays it, to this store's secure base URL, without the stored
 * secrets, and never to a destination taken from what was stored with the call
 *
 * The transport is a recording fake, so nothing leaves the test. Magento resolves an interface to its preference
 * before it looks up shared instances, so the fake is shared under the class the preference names, and the shared
 * replay service is dropped so the next one is built with the fake. Seeding a call resolves the user context outside
 * the admin area, where it knows no admin session, so that shared instance is dropped before the admin request too.
 */
#[DbIsolation(true)]
class ReplayTest extends LogControllerTestCase
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::replay';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/replay';

    /**
     * @var string|null
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    private const SECURE_BASE_URL = 'https://secure.example.test/';

    /**
     * @var RecordingReplayTransport
     */
    private RecordingReplayTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new RecordingReplayTransport(201, '{"id":42}');
        $objectManager = self::testObjectManager();
        $objectManager->removeSharedInstance(ReplayService::class);
        $objectManager->addSharedInstance($this->transport, ReplayTransportInterface::class, true);
    }

    protected function tearDown(): void
    {
        self::resetDefaultStoreUrls();
        $objectManager = self::testObjectManager();
        $objectManager->removeSharedInstance(ReplayTransportInterface::class, true);
        $objectManager->removeSharedInstance(ReplayService::class);
        parent::tearDown();
    }

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
    public function testTheCallGoesAgainAsTheAdminWithoutItsStoredSecrets(string $endpoint, string $path): void
    {
        $url = self::secureBaseUrl() . $path;
        $entry = $this->seed('PUT', $endpoint, 200, '{"product":{"sku":"a"}}', '{"id":41}', [
            'Host' => self::HOST,
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . SanitizerInterface::MASK,
            'X-Api-Key' => SanitizerInterface::MASK,
            'Cookie' => 'admin=abc123',
            'Accept-Encoding' => 'gzip',
            'Content-Length' => '24',
            'Connection' => 'keep-alive',
        ]);

        $this->replay($entry);
        $data = $this->json();

        $sent = $this->transport->sent();
        self::assertCount(1, $sent, 'The replay sent nothing: ' . $this->body());
        self::assertSame('PUT', $sent[0]['method']);
        self::assertSame($url, $sent[0]['url']);
        self::assertSame(1, substr_count($sent[0]['url'], '/rest/'));
        self::assertSame('{"product":{"sku":"a"}}', $sent[0]['body']);
        self::assertSame('application/json', $sent[0]['headers']['Accept'] ?? null);
        self::assertArrayNotHasKey('X-Api-Key', $sent[0]['headers']);
        foreach (['Host', 'Cookie', 'Accept-Encoding', 'Content-Length', 'Connection'] as $name) {
            self::assertArrayNotHasKey($name, $sent[0]['headers'], $name . ' was forwarded');
        }
        foreach ($sent[0]['headers'] as $name => $value) {
            self::assertStringNotContainsString(SanitizerInterface::MASK, $value, $name . ' went masked');
        }
        $authorization = $sent[0]['headers']['Authorization'] ?? '';
        self::assertMatchesRegularExpression('/^Bearer \S{20,}$/', $authorization);

        self::assertIsArray($data['original'] ?? null, 'No original in: ' . $this->body());
        self::assertIsArray($data['replay'] ?? null);
        self::assertSame(200, $data['original']['status']);
        self::assertSame(201, $data['replay']['status']);
        self::assertSame($url, $data['replay']['url']);
        self::assertSame("{\n    \"id\": 42\n}", $data['replay']['body']);
    }

    /**
     * A stored Host and X-Forwarded-Proto came from the caller: a replay, which carries an admin token, must go to the
     * store's own secure base URL whatever they say
     */
    #[Config('web/secure/base_url', self::SECURE_BASE_URL, ScopeInterface::SCOPE_STORE, 'default')]
    public function testAForgedHostAndPlainHttpProtoDoNotPointTheReplayAnywhere(): void
    {
        self::resetDefaultStoreUrls();
        $entry = $this->seed('GET', '/default/V1/products/a', 200, null, '{"sku":"a"}', [
            'Host' => 'evil.example.test',
            'X-Forwarded-Proto' => 'http',
            'X-Forwarded-Host' => 'evil.example.test',
        ]);

        $this->replay($entry);

        $sent = $this->transport->sent();
        self::assertCount(1, $sent, 'The replay sent nothing: ' . $this->body());
        self::assertSame(self::SECURE_BASE_URL . 'rest/default/V1/products/a', $sent[0]['url']);
        self::assertStringNotContainsString('evil', $sent[0]['url']);
        self::assertMatchesRegularExpression('/^Bearer \S{20,}$/', $sent[0]['headers']['Authorization'] ?? '');
        $replay = $this->json()['replay'] ?? null;
        self::assertIsArray($replay, 'No replay in: ' . $this->body());
        self::assertSame(self::SECURE_BASE_URL . 'rest/default/V1/products/a', $replay['url'] ?? null);
    }

    public function testACallWhoseUrlCarriesAMaskedSecretIsRefusedAndNothingIsSent(): void
    {
        $entry = $this->seed(
            'POST',
            '/default/V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK,
            200
        );

        $this->replay($entry);

        self::assertSame([], $this->transport->sent());
        $error = $this->json()['error'] ?? null;
        self::assertIsString($error);
        self::assertStringContainsString('masked', $error);
    }

    public function testAnAnswerThatIsNotValidUtf8IsStillAnsweredAsJson(): void
    {
        $this->transport = new RecordingReplayTransport(200, "caf\xC3 \xFF ok");
        self::testObjectManager()->addSharedInstance($this->transport, ReplayTransportInterface::class, true);
        $entry = $this->seed('GET', '/default/V1/products/a');

        $this->replay($entry);
        $data = $this->json();

        self::assertCount(1, $this->transport->sent());
        self::assertIsArray($data['replay'] ?? null, 'No replay in: ' . $this->body());
        self::assertIsString($data['replay']['body']);
        self::assertTrue(mb_check_encoding($data['replay']['body'], 'UTF-8'));
        self::assertStringEndsWith(' ok', $data['replay']['body']);
    }

    public function testAnUnknownCallSendsNothingAndAnswersWithAnError(): void
    {
        $this->httpRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/api_logger/log/replay/id/987654321');

        self::assertArrayHasKey('error', $this->json());
        self::assertSame([], $this->transport->sent());
    }

    public function testAGetRequestIsNotAnswered(): void
    {
        $entry = $this->seed('GET', '/default/V1/products');

        $this->httpRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch(sprintf('backend/api_logger/log/replay/id/%d', self::idOf($entry)));

        self::assertSame(404, $this->httpResponse()->getHttpResponseCode());
        self::assertNull(json_decode($this->body(), true));
        self::assertSame([], $this->transport->sent());
    }

    /**
     * Post a replay of the call as the logged-in admin
     *
     * @param LogEntryInterface $entry
     * @return void
     */
    private function replay(LogEntryInterface $entry): void
    {
        self::testObjectManager()->removeSharedInstance(UserContextInterface::class, true);
        $this->httpRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch(sprintf('backend/api_logger/log/replay/id/%d', self::idOf($entry)));
    }

    /**
     * Forget the base URLs the default store view worked out, so a configured one is read again
     *
     * @return void
     */
    private static function resetDefaultStoreUrls(): void
    {
        $store = self::service(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertInstanceOf(ResetAfterRequestInterface::class, $store);
        $store->_resetState();
    }

    /**
     * @return ObjectManager
     */
    private static function testObjectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
