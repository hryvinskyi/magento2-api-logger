<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Plugin;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\CorrelationScopeInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallCapture;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Webapi\Rest\Response as RestResponse;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Controller\Rest;
use PHPUnit\Framework\TestCase;

/**
 * A REST call dispatched through Magento's own REST controller is journaled
 *
 * The controller comes from the container of the REST area, so the logger runs only because the area's wiring puts
 * it on the controller. The request is the one the front controller hands over: its path still starts with the front
 * name and carries the store code, which Magento applies during dispatch.
 */
#[AppArea('webapi_rest')]
#[DbIsolation(false)]
class RestDispatchRecordingTest extends TestCase
{
    private const PATH = '/rest/default/V1/products';
    private const QUERY = 'searchCriteria%5BpageSize%5D=1';

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
        $this->activities = self::service(ActivityRepositoryInterface::class);
        self::service(RestCallCapture::class)->_resetState();
    }

    protected function tearDown(): void
    {
        if ($this->createdActivityIds !== []) {
            $this->activities->deleteByIds($this->createdActivityIds);
        }
        $this->createdActivityIds = [];
        self::service(RestCallCapture::class)->_resetState();
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', '["GET|/V1/products"]', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testAnAnonymousCallWithAStoreCodeIsJournaledAsDeniedWithItsMessageFilledIn(): void
    {
        $correlationScope = self::service(CorrelationScopeInterface::class);
        $operation = $correlationScope->begin();
        try {
            $response = self::service(Rest::class)->dispatch($this->anonymousGet());
        } finally {
            $correlationScope->end($operation);
        }

        self::assertInstanceOf(RestResponse::class, $response);
        self::assertTrue($response->isException(), 'An anonymous caller may not list products.');

        $activity = $this->onlyRestActivityOf($operation);
        self::assertSame('GET /V1/products', $activity->getAction());
        self::assertSame(Outcome::Denied, $activity->getOutcome());
        self::assertSame(
            "The consumer isn't authorized to access Magento_Catalog::products.",
            $activity->getMessage()
        );

        $activityId = $activity->getId();
        self::assertNotNull($activityId);
        $entry = self::service(LogEntryRepositoryInterface::class)->getByActivityId($activityId);
        self::assertSame('/default/V1/products?' . self::QUERY, $entry->getEndpoint());
        self::assertSame('GET', $entry->getMethod());
        self::assertSame(401, $entry->getResponseCode());
        self::assertSame($activity->getCorrelationId(), $entry->getCorrelationId());
    }

    /**
     * A GET without credentials, as the front controller receives it
     *
     * @return HttpRequest
     */
    private function anonymousGet(): HttpRequest
    {
        $uri = self::PATH . '?' . self::QUERY;
        $request = Bootstrap::getObjectManager()->create(HttpRequest::class);
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setRequestUri($uri);
        $request->setPathInfo(self::PATH);
        $request->setUri('http://localhost' . $uri);
        $request->setMethod(HttpRequest::METHOD_GET);

        return $request;
    }

    /**
     * The one activity of the REST channel journaled in the operation; remembered for deletion
     *
     * @param string $operation
     * @return ActivityInterface
     */
    private function onlyRestActivityOf(string $operation): ActivityInterface
    {
        $rest = [];
        foreach ($this->activities->getByCorrelationId($operation) as $activity) {
            $id = $activity->getId();
            if ($id !== null) {
                $this->createdActivityIds[] = $id;
            }
            if ($activity->getChannel() === RestCallRecorder::CHANNEL) {
                $rest[] = $activity;
            }
        }

        self::assertCount(1, $rest, 'The call is journaled once, on the REST channel.');

        return $rest[0];
    }

    /**
     * A service from the container of the current area, typed
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
