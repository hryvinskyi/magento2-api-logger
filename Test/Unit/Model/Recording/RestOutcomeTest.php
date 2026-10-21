<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ApiLogger\Model\Recording\RestOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The outcome an activity records for a REST response
 */
class RestOutcomeTest extends TestCase
{
    /**
     * @var RestOutcome
     */
    private RestOutcome $restOutcome;

    protected function setUp(): void
    {
        $this->restOutcome = new RestOutcome();
    }

    /**
     * @return array<string, array{int, bool, Outcome}>
     */
    public static function outcomes(): array
    {
        return [
            '401 is denied' => [401, false, Outcome::Denied],
            '403 is denied' => [403, false, Outcome::Denied],
            '401 with an exception is still denied' => [401, true, Outcome::Denied],
            '400 is a failure' => [400, false, Outcome::Failure],
            '404 is a failure' => [404, false, Outcome::Failure],
            '500 is a failure' => [500, false, Outcome::Failure],
            '200 is a success' => [200, false, Outcome::Success],
            '201 is a success' => [201, false, Outcome::Success],
            '204 is a success' => [204, false, Outcome::Success],
            '200 with an exception is a failure' => [200, true, Outcome::Failure],
        ];
    }

    #[DataProvider('outcomes')]
    public function testResponseCodeAndExceptionFlagGiveTheOutcome(int $code, bool $exception, Outcome $expected): void
    {
        self::assertSame($expected, $this->restOutcome->of($code, $exception));
    }
}
