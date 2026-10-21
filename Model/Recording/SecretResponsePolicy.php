<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

/**
 * Routes whose whole response is a secret: it is stored as the mask, whatever its shape
 *
 * A route that issues a token answers with the token itself, often as a bare JSON string no field name gives away; a
 * route that sets up a second factor answers with the shared secret. The routes are regular expressions matched
 * against the route of the call (store code, async prefix, query and trailing slash removed), configured as an array
 * argument so any module can add its own.
 */
class SecretResponsePolicy
{
    /**
     * @var list<string>
     */
    private readonly array $routePatterns;

    /**
     * @param RoutePath $routePath
     * @param array<mixed> $routePatterns Regular expressions, delimiters included, matched against the route
     */
    public function __construct(
        private readonly RoutePath $routePath,
        array $routePatterns = []
    ) {
        $patterns = [];
        foreach ($routePatterns as $name => $pattern) {
            if (!is_string($pattern) || preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(
                    sprintf('The secret response route "%s" must be a valid regular expression.', (string) $name)
                );
            }
            $patterns[] = $pattern;
        }
        $this->routePatterns = $patterns;
    }

    /**
     * Whether the response of a call to the endpoint is stored as the mask
     *
     * @param string $endpoint
     * @return bool
     */
    public function masksWholeResponse(string $endpoint): bool
    {
        $route = $this->routePath->route($endpoint);
        foreach ($this->routePatterns as $pattern) {
            if (preg_match($pattern, $route) === 1) {
                return true;
            }
        }

        return false;
    }
}
