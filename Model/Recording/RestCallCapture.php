<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Recording;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * What is known about each REST call being dispatched, from its dispatch until its response is ready
 *
 * A dispatch can run inside another one, so the calls form a stack: every dispatch pushes exactly one item, its call
 * or null when it is not logged, and takes back exactly that item when it returns. A dispatch that is not logged
 * therefore never hands the response of an inner call to the outer one, nor its own response to an outer call. A
 * dispatch that died without returning leaves its item behind; the stack is emptied after every request.
 *
 * @phpstan-type CapturedCall array{
 *     endpoint: string,
 *     method: string,
 *     pattern: string|null,
 *     headers: array<string, string|list<string>>,
 *     body: string|null,
 *     startedAtMs: int
 * }
 */
class RestCallCapture implements ResetAfterRequestInterface
{
    /**
     * @var list<CapturedCall|null>
     */
    private array $calls = [];

    /**
     * Start a dispatch: its call, or null when the call is not logged
     *
     * @param CapturedCall|null $call
     * @return void
     */
    public function push(?array $call): void
    {
        $this->calls[] = $call;
    }

    /**
     * End the innermost dispatch and hand over its call, or null when it was not logged or none is running
     *
     * @return CapturedCall|null
     */
    public function pop(): ?array
    {
        return array_pop($this->calls);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->calls = [];
    }
}
