<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Http;

use Hryvinskyi\ActivityLog\Api\SanitizerInterface;
use Hryvinskyi\ApiLogger\Model\Http\Masker;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The adaptation of the activity log's sanitizer to headers and bodies stored as text
 */
class MaskerTest extends TestCase
{
    /**
     * @var SanitizerInterface&MockObject
     */
    private $sanitizer;

    /**
     * @var Masker
     */
    private Masker $masker;

    protected function setUp(): void
    {
        $this->sanitizer = $this->createMock(SanitizerInterface::class);
        $this->masker = new Masker($this->sanitizer, new Json());
    }

    public function testHeadersAreHandedToTheSanitizerAndComeBackAsAJsonObject(): void
    {
        $headers = ['Authorization' => 'Bearer secret', 'Accept' => ['application/json']];
        $this->sanitizer->expects(self::once())->method('sanitize')->with($headers)->willReturn([
            'Authorization' => 'Bearer ' . SanitizerInterface::MASK,
            'Accept' => ['application/json'],
        ]);

        $json = $this->masker->headers($headers);

        self::assertStringStartsWith('{', $json);
        self::assertSame(
            ['Authorization' => 'Bearer ' . SanitizerInterface::MASK, 'Accept' => ['application/json']],
            json_decode($json, true)
        );
    }

    public function testHeadersAreAnEmptyListWhenTheSanitizerDoesNotAnswerAnArray(): void
    {
        $this->sanitizer->method('sanitize')->willReturn(null);

        self::assertSame('[]', $this->masker->headers(['Authorization' => 'x']));
    }

    public function testNoHeadersAreAnEmptyList(): void
    {
        $this->sanitizer->method('sanitize')->willReturn([]);

        self::assertSame('[]', $this->masker->headers([]));
    }

    public function testABodyIsMaskedByTheSanitizersBodyRuleAndReturnedAsItAnswers(): void
    {
        $this->sanitizer->expects(self::never())->method('sanitize');
        $this->sanitizer->expects(self::once())->method('sanitizeBody')
            ->with('<login><password>x</password></login>')
            ->willReturn('<login><password>' . SanitizerInterface::MASK . '</password></login>');

        self::assertSame(
            '<login><password>' . SanitizerInterface::MASK . '</password></login>',
            $this->masker->text('<login><password>x</password></login>')
        );
    }
}
