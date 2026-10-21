<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Hryvinskyi\ApiLogger\Api\Data\LogEntryInterface;
use Hryvinskyi\ApiLogger\Test\Integration\Fixture\RestCallSeeder;
use Laminas\Http\Header\HeaderInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\Url\ScopeInterface as UrlScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Request;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Admin actions of the REST call log, dispatched through the real admin stack as a logged-in admin
 */
abstract class LogControllerTestCase extends AbstractBackendController
{
    protected const HOST = 'api.example.test';

    /**
     * Store a call with its activity
     *
     * @param string $method
     * @param string $endpoint
     * @param int $responseCode
     * @param string|null $requestBody
     * @param string|null $responseBody
     * @param array<string, string> $requestHeaders
     * @param array<string, string> $responseHeaders
     * @return LogEntryInterface
     */
    protected function seed(
        string $method,
        string $endpoint,
        int $responseCode = 200,
        ?string $requestBody = null,
        ?string $responseBody = null,
        array $requestHeaders = ['Host' => self::HOST],
        array $responseHeaders = []
    ): LogEntryInterface {
        return self::service(RestCallSeeder::class)->seed(
            $method,
            $endpoint,
            $responseCode,
            $requestBody,
            $responseBody,
            $requestHeaders,
            $responseHeaders
        );
    }

    /**
     * The id of a stored entry
     *
     * @param LogEntryInterface $entry
     * @return int
     */
    protected static function idOf(LogEntryInterface $entry): int
    {
        $id = $entry->getEntityId();
        self::assertNotNull($id);

        return $id;
    }

    /**
     * The activity id of a stored entry
     *
     * @param LogEntryInterface $entry
     * @return int
     */
    protected static function activityIdOf(LogEntryInterface $entry): int
    {
        $id = $entry->getActivityId();
        self::assertNotNull($id);

        return $id;
    }

    /**
     * A shared service from the real container
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * @return Request
     */
    protected function httpRequest(): Request
    {
        $request = $this->getRequest();
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    /**
     * @return Response
     */
    protected function httpResponse(): Response
    {
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    /**
     * @return string
     */
    protected function body(): string
    {
        return (string) $this->httpResponse()->getBody();
    }

    /**
     * The response body decoded as a JSON object
     *
     * @return array<mixed>
     */
    protected function json(): array
    {
        $data = json_decode($this->body(), true);
        self::assertIsArray($data, 'The answer is not a JSON object: ' . $this->body());

        return $data;
    }

    /**
     * The value of a response header, or null when it was not sent
     *
     * @param string $name
     * @return string|null
     */
    protected function header(string $name): ?string
    {
        $header = $this->httpResponse()->getHeader($name);
        if ($header === false) {
            return null;
        }

        self::assertInstanceOf(HeaderInterface::class, $header);

        return $header->getFieldValue();
    }

    /**
     * Dispatch an action that answers with a file, and return what it sends
     *
     * The admin logged in by the test has not seen a page yet, and a file asked for as the first page after login is
     * answered with a redirect to the start page instead, so the session is told that page was seen.
     *
     * @param string $uri
     * @return string
     */
    protected function download(string $uri): string
    {
        $session = $this->_auth->getAuthStorage();
        self::assertInstanceOf(AdminSession::class, $session);
        $session->setIsFirstPageAfterLogin(false);
        $this->dispatch($uri);

        return $this->body();
    }

    /**
     * The secure base web URL of the default store view, which a call stored without a store is pointed at
     *
     * @return string
     */
    protected static function secureBaseUrl(): string
    {
        $store = self::service(StoreManagerInterface::class)->getDefaultStoreView();
        self::assertInstanceOf(UrlScopeInterface::class, $store);

        return rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true), '/');
    }
}
