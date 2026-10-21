/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Line diff of two texts for a side-by-side view. Lines are aligned by their longest common subsequence; texts too
 * large for that are compared line by line at the same position. Pure: arrays of strings in, rows out.
 */
define([], function () {
    'use strict';

    var MAX_CELLS = 4000000;

    /**
     * @param {Array<string>} left
     * @param {Array<string>} right
     * @returns {Array<{kind: string, left: ?string, right: ?string}>}
     */
    function byPosition(left, right) {
        var rows = [],
            length = Math.max(left.length, right.length),
            index,
            a,
            b;

        for (index = 0; index < length; index++) {
            a = index < left.length ? left[index] : null;
            b = index < right.length ? right[index] : null;
            rows.push({kind: a === b ? 'same' : 'change', left: a, right: b});
        }

        return rows;
    }

    /**
     * Pairs runs of removed and added lines into changed rows
     *
     * @param {Array<{kind: string, line: string}>} ops
     * @returns {Array<{kind: string, left: ?string, right: ?string}>}
     */
    function pair(ops) {
        var rows = [],
            removed = [],
            added = [];

        function flush() {
            var count = Math.max(removed.length, added.length),
                index;

            for (index = 0; index < count; index++) {
                rows.push({
                    kind: index < removed.length && index < added.length ? 'change'
                        : (index < removed.length ? 'del' : 'add'),
                    left: index < removed.length ? removed[index] : null,
                    right: index < added.length ? added[index] : null
                });
            }
            removed = [];
            added = [];
        }

        var handlers = {
            del: function (line) {
                removed.push(line);
            },
            add: function (line) {
                added.push(line);
            },
            same: function (line) {
                flush();
                rows.push({kind: 'same', left: line, right: line});
            }
        };

        ops.forEach(function (op) {
            handlers[op.kind](op.line);
        });
        flush();

        return rows;
    }

    /**
     * @param {Array<string>} left
     * @param {Array<string>} right
     * @returns {Array<{kind: string, left: ?string, right: ?string}>}
     */
    function diff(left, right) {
        var n = left.length,
            m = right.length,
            width = m + 1,
            table,
            i,
            j,
            ops = [];

        if ((n + 1) * (m + 1) > MAX_CELLS) {
            return byPosition(left, right);
        }

        table = new Uint32Array((n + 1) * width);
        for (i = n - 1; i >= 0; i--) {
            for (j = m - 1; j >= 0; j--) {
                table[i * width + j] = left[i] === right[j]
                    ? table[(i + 1) * width + j + 1] + 1
                    : Math.max(table[(i + 1) * width + j], table[i * width + j + 1]);
            }
        }

        i = 0;
        j = 0;
        while (i < n && j < m) {
            if (left[i] === right[j]) {
                ops.push({kind: 'same', line: left[i]});
                i++;
                j++;
            } else if (table[(i + 1) * width + j] >= table[i * width + j + 1]) {
                ops.push({kind: 'del', line: left[i++]});
            } else {
                ops.push({kind: 'add', line: right[j++]});
            }
        }
        while (i < n) {
            ops.push({kind: 'del', line: left[i++]});
        }
        while (j < m) {
            ops.push({kind: 'add', line: right[j++]});
        }

        return pair(ops);
    }

    return Object.freeze({diff: diff});
});
