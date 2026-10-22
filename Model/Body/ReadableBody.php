<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Model\Body;

/**
 * A stored body as people read it: a JSON object or array laid out over indented lines, anything else as it was stored
 *
 * The layout works on the JSON text rather than on decoded values, so every number reads exactly as the call sent
 * it: an integer past PHP's range stays an integer, "1.50" keeps its zero. A string is shown with its escapes for
 * slashes and non-ASCII letters written out, the way it reads; any other escape stays. Members are indented by four
 * spaces, and an empty object or array stays on its line.
 */
class ReadableBody
{
    private const INDENT = '    ';
    private const MAX_DEPTH = 512;
    private const STRING_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    private const TOKENS = '/"(?:[^"\\\\]|\\\\.)*"|[{}\[\],:]|[^\s{}\[\],:"]+/s';
    private const CLOSING = ['{' => '}', '[' => ']'];

    /**
     * The body as readable text
     *
     * @param string|null $body
     * @return string
     */
    public function text(?string $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        $first = ltrim($body)[0] ?? '';
        if (!isset(self::CLOSING[$first]) || !$this->isJson($body)) {
            return $body;
        }

        return preg_match_all(self::TOKENS, $body, $matches) === false ? $body : $this->layout($matches[0]);
    }

    /**
     * Whether the text is valid JSON
     *
     * @param string $text
     * @return bool
     */
    private function isJson(string $text): bool
    {
        try {
            json_decode($text, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return false;
        }

        return true;
    }

    /**
     * The tokens of valid JSON laid out over indented lines
     *
     * @param list<string> $tokens
     * @return string
     */
    private function layout(array $tokens): string
    {
        $text = '';
        $depth = 0;
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (isset(self::CLOSING[$token]) && ($tokens[$index + 1] ?? null) === self::CLOSING[$token]) {
                $text .= $token . self::CLOSING[$token];
                $index++;
                continue;
            }

            $text .= match ($token) {
                '{', '[' => $token . "\n" . str_repeat(self::INDENT, ++$depth),
                '}', ']' => "\n" . str_repeat(self::INDENT, --$depth) . $token,
                ',' => ",\n" . str_repeat(self::INDENT, $depth),
                ':' => ': ',
                default => $token[0] === '"' ? $this->string($token) : $token,
            };
        }

        return $text;
    }

    /**
     * A string token with its slash and non-ASCII escapes written out
     *
     * @param string $token
     * @return string
     */
    private function string(string $token): string
    {
        if (!str_contains($token, '\\')) {
            return $token;
        }

        $value = json_decode($token);
        $encoded = is_string($value) ? json_encode($value, self::STRING_FLAGS) : false;

        return $encoded === false ? $token : $encoded;
    }
}
