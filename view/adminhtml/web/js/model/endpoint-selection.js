/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Rules of the endpoint picker: which endpoints a search shows, how a group's own checkbox reflects its
 * endpoints, and which value is saved. Pure: plain values in and out.
 */
define([], function () {
    'use strict';

    /**
     * @param {string} searchText What an endpoint is found by, lower case
     * @param {string} query
     * @returns {boolean}
     */
    function matches(searchText, query) {
        var needle = String(query || '').trim().toLowerCase();

        return needle === '' || String(searchText).indexOf(needle) !== -1;
    }

    /**
     * @param {number} visible Endpoints of the group the search shows
     * @param {number} checked Of those, how many are chosen
     * @returns {{checked: boolean, indeterminate: boolean}}
     */
    function groupState(visible, checked) {
        return {
            checked: visible > 0 && checked === visible,
            indeterminate: checked > 0 && checked < visible
        };
    }

    /**
     * @param {Array<string>} list
     * @returns {Array<string>} Each item once, in the order first seen
     */
    function unique(list) {
        return list.reduce(function (result, item) {
            return result.indexOf(item) === -1 ? result.concat([item]) : result;
        }, []);
    }

    /**
     * @param {string} text "METHOD|/route" values separated by commas
     * @returns {Array<string>} The values, trimmed, without empty ones
     */
    function values(text) {
        return String(text || '').split(',').map(function (item) {
            return item.trim();
        }).filter(function (item) {
            return item !== '';
        });
    }

    /**
     * The value to save and how many endpoints it selects
     *
     * A stored value without a checkbox (a wildcard pattern, the route of a module that is switched off) cannot be
     * chosen or cleared in the picker, so it is kept as it was and counted with the checked ones.
     *
     * @param {string} stored The value the picker holds: kept values and the checked ones, comma-separated
     * @param {Array<string>} listed Values of every checkbox
     * @param {Array<string>} checked Values of the checked checkboxes
     * @returns {{value: string, count: number}}
     */
    function chosen(stored, listed, checked) {
        var all = unique(values(stored).filter(function (item) {
            return listed.indexOf(item) === -1;
        }).concat(checked));

        return {value: all.join(','), count: all.length};
    }

    return Object.freeze({matches: matches, groupState: groupState, chosen: chosen});
});
