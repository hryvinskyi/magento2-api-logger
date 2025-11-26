<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Webapi\Model\Config;

/**
 * Source model for API endpoints
 *
 * Provides list of available API endpoints from webapi.xml
 */
class Endpoints implements OptionSourceInterface
{
    private ?array $options = null;

    public function __construct(
        private readonly Config $webapiConfig
    ) {
    }

    /**
     * Get options as array with value and label
     *
     * @return array<array{value: string, label: string, group: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = $this->buildOptions();
        }

        return $this->options;
    }

    /**
     * Get options as key-value pairs
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $options = $this->toOptionArray();
        $result = [];

        foreach ($options as $option) {
            $result[$option['value']] = $option['label'];
        }

        return $result;
    }

    /**
     * Get grouped options for the advanced frontend
     *
     * @return array<string, array<array{value: string, label: string, methods: array<string>}>>
     */
    public function getGroupedOptions(): array
    {
        $services = $this->webapiConfig->getServices();
        $routes = $services[Config\Converter::KEY_ROUTES] ?? [];

        $grouped = [];

        foreach ($routes as $route => $methods) {
            $routePath = '/' . trim($route, '/');
            $group = $this->extractGroup($routePath);
            $label = $this->formatLabel($routePath);

            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            // Each HTTP method gets its own entry
            foreach ($methods as $method => $config) {
                $grouped[$group][] = [
                    'value' => $routePath,
                    'label' => $label,
                    'method' => strtoupper($method)
                ];
            }
        }

        // Sort groups alphabetically
        ksort($grouped);

        // Sort endpoints within each group
        foreach ($grouped as &$endpoints) {
            usort($endpoints, fn($a, $b) => strcmp($a['method'] . $a['label'], $b['method'] . $b['label']));
        }

        return $grouped;
    }

    /**
     * Build options from webapi configuration
     *
     * @return array<array{value: string, label: string, group: string}>
     */
    private function buildOptions(): array
    {
        $options = [];
        $services = $this->webapiConfig->getServices();

        if (!isset($services[Config\Converter::KEY_ROUTES])) {
            return $options;
        }

        foreach ($services[Config\Converter::KEY_ROUTES] as $route => $methods) {
            $routePath = '/' . trim($route, '/');
            $group = $this->extractGroup($routePath);
            $label = $this->formatLabel($routePath);

            $options[] = [
                'value' => $routePath,
                'label' => $label,
                'group' => $group
            ];
        }

        usort($options, fn($a, $b) => strcmp($a['group'] . $a['label'], $b['group'] . $b['label']));

        return $options;
    }

    /**
     * Extract group name from route path
     *
     * @param string $route
     * @return string
     */
    private function extractGroup(string $route): string
    {
        // Extract group from route like /V1/products/:id -> Products
        if (preg_match('#^/V\d+/([^/]+)#', $route, $matches)) {
            return ucfirst($matches[1]);
        }

        return 'Other';
    }

    /**
     * Format route path as readable label
     *
     * @param string $route
     * @return string
     */
    private function formatLabel(string $route): string
    {
        // Convert /V1/products/:id to "Products - Get by ID"
        $parts = explode('/', trim($route, '/'));

        if (count($parts) < 2) {
            return $route;
        }

        $version = array_shift($parts); // Remove V1
        $resource = ucfirst(array_shift($parts));

        if (empty($parts)) {
            return $resource;
        }

        $action = implode(' ', array_map('ucfirst', $parts));
        $action = str_replace(':', '', $action);

        return "$resource - $action";
    }
}