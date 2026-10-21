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
 * A promise with its settle functions, so a test decides when a task ends
 *
 * @returns {{promise: Promise<*>, resolve: function(*): void, reject: function(*): void}}
 */
function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return {promise, resolve, reject};
}

test('single flight: a second run while the first is pending does nothing', async () => {
    const {singleFlight} = load('model/single-flight');
    const pending = deferred();
    let runs = 0;
    const run = singleFlight(() => {
        runs += 1;

        return pending.promise;
    }, () => {});

    const first = run();
    const second = run();

    assert.equal(second, null);
    pending.resolve('done');
    assert.equal(await first, 'done');
    assert.equal(runs, 1);
});

test('single flight: busy is told when a run starts and when it ends, and a new run can start after', async () => {
    const {singleFlight} = load('model/single-flight');
    const busy = [];
    let runs = 0;
    const run = singleFlight(() => {
        runs += 1;

        return Promise.resolve(runs);
    }, (value) => busy.push(value));

    await run();
    assert.deepEqual(busy, [true, false]);

    assert.equal(await run(), 2);
    assert.deepEqual(busy, [true, false, true, false]);
});

test('single flight: a failed run ends the flight and its failure reaches the caller', async () => {
    const {singleFlight} = load('model/single-flight');
    const busy = [];
    const pending = deferred();
    const run = singleFlight(() => pending.promise, (value) => busy.push(value));

    const first = run();
    pending.reject(new Error('offline'));

    await assert.rejects(first, /offline/);
    assert.deepEqual(busy, [true, false]);

    const again = run();
    assert.notEqual(again, null);
    await assert.rejects(again, /offline/);
});

test('single flight: a task that throws before returning a promise still ends the flight', async () => {
    const {singleFlight} = load('model/single-flight');
    const busy = [];
    const run = singleFlight(() => {
        throw new Error('broken');
    }, (value) => busy.push(value));

    await assert.rejects(run(), /broken/);
    assert.deepEqual(busy, [true, false]);
});

test('body comparison: different texts are different, whether or not they were cut', () => {
    const {verdict} = load('model/body-comparison');

    assert.equal(verdict({text: 'a', cut: false}, {text: 'b', cut: false}), 'different');
    assert.equal(verdict({text: 'a', cut: true}, {text: 'b', cut: true}), 'different');
});

test('body comparison: equal texts that were not cut are identical', () => {
    const {verdict} = load('model/body-comparison');

    assert.equal(verdict({text: 'a', cut: false}, {text: 'a', cut: false}), 'identical');
    assert.equal(verdict({text: '', cut: false}, {text: '', cut: false}), 'identical');
});

test('body comparison: equal texts are only identical in their beginning when either side was cut', () => {
    const {verdict, IDENTICAL_PREFIX} = load('model/body-comparison');

    assert.equal(IDENTICAL_PREFIX, 'identicalPrefix');
    assert.equal(verdict({text: 'a', cut: true}, {text: 'a', cut: false}), IDENTICAL_PREFIX);
    assert.equal(verdict({text: 'a', cut: false}, {text: 'a', cut: true}), IDENTICAL_PREFIX);
    assert.equal(verdict({text: 'a', cut: true}, {text: 'a', cut: true}), IDENTICAL_PREFIX);
});
