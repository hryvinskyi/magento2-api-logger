<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Ui\Component\Listing\Column;

use Hryvinskyi\ApiLogger\Model\CallRemover;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions of the REST log: the call's activity page, and removing the call
 */
class Actions extends Column
{
    private const ACTIVITY_VIEW = 'activitylog/activity/view';
    private const ENTRY_VIEW = 'api_logger/log/view';
    private const DELETE = 'api_logger/log/delete';
    private const DELETE_RESOURCE = 'Hryvinskyi_ApiLogger::delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Add the action links to each row
     *
     * @param array<string,mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        $data = $dataSource['data'] ?? null;
        $items = is_array($data) ? ($data['items'] ?? null) : null;
        if (!is_array($data) || !is_array($items)) {
            return $dataSource;
        }

        $name = $this->getName();
        $canDelete = $this->authorization->isAllowed(self::DELETE_RESOURCE)
            && $this->authorization->isAllowed(CallRemover::ACTIVITY_DELETE_RESOURCE);
        foreach ($items as $index => $item) {
            if (!is_array($item) || !is_numeric($item['entity_id'] ?? null)) {
                continue;
            }

            $activityId = $item['activity_id'] ?? null;
            $actions = [
                'view' => [
                    'href' => is_numeric($activityId)
                        ? $this->urlBuilder->getUrl(self::ACTIVITY_VIEW, ['id' => (int) $activityId])
                        : $this->urlBuilder->getUrl(self::ENTRY_VIEW, ['id' => (int) $item['entity_id']]),
                    'label' => __('View'),
                ],
            ];
            if ($canDelete) {
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl(self::DELETE, ['id' => (int) $item['entity_id']]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Remove this call?'),
                        'message' => __('The call and its activity are removed from the log for good.'),
                    ],
                ];
            }
            $item[$name] = $actions;
            $items[$index] = $item;
        }

        $data['items'] = $items;
        $dataSource['data'] = $data;

        return $dataSource;
    }
}
