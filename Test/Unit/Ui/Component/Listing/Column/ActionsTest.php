<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Ui\Component\Listing\Column;

use Hryvinskyi\ApiLogger\Model\CallRemover;
use Hryvinskyi\ApiLogger\Ui\Component\Listing\Column\Actions;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

/**
 * The row actions of the REST call grid
 */
class ActionsTest extends TestCase
{
    public function testARowLinksToItsActivityAndOffersRemovalToAnAdminAllowedBoth(): void
    {
        $actions = $this->actionsOf($this->column([]), ['entity_id' => '7', 'activity_id' => '42']);

        self::assertSame('hryvinskyi_activitylog/activity/view|42', $actions['view']['href'] ?? null);
        self::assertSame('api_logger/log/delete|7', $actions['delete']['href'] ?? null);
        self::assertTrue($actions['delete']['post'] ?? false);
    }

    public function testARowWithoutAnActivityLinksToTheCallPage(): void
    {
        $actions = $this->actionsOf($this->column([]), ['entity_id' => '7', 'activity_id' => null]);

        self::assertSame('api_logger/log/view|7', $actions['view']['href'] ?? null);
    }

    public function testRemovalIsNotOfferedWithoutThePermissionToRemoveActivities(): void
    {
        $actions = $this->actionsOf(
            $this->column([CallRemover::ACTIVITY_DELETE_RESOURCE]),
            ['entity_id' => '7', 'activity_id' => '42']
        );

        self::assertArrayHasKey('view', $actions);
        self::assertArrayNotHasKey('delete', $actions);
    }

    public function testRemovalIsNotOfferedWithoutThePermissionToRemoveCalls(): void
    {
        $actions = $this->actionsOf(
            $this->column(['Hryvinskyi_ApiLogger::delete']),
            ['entity_id' => '7', 'activity_id' => '42']
        );

        self::assertArrayNotHasKey('delete', $actions);
    }

    /**
     * @param list<string> $denied
     * @return Actions
     */
    private function column(array $denied): Actions
    {
        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => sprintf('%s|%d', $route, self::id($params))
        );
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => !in_array($resource, $denied, true)
        );

        return new Actions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $urlBuilder,
            $authorization,
            [],
            ['name' => 'actions']
        );
    }

    /**
     * @param array<mixed> $params
     * @return int
     */
    private static function id(array $params): int
    {
        return is_numeric($params['id'] ?? null) ? (int) $params['id'] : 0;
    }

    /**
     * @param Actions $column
     * @param array<string,mixed> $row
     * @return array<string,array<string,mixed>>
     */
    private function actionsOf(Actions $column, array $row): array
    {
        $result = $column->prepareDataSource(['data' => ['items' => [$row]]]);
        $data = $result['data'] ?? null;
        self::assertIsArray($data);
        $items = $data['items'] ?? null;
        self::assertIsArray($items);
        $item = $items[0] ?? null;
        self::assertIsArray($item);
        $actions = $item['actions'] ?? null;
        self::assertIsArray($actions);

        /** @var array<string,array<string,mixed>> $actions */
        return $actions;
    }
}
