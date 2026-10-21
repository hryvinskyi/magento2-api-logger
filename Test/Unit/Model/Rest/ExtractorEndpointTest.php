<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Rest;

use Hryvinskyi\ApiLogger\Model\Rest\Extractor;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\ErrorProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The endpoint of a call is its path after the REST front name, with its query
 */
class ExtractorEndpointTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function paths(): array
    {
        return [
            'front name and query' => ['/rest/V1/x', 'http://shop.test/rest/V1/x?a=1', '/V1/x?a=1'],
            'front name and store code' => [
                '/rest/default/V1/x',
                'http://shop.test/rest/default/V1/x',
                '/default/V1/x',
            ],
            'query kept as sent' => [
                '/rest/default/V1/products',
                'http://shop.test/rest/default/V1/products?searchCriteria%5BpageSize%5D=1',
                '/default/V1/products?searchCriteria%5BpageSize%5D=1',
            ],
            'first segment only starts like the front name' => [
                '/restaurants/V1/x',
                'http://shop.test/restaurants/V1/x',
                '/restaurants/V1/x',
            ],
            'front name later in the path' => ['/V1/rest/x', 'http://shop.test/V1/rest/x', '/V1/rest/x'],
            'front name alone' => ['/rest', 'http://shop.test/rest', '/'],
        ];
    }

    #[DataProvider('paths')]
    public function testTheLeadingFrontNameIsRemoved(string $pathInfo, string $uri, string $endpoint): void
    {
        self::assertSame($endpoint, $this->extractor('rest')->extractEndpoint($this->request($pathInfo, $uri)));
    }

    public function testAnotherConfiguredFrontNameIsRemoved(): void
    {
        $request = $this->request('/api/default/V1/x', 'http://shop.test/api/default/V1/x');

        self::assertSame('/default/V1/x', $this->extractor('api')->extractEndpoint($request));
    }

    public function testThePathIsKeptWhenNoFrontNameIsConfigured(): void
    {
        $request = $this->request('/rest/V1/x', 'http://shop.test/rest/V1/x?a=1');

        self::assertSame('/rest/V1/x?a=1', $this->extractor(null)->extractEndpoint($request));
        self::assertSame('/rest/V1/x?a=1', $this->extractor('')->extractEndpoint($request));
    }

    /**
     * An extractor whose REST area answers the front name
     *
     * @param string|null $frontName
     * @return Extractor
     */
    private function extractor(?string $frontName): Extractor
    {
        $areaList = $this->createMock(AreaList::class);
        $areaList->method('getFrontName')->with(Area::AREA_WEBAPI_REST)->willReturn($frontName);

        return new Extractor(
            $this->createMock(State::class),
            $this->createMock(ErrorProcessor::class),
            $this->createMock(SerializerInterface::class),
            $areaList
        );
    }

    /**
     * A request with its path info and full URI
     *
     * @param string $pathInfo
     * @param string $uri
     * @return HttpRequest
     */
    private function request(string $pathInfo, string $uri): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getUri')->willReturn(new HttpUri($uri));

        return $request;
    }
}
