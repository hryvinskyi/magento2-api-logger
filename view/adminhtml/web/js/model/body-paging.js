/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Where a numbered line of a stored body sits among the pages the server serves. The page size is read from the
 * page just received, so it follows whatever the server is configured to serve. Pure: plain objects in, numbers out.
 */
define([], function () {
    'use strict';

    /**
     * Lines per page, worked out from a received page: the first line number of any page after the first is
     * (page - 1) * size + 1, and a page followed by another is full.
     *
     * @param {{lines: Array<{n: number}>, page: number}} data
     * @returns {number}
     */
    function pageSize(data) {
        var count = data.lines.length;

        if (data.page > 1 && count > 0) {
            return (data.lines[0].n - 1) / (data.page - 1);
        }

        return Math.max(1, count);
    }

    /**
     * @param {{lines: Array<{n: number}>, page: number, pages: number}} data A page just received
     * @param {number} line 1-based line number of the body
     * @returns {number} 1-based page holding that line, within the pages the body has
     */
    function pageOfLine(data, line) {
        return Math.min(Math.max(1, data.pages), Math.max(1, Math.ceil(line / pageSize(data))));
    }

    return Object.freeze({pageSize: pageSize, pageOfLine: pageOfLine});
});
