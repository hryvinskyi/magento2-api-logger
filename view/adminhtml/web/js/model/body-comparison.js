/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What can be said about two compared bodies. A body longer than the compare limit arrives cut, so two bodies that
 * match only tell that their beginnings are the same. Pure: no DOM, no wording.
 */
define([], function () {
    'use strict';

    var DIFFERENT = 'different',
        IDENTICAL = 'identical',
        IDENTICAL_PREFIX = 'identicalPrefix';

    /**
     * @param {{text: string, cut: boolean}} left
     * @param {{text: string, cut: boolean}} right
     * @returns {string} "different"; "identical"; or "identicalPrefix" when they match but either was cut
     */
    function verdict(left, right) {
        if (left.text !== right.text) {
            return DIFFERENT;
        }

        return left.cut === true || right.cut === true ? IDENTICAL_PREFIX : IDENTICAL;
    }

    return Object.freeze({
        DIFFERENT: DIFFERENT,
        IDENTICAL: IDENTICAL,
        IDENTICAL_PREFIX: IDENTICAL_PREFIX,
        verdict: verdict
    });
});
