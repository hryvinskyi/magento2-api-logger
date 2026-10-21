<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Model\Recording;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\CorrelationScopeInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\Replay\ReplayService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Records calls through the real recorder, journal and sanitizer, and reads back what was stored
 *
 * Each test records inside an operation of its own, so the activities it created are found by that operation's
 * correlation id and deleted afterwards; deleting an activity deletes its entry with it.
 */
#[DbIsolation(false)]
class RestCallRecorderTest extends TestCase
{
    private const ADMIN_TOKEN = '/default/V1/integration/admin/token';
    private const ISSUED_TOKEN = 'k9x2plq7rvzt3mw8ab4cdefgh1jn5s6u';
    private const BEARER_TOKEN = 'b7d1e0f2a9c84d3e6f5a2b1c0d9e8f7a';
    private const PASSWORD = 'Plain-Text-Pa55';
    private const RESET_LINK_TOKEN = 'f3c9a1e07b2d4c58a6e1d0b9c8f7e6d5';
    private const RESET_PATTERN = 'GET|/V1/customers/:customerId/password/resetLinkToken/:resetPasswordLinkToken';

    /**
     * @var RestCallRecorder
     */
    private RestCallRecorder $recorder;

    /**
     * @var CorrelationScopeInterface
     */
    private CorrelationScopeInterface $correlationScope;

    /**
     * @var ActivityRepositoryInterface
     */
    private ActivityRepositoryInterface $activities;

    /**
     * @var list<int>
     */
    private array $createdActivityIds = [];

    protected function setUp(): void
    {
        $this->recorder = self::service(RestCallRecorder::class);
        $this->correlationScope = self::service(CorrelationScopeInterface::class);
        $this->activities = self::service(ActivityRepositoryInterface::class);

        $sanitizer = self::service(SanitizerInterface::class);
        if ($sanitizer instanceof ResetAfterRequestInterface) {
            $sanitizer->_resetState();
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdActivityIds !== []) {
            $this->activities->deleteByIds($this->createdActivityIds);
        }
        $this->createdActivityIds = [];
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testACallIsJournaledAsOneRestActivityWithItsEntryLinkedToIt(): void
    {
        $activity = $this->recordOne(
            $this->call('POST', self::ADMIN_TOKEN, [], '{"username":"admin"}'),
            $this->response(200, '"' . self::ISSUED_TOKEN . '"')
        );

        self::assertSame(RestCallRecorder::CHANNEL, $activity->getChannel());
        self::assertSame('POST /V1/integration/admin/token', $activity->getAction());
        self::assertSame(Outcome::Success, $activity->getOutcome());
        self::assertNull($activity->getMessage());

        $entry = $this->entryOf($activity);
        self::assertSame($activity->getId(), $entry->getActivityId());
        self::assertSame($activity->getCorrelationId(), $entry->getCorrelationId());
        self::assertSame(self::ADMIN_TOKEN, $entry->getEndpoint());
        self::assertSame('POST', $entry->getMethod());
        self::assertSame(200, $entry->getResponseCode());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_request_headers', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_request_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testSecretsOfTheRequestAreStoredMasked(): void
    {
        $activity = $this->recordOne(
            $this->call(
                'POST',
                '/default/V1/customers',
                ['Authorization' => 'Bearer ' . self::BEARER_TOKEN, 'Content-Type' => 'application/json'],
                '{"customer":{"email":"jane@example.com"},"password":"' . self::PASSWORD . '"}'
            ),
            $this->response(200, '{"id":42,"email":"jane@example.com"}')
        );

        $entry = $this->entryOf($activity);

        $body = json_decode((string) $entry->getRequestBody(), true);
        self::assertIsArray($body);
        self::assertSame(SanitizerInterface::MASK, $body['password'] ?? null);
        self::assertSame(['email' => 'jane@example.com'], $body['customer'] ?? null);
        self::assertStringNotContainsString(self::PASSWORD, (string) $entry->getRequestBody());

        $headers = json_decode((string) $entry->getRequestHeaders(), true);
        self::assertIsArray($headers);
        self::assertSame(
            'Bearer ' . SanitizerInterface::MASK,
            $headers['Authorization'] ?? null,
            'A credential keeps its scheme and loses its value.'
        );
        self::assertSame('application/json', $headers['Content-Type'] ?? null);
        self::assertStringNotContainsString(self::BEARER_TOKEN, (string) $entry->getRequestHeaders());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_request_body', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testTheResponseOfATokenEndpointIsStoredAsTheMaskNeverTheToken(): void
    {
        $activity = $this->recordOne(
            $this->call('POST', self::ADMIN_TOKEN, [], '{"username":"admin","password":"' . self::PASSWORD . '"}'),
            $this->response(200, '"' . self::ISSUED_TOKEN . '"')
        );

        $entry = $this->entryOf($activity);

        self::assertSame(SanitizerInterface::MASK, $entry->getResponseBody());
        self::assertStringNotContainsString(self::ISSUED_TOKEN, (string) $entry->getResponseBody());
        self::assertStringNotContainsString(self::PASSWORD, (string) $entry->getRequestBody());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testARefusedCallIsDeniedWithTheParametersOfItsErrorMessageFilledIn(): void
    {
        $activity = $this->recordOne(
            $this->call('GET', '/default/V1/products?searchCriteria%5BpageSize%5D=1', [], null),
            $this->response(
                401,
                '{"message":"The consumer isn\'t authorized to access %resources.",'
                . '"parameters":{"resources":"Magento_Catalog::products"}}',
                true
            )
        );

        self::assertSame('GET /V1/products', $activity->getAction());
        self::assertSame(Outcome::Denied, $activity->getOutcome());
        self::assertSame(
            "The consumer isn't authorized to access Magento_Catalog::products.",
            $activity->getMessage()
        );
        self::assertSame(401, $this->entryOf($activity)->getResponseCode());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_request_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testTheSecretsOfAnXmlRequestBodyAreStoredMasked(): void
    {
        $activity = $this->recordOne(
            $this->call(
                'POST',
                '/default/V1/customers',
                ['Content-Type' => 'application/xml'],
                '<?xml version="1.0"?><customer><email>jane@example.com</email>'
                . '<password>' . self::PASSWORD . '</password></customer>'
            ),
            $this->response(200, '{"id":42}')
        );

        $body = (string) $this->entryOf($activity)->getRequestBody();
        self::assertStringNotContainsString(self::PASSWORD, $body);
        self::assertStringContainsString('<password>' . SanitizerInterface::MASK . '</password>', $body);
        self::assertStringContainsString('<email>jane@example.com</email>', $body);
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_request_headers', '1', ScopeInterface::SCOPE_STORE)]
    public function testCookieAndApiKeyRequestHeadersAreStoredMasked(): void
    {
        $activity = $this->recordOne(
            $this->call(
                'GET',
                '/default/V1/products',
                ['Cookie' => 'PHPSESSID=s3ss10n1d', 'X-Api-Key' => 'ak-7d1e0f2a9c84', 'Accept' => 'application/json'],
                null
            ),
            $this->response(200, '{"items":[]}')
        );

        $stored = (string) $this->entryOf($activity)->getRequestHeaders();
        $headers = json_decode($stored, true);
        self::assertIsArray($headers);
        self::assertSame(SanitizerInterface::MASK, $headers['Cookie'] ?? null);
        self::assertSame(SanitizerInterface::MASK, $headers['X-Api-Key'] ?? null);
        self::assertSame('application/json', $headers['Accept'] ?? null);
        self::assertStringNotContainsString('s3ss10n1d', $stored);
        self::assertStringNotContainsString('ak-7d1e0f2a9c84', $stored);
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testASecretRouteParameterIsMaskedInTheEntryAndTheActionAndTheCallIsNotReplayed(): void
    {
        $activity = $this->recordOne(
            $this->call(
                'GET',
                '/default/V1/customers/42/password/resetLinkToken/' . self::RESET_LINK_TOKEN,
                [],
                null,
                self::RESET_PATTERN
            ),
            $this->response(200, 'true')
        );

        self::assertSame(
            'GET /V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK,
            $activity->getAction()
        );
        $entry = $this->entryOf($activity);
        self::assertSame(
            '/default/V1/customers/42/password/resetLinkToken/' . SanitizerInterface::MASK,
            $entry->getEndpoint()
        );

        $replay = self::service(ReplayService::class);
        self::assertFalse($replay->canReplay($entry));
        $this->expectException(LocalizedException::class);
        $replay->replay($entry);
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testTheValueOfASecretQueryKeyIsMaskedInTheStoredQuery(): void
    {
        $activity = $this->recordOne(
            $this->call('GET', '/default/V1/products?searchCriteria%5BpageSize%5D=1&access_token=x', [], null),
            $this->response(200, '{"items":[]}')
        );

        self::assertSame(
            '/default/V1/products?searchCriteria%5BpageSize%5D=1&access_token=' . SanitizerInterface::MASK,
            $this->entryOf($activity)->getEndpoint()
        );
        self::assertSame('GET /V1/products', $activity->getAction());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testAQueryLongerThanThePathColumnIsStoredWhole(): void
    {
        $query = 'searchCriteria%5BfilterGroups%5D%5B0%5D%5Bfilters%5D%5B0%5D%5Bvalue%5D=' . str_repeat('sku-', 200);
        self::assertGreaterThan(512, strlen($query));

        $activity = $this->recordOne(
            $this->call('GET', '/default/V1/products?' . $query, [], null),
            $this->response(200, '{"items":[]}')
        );

        self::assertSame('/default/V1/products?' . $query, $this->entryOf($activity)->getEndpoint());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    public function testAPathLongerThanItsColumnIsCutToTheColumn(): void
    {
        $path = '/default/V1/products/' . str_repeat('a', 600);

        $activity = $this->recordOne(
            $this->call('GET', $path . '?a=1', [], null),
            $this->response(404, '{"message":"No such product."}', true)
        );

        self::assertSame(mb_substr($path, 0, 512) . '?a=1', $this->entryOf($activity)->getEndpoint());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testTheResponseOfATokenRouteCalledWithATrailingSlashIsStoredAsTheMask(): void
    {
        $activity = $this->recordOne(
            $this->call('POST', self::ADMIN_TOKEN . '/', [], '{"username":"admin"}'),
            $this->response(200, '"' . self::ISSUED_TOKEN . '"')
        );

        self::assertSame('POST /V1/integration/admin/token', $activity->getAction());
        self::assertSame(SanitizerInterface::MASK, $this->entryOf($activity)->getResponseBody());
    }

    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testTheBareJsonStringResponseOfASecondFactorAuthenticateRouteIsStoredAsTheMask(): void
    {
        $activity = $this->recordOne(
            $this->call('POST', '/V1/tfa/provider/google/authenticate', [], '{"username":"admin"}'),
            $this->response(200, '"' . self::ISSUED_TOKEN . '"')
        );

        self::assertSame(SanitizerInterface::MASK, $this->entryOf($activity)->getResponseBody());
    }

    /**
     * Record one call inside an operation of its own and return the one activity it journaled
     *
     * @param array{
     *     endpoint: string,
     *     method: string,
     *     pattern: string|null,
     *     headers: array<string, string|list<string>>,
     *     body: string|null,
     *     startedAtMs: int
     * } $call
     * @param array{code:int,headers:array<string,string|list<string>>,body:string|null,exception:bool} $response
     * @return ActivityInterface
     */
    private function recordOne(array $call, array $response): ActivityInterface
    {
        $operation = $this->correlationScope->begin();
        try {
            $this->recorder->record($call, $response);
        } finally {
            $this->correlationScope->end($operation);
        }

        $journaled = $this->activities->getByCorrelationId($operation);
        foreach ($journaled as $activity) {
            $id = $activity->getId();
            if ($id !== null) {
                $this->createdActivityIds[] = $id;
            }
        }

        self::assertCount(1, $journaled, 'One call is one activity.');

        return $journaled[0];
    }

    /**
     * The stored entry of an activity
     *
     * @param ActivityInterface $activity
     * @return LogEntryInterface
     */
    private function entryOf(ActivityInterface $activity): LogEntryInterface
    {
        $activityId = $activity->getId();
        self::assertNotNull($activityId);

        return self::service(LogEntryRepositoryInterface::class)->getByActivityId($activityId);
    }

    /**
     * A call as the plugin captures it at dispatch
     *
     * @param string $method
     * @param string $endpoint
     * @param array<string, string|list<string>> $headers
     * @param string|null $body
     * @param string|null $pattern
     * @return array{
     *     endpoint: string,
     *     method: string,
     *     pattern: string|null,
     *     headers: array<string, string|list<string>>,
     *     body: string|null,
     *     startedAtMs: int
     * }
     */
    private function call(
        string $method,
        string $endpoint,
        array $headers,
        ?string $body,
        ?string $pattern = null
    ): array {
        return [
            'endpoint' => $endpoint,
            'method' => $method,
            'pattern' => $pattern,
            'headers' => $headers + ['User-Agent' => 'integration-test'],
            'body' => $body,
            'startedAtMs' => self::service(MillisecondClockInterface::class)->nowMs(),
        ];
    }

    /**
     * A response as the plugin reads it after dispatch
     *
     * @param int $code
     * @param string|null $body
     * @param bool $exception
     * @return array{code:int,headers:array<string,string|list<string>>,body:string|null,exception:bool}
     */
    private function response(int $code, ?string $body, bool $exception = false): array
    {
        return [
            'code' => $code,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => $body,
            'exception' => $exception,
        ];
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
