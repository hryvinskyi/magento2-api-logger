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
use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Api\LogEntryRepositoryInterface;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallCapture;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Plugin\WebApi\RestInterceptor;
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
 * A REST dispatch that runs inside another one is recorded with its own response, and so is the outer one
 *
 * The plugin is driven the way Magento drives it for nested dispatches: the outer call is dispatched, the inner call
 * is dispatched and returns, then the outer call returns.
 */
#[AppArea('webapi_rest')]
#[DbIsolation(false)]
class NestedDispatchRecordingTest extends TestCase
{
    private const SELECTED = '["GET|/V1/products","GET|/V1/orders"]';
    private const OUTER_BODY = '{"items":[{"sku":"outer-call"}]}';
    private const INNER_BODY = '{"items":[{"increment_id":"inner-call"}]}';

    /**
     * @var RestInterceptor
     */
    private RestInterceptor $interceptor;

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
        $this->interceptor = self::service(RestInterceptor::class);
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
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED, ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testTwoLoggedCallsAreEachRecordedWithTheirOwnResponse(): void
    {
        $entries = $this->dispatchNested('/rest/default/V1/orders');

        self::assertCount(2, $entries, 'Each dispatch is one call.');
        self::assertSame(200, $entries['GET /V1/products']->getResponseCode());
        self::assertSame(self::OUTER_BODY, $entries['GET /V1/products']->getResponseBody());
        self::assertSame(404, $entries['GET /V1/orders']->getResponseCode());
        self::assertSame(self::INNER_BODY, $entries['GET /V1/orders']->getResponseBody());
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED, ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_response_codes', '', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/log_response_body', '1', ScopeInterface::SCOPE_STORE)]
    public function testAnInnerCallThatIsNotLoggedLeavesTheOuterCallItsOwnResponse(): void
    {
        $entries = $this->dispatchNested('/rest/default/V1/store/storeViews');

        self::assertSame(['GET /V1/products'], array_keys($entries), 'Only the outer call is logged.');
        self::assertSame(200, $entries['GET /V1/products']->getResponseCode());
        self::assertSame(self::OUTER_BODY, $entries['GET /V1/products']->getResponseBody());
    }

    /**
     * Dispatch the logged outer call with an inner call inside it; the entries of the REST activities journaled, by
     * action
     *
     * @param string $innerPath
     * @return array<string, LogEntryInterface>
     */
    private function dispatchNested(string $innerPath): array
    {
        $rest = $this->createMock(Rest::class);
        $correlationScope = self::service(CorrelationScopeInterface::class);
        $operation = $correlationScope->begin();
        try {
            $this->interceptor->beforeDispatch($rest, $this->get('/rest/default/V1/products'));
            $this->interceptor->beforeDispatch($rest, $this->get($innerPath));
            $this->interceptor->afterDispatch($rest, $this->response(404, self::INNER_BODY));
            $this->interceptor->afterDispatch($rest, $this->response(200, self::OUTER_BODY));
        } finally {
            $correlationScope->end($operation);
        }

        $entries = [];
        foreach ($this->activities->getByCorrelationId($operation) as $activity) {
            $id = $activity->getId();
            if ($id === null) {
                continue;
            }
            $this->createdActivityIds[] = $id;
            if ($activity->getChannel() === RestCallRecorder::CHANNEL) {
                $entries[$activity->getAction()] = self::service(LogEntryRepositoryInterface::class)
                    ->getByActivityId($id);
            }
        }

        return $entries;
    }

    /**
     * A GET request as the front controller hands it to the REST controller
     *
     * @param string $path
     * @return HttpRequest
     */
    private function get(string $path): HttpRequest
    {
        $request = Bootstrap::getObjectManager()->create(HttpRequest::class);
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setRequestUri($path);
        $request->setPathInfo($path);
        $request->setUri('http://localhost' . $path);
        $request->setMethod(HttpRequest::METHOD_GET);

        return $request;
    }

    /**
     * A REST response as a dispatch returns it
     *
     * @param int $code
     * @param string $body
     * @return RestResponse
     */
    private function response(int $code, string $body): RestResponse
    {
        $response = Bootstrap::getObjectManager()->create(RestResponse::class);
        self::assertInstanceOf(RestResponse::class, $response);
        $response->setHttpResponseCode($code);
        $response->setBody($body);

        return $response;
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
