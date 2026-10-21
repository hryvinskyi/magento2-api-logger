/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Splits one line of pretty-printed JSON into coloured pieces, and marks the parts that match a search.
 * Pure: plain strings in, plain objects out; text that is not JSON comes back as plain text.
 */
define([], function () {
    'use strict';

    var TOKEN = /("(?:\\.|[^"\\])*")(\s*:)?|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)|\b(true|false|null)\b|([{}\[\],:])/g;

    var STRING_GROUP = 1,
        KEY_COLON = 2,
        GROUPS = [1, 3, 4, 5],
        TYPES = {1: 'string', 3: 'number', 4: 'literal', 5: 'punct'};

    /**
     * @param {Array} match
     * @param {number} group
     * @returns {boolean}
     */
    function groupMatched(match, group) {
        return match[group] !== undefined;
    }

    /**
     * @param {Array} tokens
     * @param {string} type
     * @param {string} text
     */
    function push(tokens, type, text) {
        if (text !== '') {
            tokens.push({type: type, text: text});
        }
    }

    /**
     * @param {string} line
     * @returns {Array<{type: string, text: string}>}
     */
    function tokenize(line) {
        var tokens = [],
            text = String(line),
            last = 0,
            match,
            group;

        TOKEN.lastIndex = 0;
        while ((match = TOKEN.exec(text)) !== null) {
            push(tokens, 'text', text.slice(last, match.index));
            group = GROUPS.find(groupMatched.bind(null, match));
            push(tokens, group === STRING_GROUP && match[KEY_COLON] !== undefined ? 'key' : TYPES[group], match[group]);
            push(tokens, 'punct', group === STRING_GROUP ? match[KEY_COLON] || '' : '');
            last = TOKEN.lastIndex;
        }
        push(tokens, 'text', text.slice(last));

        return tokens;
    }

    /**
     * Splits the tokens where the search matches, case ignored, and flags the matching pieces
     *
     * @param {Array<{type: string, text: string}>} tokens
     * @param {string} search
     * @returns {Array<{type: string, text: string, hit: boolean}>}
     */
    function highlight(tokens, search) {
        var needle = String(search || '').toLowerCase(),
            result = [];

        tokens.forEach(function (token) {
            var lower = token.text.toLowerCase(),
                from = 0,
                at;

            if (needle === '') {
                result.push({type: token.type, text: token.text, hit: false});

                return;
            }

            while ((at = lower.indexOf(needle, from)) !== -1) {
                if (at > from) {
                    result.push({type: token.type, text: token.text.slice(from, at), hit: false});
                }
                result.push({type: token.type, text: token.text.slice(at, at + needle.length), hit: true});
                from = at + needle.length;
            }
            if (from < token.text.length) {
                result.push({type: token.type, text: token.text.slice(from), hit: false});
            }
        });

        return result;
    }

    return Object.freeze({tokenize: tokenize, highlight: highlight});
});
