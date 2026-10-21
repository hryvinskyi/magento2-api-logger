/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');

/**
 * The module runs in its own realm, so what it returns is compared by value, not by prototype
 *
 * @param {*} value
 * @returns {*}
 */
const plain = (value) => JSON.parse(JSON.stringify(value));

const LISTED = ['GET|/V1/products', 'POST|/V1/products', 'GET|/V1/orders'];

test('endpoint selection: it exports exactly matches, groupState and chosen', () => {
    const selection = load('model/endpoint-selection');

    assert.deepEqual(Object.keys(selection).sort(), ['chosen', 'groupState', 'matches']);
});

test('endpoint selection: an empty, blank or missing query matches everything', () => {
    const {matches} = load('model/endpoint-selection');

    for (const query of ['', '   ', undefined, null]) {
        assert.equal(matches('get /v1/products', query), true);
    }
});

test('endpoint selection: the query is trimmed and lower-cased before it is looked for', () => {
    const {matches} = load('model/endpoint-selection');

    assert.equal(matches('get /v1/products/:sku', '  PRODUCTS '), true);
    assert.equal(matches('get /v1/products/:sku', 'get /v1'), true);
    assert.equal(matches('get /v1/products/:sku', 'orders'), false);
});

test('endpoint selection: a group with nothing visible is neither checked nor indeterminate', () => {
    const {groupState} = load('model/endpoint-selection');

    assert.deepEqual(plain(groupState(0, 0)), {checked: false, indeterminate: false});
});

test('endpoint selection: a group is checked when all visible endpoints are, indeterminate when some are', () => {
    const {groupState} = load('model/endpoint-selection');

    assert.deepEqual(plain(groupState(3, 3)), {checked: true, indeterminate: false});
    assert.deepEqual(plain(groupState(3, 1)), {checked: false, indeterminate: true});
    assert.deepEqual(plain(groupState(3, 2)), {checked: false, indeterminate: true});
    assert.deepEqual(plain(groupState(3, 0)), {checked: false, indeterminate: false});
});

test('endpoint selection: the saved value is the checked endpoints when every stored value has a checkbox', () => {
    const {chosen} = load('model/endpoint-selection');

    assert.deepEqual(
        plain(chosen('GET|/V1/products', LISTED, ['GET|/V1/products', 'GET|/V1/orders'])),
        {value: 'GET|/V1/products,GET|/V1/orders', count: 2}
    );
    assert.deepEqual(plain(chosen('GET|/V1/products', LISTED, [])), {value: '', count: 0});
});

test('endpoint selection: a wildcard pattern and a route without a checkbox are kept and counted', () => {
    const {chosen} = load('model/endpoint-selection');
    const stored = 'GET|/V1/products/*,GET|/V1/products,DELETE|/V1/switched-off/:id';

    assert.deepEqual(
        plain(chosen(stored, LISTED, ['POST|/V1/products'])),
        {value: 'GET|/V1/products/*,DELETE|/V1/switched-off/:id,POST|/V1/products', count: 3}
    );
    assert.deepEqual(
        plain(chosen(stored, LISTED, [])),
        {value: 'GET|/V1/products/*,DELETE|/V1/switched-off/:id', count: 2},
        'Clearing every checkbox keeps the values the picker cannot show.'
    );
});

test('endpoint selection: the saved value is stable when it is computed again from itself', () => {
    const {chosen} = load('model/endpoint-selection');
    const first = plain(chosen('GET|/V1/products/*', LISTED, ['GET|/V1/orders']));

    assert.deepEqual(plain(chosen(first.value, LISTED, ['GET|/V1/orders'])), first);
});

test('endpoint selection: stored values are trimmed, empty ones dropped and each counted once', () => {
    const {chosen} = load('model/endpoint-selection');

    assert.deepEqual(
        plain(chosen(' GET|/V1/carts/* ,, GET|/V1/carts/* ,', LISTED, ['GET|/V1/orders', 'GET|/V1/orders'])),
        {value: 'GET|/V1/carts/*,GET|/V1/orders', count: 2}
    );
    assert.deepEqual(plain(chosen('', LISTED, [])), {value: '', count: 0});
});
