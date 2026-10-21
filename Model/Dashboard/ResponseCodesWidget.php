<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\DashboardWidgetInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\Data\WidgetViewType;
use Hryvinskyi\ApiLogger\Model\Recording\RestCallRecorder;
use Hryvinskyi\ApiLogger\Model\ResourceModel\ResponseCodeStatistics;

/**
 * How REST calls of the period were answered, by class of status
 */
class ResponseCodesWidget implements DashboardWidgetInterface
{
    private const CLASSES = [
        2 => ['label' => '2xx', 'color' => '#3C9D5A'],
        3 => ['label' => '3xx', 'color' => '#4477AA'],
        4 => ['label' => '4xx', 'color' => '#E6A117'],
        5 => ['label' => '5xx', 'color' => '#C8372D'],
    ];

    /**
     * @param ResponseCodeStatistics $statistics
     */
    public function __construct(
        private readonly ResponseCodeStatistics $statistics
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'rest_response_codes';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return (string) __('REST response codes');
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return (string) __('Calls by class of status');
    }

    /**
     * @inheritDoc
     */
    public function getModuleTag(): ?string
    {
        return 'REST API';
    }

    /**
     * @inheritDoc
     */
    public function getViewType(): WidgetViewType
    {
        return WidgetViewType::Bar;
    }

    /**
     * @inheritDoc
     */
    public function getSpan(): int
    {
        return 1;
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): ?string
    {
        return 'Hryvinskyi_ApiLogger::logs';
    }

    /**
     * @inheritDoc
     */
    public function getData(DashboardQueryInterface $query): array
    {
        $counts = in_array(RestCallRecorder::CHANNEL, $query->getChannels(), true)
            ? $this->statistics->countByClass($query->getFromMs(), $query->getToMs())
            : [];
        if ($counts === []) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = [];
        $values = [];
        $colors = [];
        foreach (self::CLASSES as $class => $view) {
            $labels[] = $view['label'];
            $values[] = $counts[$class] ?? 0;
            $colors[] = $view['color'];
        }

        return [
            'labels' => $labels,
            'datasets' => [['label' => (string) __('Calls'), 'color' => $colors, 'values' => $values]],
        ];
    }
}
