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
use Magento\Webapi\Model\Config\Converter;

/**
 * Every REST route the installed modules declare, for choosing which ones are logged
 *
 * @phpstan-type Endpoint array{value: string, label: string, method: string}
 */
class Endpoints implements OptionSourceInterface
{
    /**
     * @param Config $webapiConfig
     */
    public function __construct(
        private readonly Config $webapiConfig
    ) {
    }

    /**
     * Each route once, by group then label
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (array_keys($this->routes()) as $route) {
            $options[] = ['value' => $route, 'label' => $this->label($route), 'group' => $this->group($route)];
        }

        usort(
            $options,
            static fn (array $a, array $b): int => strcmp($a['group'] . $a['label'], $b['group'] . $b['label'])
        );

        return $options;
    }

    /**
     * Routes grouped by resource, one entry per HTTP method
     *
     * @return array<string, list<Endpoint>>
     */
    public function getGroupedOptions(): array
    {
        $grouped = [];
        foreach ($this->routes() as $route => $methods) {
            foreach ($methods as $method) {
                $grouped[$this->group($route)][] = [
                    'value' => $route,
                    'label' => $this->label($route),
                    'method' => $method,
                ];
            }
        }

        ksort($grouped);
        foreach ($grouped as $group => $endpoints) {
            usort(
                $endpoints,
                static fn (array $a, array $b): int => strcmp($a['method'] . $a['label'], $b['method'] . $b['label'])
            );
            $grouped[$group] = $endpoints;
        }

        return $grouped;
    }

    /**
     * Declared routes with their HTTP methods, upper case
     *
     * @return array<string, list<string>>
     */
    private function routes(): array
    {
        $routes = $this->webapiConfig->getServices()[Converter::KEY_ROUTES] ?? [];
        if (!is_array($routes)) {
            return [];
        }

        $result = [];
        foreach ($routes as $route => $methods) {
            if (!is_array($methods)) {
                continue;
            }

            $path = '/' . trim((string) $route, '/');
            $result[$path] = array_map(
                static fn (int|string $method): string => strtoupper((string) $method),
                array_keys($methods)
            );
        }

        return $result;
    }

    /**
     * The resource a route belongs to: "/V1/products/:sku" → "Products"
     *
     * @param string $route
     * @return string
     */
    private function group(string $route): string
    {
        return preg_match('#^/V\d+/([^/]+)#', $route, $matches) === 1 ? ucfirst($matches[1]) : 'Other';
    }

    /**
     * A route as people read it: "/V1/products/:sku" → "Products - Sku"
     *
     * @param string $route
     * @return string
     */
    private function label(string $route): string
    {
        $parts = explode('/', trim($route, '/'));
        if (count($parts) < 2) {
            return $route;
        }

        $resource = ucfirst($parts[1]);
        $action = array_slice($parts, 2);

        return $action === []
            ? $resource
            : $resource . ' - ' . str_replace(':', '', implode(' ', array_map('ucfirst', $action)));
    }
}
