<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Body;

/**
 * Splits a stored body into numbered lines served a page at a time
 *
 * A body is first made readable (ReadableBody), so a JSON body reads line by line. A search covers the whole body,
 * not only the page: it answers with the numbers of every matching line, up to a limit.
 *
 * @phpstan-type BodyPage array{
 *     lines: list<array{n: int, text: string}>,
 *     page: int,
 *     pages: int,
 *     totalLines: int,
 *     bytes: int,
 *     hits: list<int>
 * }
 */
class BodyPager
{
    /**
     * @param ReadableBody $readableBody
     * @param int $pageSize Lines per page
     * @param int $maxHits Matching lines reported at most
     */
    public function __construct(
        private readonly ReadableBody $readableBody,
        private readonly int $pageSize = 500,
        private readonly int $maxHits = 1000
    ) {
    }

    /**
     * One page of the body's lines, with the lines matching the search
     *
     * @param string|null $body
     * @param int $page 1-based; out of range is brought back in range
     * @param string $search
     * @return BodyPage
     */
    public function page(?string $body, int $page, string $search = ''): array
    {
        $lines = $this->lines($body);
        $pages = max(1, (int) ceil(count($lines) / $this->pageSize));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $this->pageSize;

        $numbered = [];
        foreach (array_slice($lines, $offset, $this->pageSize) as $index => $text) {
            $numbered[] = ['n' => $offset + $index + 1, 'text' => $text];
        }

        return [
            'lines' => $numbered,
            'page' => $page,
            'pages' => $pages,
            'totalLines' => count($lines),
            'bytes' => strlen((string) $body),
            'hits' => $this->hits($lines, $search),
        ];
    }

    /**
     * Split a body into its readable lines
     *
     * @param string|null $body
     * @return list<string>
     */
    private function lines(?string $body): array
    {
        $text = $this->readableBody->text($body);

        return $text === '' ? [] : explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * Numbers of the lines containing the search, case ignored
     *
     * @param list<string> $lines
     * @param string $search
     * @return list<int>
     */
    private function hits(array $lines, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $hits = [];
        foreach ($lines as $index => $line) {
            if (mb_stripos($line, $search) !== false) {
                $hits[] = $index + 1;
                if (count($hits) >= $this->maxHits) {
                    break;
                }
            }
        }

        return $hits;
    }
}
