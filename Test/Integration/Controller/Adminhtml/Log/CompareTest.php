<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Integration\Controller\Adminhtml\Log;

use Magento\TestFramework\Fixture\DbIsolation;

/**
 * The calls a call can be compared with, and the two calls side by side, each body saying whether it was cut
 *
 * Each test logs calls to a path of its own, so rows other tests left in the shared database are never candidates.
 */
#[DbIsolation(true)]
class CompareTest extends LogControllerTestCase
{
    private const COMPARE_LIMIT_BYTES = 262144;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ApiLogger::logs';

    /**
     * @var string
     */
    protected $uri = 'backend/api_logger/log/compare';

    public function testOtherCallsToTheSameEndpointAreListedNewestFirstWithoutTheCurrentOne(): void
    {
        $path = $this->uniquePath();
        $older = $this->seed('GET', $path, 200);
        $current = $this->seed('GET', $path . '?fields=sku', 200);
        $newer = $this->seed('GET', $path . '?fields=name', 500);
        $this->seed('POST', $path, 200);
        $this->seed('GET', $path . '/children', 200);

        $this->dispatch(sprintf('backend/api_logger/log/compare/id/%d', self::idOf($current)));

        self::assertSame([self::idOf($newer), self::idOf($older)], $this->candidateIds());
    }

    public function testASearchByStatusNarrowsTheCandidates(): void
    {
        $path = $this->uniquePath();
        $this->seed('GET', $path, 200);
        $current = $this->seed('GET', $path, 200);
        $failed = $this->seed('GET', $path, 500);

        $this->httpRequest()->setParam('q', '500');
        $this->dispatch(sprintf('backend/api_logger/log/compare/id/%d', self::idOf($current)));

        self::assertSame([self::idOf($failed)], $this->candidateIds());
    }

    public function testWithAnotherCallBothSidesAreAnswered(): void
    {
        $path = $this->uniquePath();
        $current = $this->seed('GET', $path, 200, null, '{"name":"a"}');
        $other = $this->seed('GET', $path, 404, null, '{"message":"gone"}');

        $this->dispatch(sprintf(
            'backend/api_logger/log/compare/id/%d/other/%d',
            self::idOf($current),
            self::idOf($other)
        ));
        $data = $this->json();

        self::assertSame(['current', 'other'], array_keys($data));
        self::assertIsArray($data['current']);
        self::assertIsArray($data['other']);
        self::assertSame(self::idOf($current), $data['current']['id']);
        self::assertSame(200, $data['current']['status']);
        self::assertSame("{\n    \"name\": \"a\"\n}", $data['current']['response']);
        self::assertSame(self::idOf($other), $data['other']['id']);
        self::assertSame(404, $data['other']['status']);
        self::assertSame("{\n    \"message\": \"gone\"\n}", $data['other']['response']);
        self::assertFalse($data['current']['responseCut']);
        self::assertFalse($data['other']['requestCut']);
    }

    public function testABodyLongerThanTheCompareLimitIsCutAndSaysSo(): void
    {
        $path = $this->uniquePath();
        $long = str_repeat('a', self::COMPARE_LIMIT_BYTES + 10);
        $current = $this->seed('POST', $path, 200, 'sku=a', $long);
        $other = $this->seed('POST', $path, 200, 'sku=a', $long . 'b');

        $this->dispatch(sprintf(
            'backend/api_logger/log/compare/id/%d/other/%d',
            self::idOf($current),
            self::idOf($other)
        ));
        $data = $this->json();

        self::assertIsArray($data['current']);
        self::assertIsArray($data['other']);
        self::assertIsString($data['current']['response']);
        self::assertSame(self::COMPARE_LIMIT_BYTES, strlen($data['current']['response']));
        self::assertSame($data['current']['response'], $data['other']['response']);
        self::assertTrue($data['current']['responseCut']);
        self::assertTrue($data['other']['responseCut']);
        self::assertFalse($data['current']['requestCut']);
        self::assertFalse($data['other']['requestCut']);
    }

    public function testAnUnknownCallAnswersWithAnError(): void
    {
        $this->dispatch('backend/api_logger/log/compare/id/987654321');

        self::assertArrayHasKey('error', $this->json());
    }

    /**
     * Ids of the candidates in the answer, in their order
     *
     * @return list<int>
     */
    private function candidateIds(): array
    {
        $data = $this->json();
        self::assertIsArray($data['candidates'] ?? null, 'No candidates in: ' . $this->body());

        $ids = [];
        foreach ($data['candidates'] as $candidate) {
            self::assertIsArray($candidate);
            self::assertIsInt($candidate['id']);
            $ids[] = $candidate['id'];
        }

        return $ids;
    }

    /**
     * A product path no other test logs calls to
     *
     * @return string
     */
    private function uniquePath(): string
    {
        return '/default/V1/products/compare-' . bin2hex(random_bytes(6));
    }
}
