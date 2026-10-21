<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Plugin;

use Hryvinskyi\ApiLogger\Model\Recording\RestCallCapture;
use Hryvinskyi\ApiLogger\Plugin\WebApi\RestInterceptor;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Webapi\Controller\Rest;
use PHPUnit\Framework\TestCase;

/**
 * A REST call is captured with its path after the front name, as Magento hands the request to the REST controller
 *
 * The request is the one the front controller receives: its path still starts with the front name, and the store
 * code of the URL has not been applied yet.
 */
class RestEndpointCaptureTest extends TestCase
{
    private const SELECTED_PRODUCTS = '["GET|/V1/products"]';
    private const SELECTED_PRODUCT_AND_ORDERS = '["GET|/V1/orders","GET|/V1/products/:sku"]';

    /**
     * @var RestInterceptor
     */
    private RestInterceptor $interceptor;

    /**
     * @var RestCallCapture
     */
    private RestCallCapture $capture;

    protected function setUp(): void
    {
        $this->interceptor = self::service(RestInterceptor::class);
        $this->capture = self::service(RestCallCapture::class);
        $this->capture->_resetState();
    }

    protected function tearDown(): void
    {
        $this->capture->_resetState();
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED_PRODUCTS, ScopeInterface::SCOPE_STORE)]
    public function testACallWithAStoreCodeIsCapturedWithoutTheFrontNameAndWithItsQueryAsSent(): void
    {
        $this->dispatch('/rest/default/V1/products', 'searchCriteria%5BpageSize%5D=1');

        self::assertSame('/default/V1/products?searchCriteria%5BpageSize%5D=1', $this->capturedEndpoint());
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED_PRODUCTS, ScopeInterface::SCOPE_STORE)]
    public function testACallWithoutAStoreCodeIsCapturedWithoutTheFrontName(): void
    {
        $this->dispatch('/rest/V1/products');

        self::assertSame('/V1/products', $this->capturedEndpoint());
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED_PRODUCTS, ScopeInterface::SCOPE_STORE)]
    public function testACallToAnEndpointThatIsNotSelectedIsNotCaptured(): void
    {
        $this->dispatch('/rest/default/V1/orders');

        self::assertNull($this->capture->pop());
    }

    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE)]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED_PRODUCT_AND_ORDERS, ScopeInterface::SCOPE_STORE)]
    public function testACallIsCapturedWithTheSelectedPatternItMatched(): void
    {
        $this->dispatch('/rest/default/V1/products/24-MB01');

        $call = $this->capture->pop();
        self::assertNotNull($call, 'The call was not captured.');
        self::assertSame('GET|/V1/products/:sku', $call['pattern']);
    }

    #[Config('api_logger/general/enabled', '0')]
    #[Config('api_logger/general/enabled', '1', ScopeInterface::SCOPE_STORE, 'default')]
    #[Config('api_logger/general/enabled_endpoints', self::SELECTED_PRODUCTS, ScopeInterface::SCOPE_STORE, 'default')]
    public function testSettingsAreReadForTheStoreInTheCallsPath(): void
    {
        $this->dispatch('/rest/default/V1/products');
        self::assertSame('/default/V1/products', $this->capturedEndpoint());

        $defaultStoreView = self::service(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertNotNull($defaultStoreView);

        $this->dispatch('/rest/V1/products');
        $call = $this->capture->pop();
        self::assertSame(
            $defaultStoreView->getCode() === 'default',
            $call !== null,
            'A call without a store code reads the settings of the store Magento resolved for the request.'
        );
    }

    /**
     * Hand the REST controller a GET request as the front controller receives it
     *
     * @param string $path
     * @param string $query
     * @return void
     */
    private function dispatch(string $path, string $query = ''): void
    {
        $uri = $path . ($query !== '' ? '?' . $query : '');
        $request = Bootstrap::getObjectManager()->create(HttpRequest::class);
        self::assertInstanceOf(HttpRequest::class, $request);
        $request->setRequestUri($uri);
        $request->setPathInfo($path);
        $request->setUri('http://localhost' . $uri);
        $request->setMethod(HttpRequest::METHOD_GET);

        $this->interceptor->beforeDispatch($this->createMock(Rest::class), $request);
    }

    /**
     * The endpoint of the captured call; fails when nothing was captured
     *
     * @return string
     */
    private function capturedEndpoint(): string
    {
        $call = $this->capture->pop();
        self::assertNotNull($call, 'The call was not captured.');

        return $call['endpoint'];
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
