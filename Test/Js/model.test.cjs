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
 * The modules run in their own realm, so what they return is compared by value, not by prototype
 *
 * @param {*} value
 * @returns {*}
 */
const plain = (value) => JSON.parse(JSON.stringify(value));

const texts = (tokens) => tokens.map((token) => token.text).join('');
const types = (tokens) => plain(tokens).map((token) => token.type);

test('json tokens: a key and its value are typed and joining the texts gives the line back', () => {
    const {tokenize} = load('model/json-tokens');
    const line = '    "name": "Widget",';
    const tokens = tokenize(line);

    assert.equal(texts(tokens), line);
    assert.deepEqual(plain(tokens), [
        {type: 'text', text: '    '},
        {type: 'key', text: '"name"'},
        {type: 'punct', text: ':'},
        {type: 'text', text: ' '},
        {type: 'string', text: '"Widget"'},
        {type: 'punct', text: ','}
    ]);
});

test('json tokens: nested lines, braces and brackets are punctuation', () => {
    const {tokenize} = load('model/json-tokens');

    for (const line of ['{', '    "items": [', '        {', '}', ']', '  "a": {"b": [1, 2]}']) {
        assert.equal(texts(tokenize(line)), line);
    }
    assert.deepEqual(types(tokenize('    "items": [')), ['text', 'key', 'punct', 'text', 'punct']);
});

test('json tokens: numbers with sign, fraction and exponent stay whole', () => {
    const {tokenize} = load('model/json-tokens');

    for (const number of ['42', '-7', '3.14', '1e10', '-2.5E+3', '6.02e-23']) {
        const line = `"n": ${number}`;
        const tokens = plain(tokenize(line));

        assert.equal(texts(tokens), line);
        assert.deepEqual(tokens.filter((token) => token.type === 'number'), [{type: 'number', text: number}]);
    }
});

test('json tokens: true, false and null are literals', () => {
    const {tokenize} = load('model/json-tokens');

    for (const literal of ['true', 'false', 'null']) {
        const line = `"flag": ${literal}`;
        const tokens = plain(tokenize(line));

        assert.equal(texts(tokens), line);
        assert.deepEqual(tokens.filter((token) => token.type === 'literal'), [{type: 'literal', text: literal}]);
    }
});

test('json tokens: a string with an escaped quote is one token', () => {
    const {tokenize} = load('model/json-tokens');
    const line = '"say": "he said \\"hi\\" twice"';
    const tokens = plain(tokenize(line));

    assert.equal(texts(tokens), line);
    assert.deepEqual(tokens.filter((token) => token.type === 'string'), [
        {type: 'string', text: '"he said \\"hi\\" twice"'}
    ]);
});

test('json tokens: text that is not JSON comes back as plain text', () => {
    const {tokenize} = load('model/json-tokens');

    assert.deepEqual(plain(tokenize('not json at all')), [{type: 'text', text: 'not json at all'}]);
    assert.deepEqual(plain(tokenize('')), []);
});

test('json tokens: highlight marks hits whatever the case and splits a token at the hit boundaries', () => {
    const {tokenize, highlight} = load('model/json-tokens');
    const tokens = tokenize('"name": "Widget"');
    const marked = plain(highlight(tokens, 'DGE'));

    assert.equal(texts(marked), '"name": "Widget"');
    assert.deepEqual(marked.filter((piece) => piece.hit), [{type: 'string', text: 'dge', hit: true}]);
    assert.deepEqual(marked.filter((piece) => piece.type === 'string'), [
        {type: 'string', text: '"Wi', hit: false},
        {type: 'string', text: 'dge', hit: true},
        {type: 'string', text: 't"', hit: false}
    ]);
});

test('json tokens: highlight marks every occurrence in a token', () => {
    const {highlight} = load('model/json-tokens');
    const marked = plain(highlight([{type: 'string', text: 'abcAbc'}], 'abc'));

    assert.deepEqual(marked, [
        {type: 'string', text: 'abc', hit: true},
        {type: 'string', text: 'Abc', hit: true}
    ]);
});

test('json tokens: an empty search marks nothing', () => {
    const {tokenize, highlight} = load('model/json-tokens');
    const tokens = tokenize('"name": "Widget"');

    for (const search of ['', undefined]) {
        const marked = plain(highlight(tokens, search));

        assert.equal(texts(marked), '"name": "Widget"');
        assert.equal(marked.some((piece) => piece.hit), false);
        assert.equal(marked.length, tokens.length);
    }
});

const kinds = (rows) => plain(rows).map((row) => row.kind);

test('line diff: identical inputs give only same rows', () => {
    const {diff} = load('model/line-diff');
    const rows = plain(diff(['a', 'b', 'c'], ['a', 'b', 'c']));

    assert.deepEqual(kinds(rows), ['same', 'same', 'same']);
    assert.deepEqual(rows[1], {kind: 'same', left: 'b', right: 'b'});
});

test('line diff: one changed line is one change row', () => {
    const {diff} = load('model/line-diff');
    const rows = plain(diff(['a', 'b', 'c'], ['a', 'x', 'c']));

    assert.deepEqual(kinds(rows), ['same', 'change', 'same']);
    assert.deepEqual(rows[1], {kind: 'change', left: 'b', right: 'x'});
});

test('line diff: a line added at the start or the end is one add row', () => {
    const {diff} = load('model/line-diff');

    assert.deepEqual(plain(diff(['a', 'b'], ['n', 'a', 'b'])), [
        {kind: 'add', left: null, right: 'n'},
        {kind: 'same', left: 'a', right: 'a'},
        {kind: 'same', left: 'b', right: 'b'}
    ]);
    assert.deepEqual(plain(diff(['a', 'b'], ['a', 'b', 'n'])), [
        {kind: 'same', left: 'a', right: 'a'},
        {kind: 'same', left: 'b', right: 'b'},
        {kind: 'add', left: null, right: 'n'}
    ]);
});

test('line diff: a removed line is a del row', () => {
    const {diff} = load('model/line-diff');

    assert.deepEqual(plain(diff(['a', 'b', 'c'], ['a', 'c'])), [
        {kind: 'same', left: 'a', right: 'a'},
        {kind: 'del', left: 'b', right: null},
        {kind: 'same', left: 'c', right: 'c'}
    ]);
});

test('line diff: an empty side makes every row an add or a del', () => {
    const {diff} = load('model/line-diff');

    assert.deepEqual(plain(diff([], ['a', 'b'])), [
        {kind: 'add', left: null, right: 'a'},
        {kind: 'add', left: null, right: 'b'}
    ]);
    assert.deepEqual(plain(diff(['a', 'b'], [])), [
        {kind: 'del', left: 'a', right: null},
        {kind: 'del', left: 'b', right: null}
    ]);
    assert.deepEqual(plain(diff([], [])), []);
});

test('line diff: past the size limit lines are paired by position', () => {
    const {diff} = load('model/line-diff');
    const lines = (count) => Array.from({length: count}, (_, index) => `line ${index}`);

    // (10 + 1) x (11 + 1) cells: aligned by common subsequence, the insertion is a single add row
    const small = kinds(diff(lines(10), ['inserted', ...lines(10)]));

    assert.equal(small.filter((kind) => kind === 'add').length, 1);
    assert.equal(small.filter((kind) => kind === 'change').length, 0);
    assert.equal(small.filter((kind) => kind === 'same').length, 10);

    // (2001 + 1) x (2002 + 1) cells is over four million: rows are compared at the same position, so the shift by one makes every row differ, the last one against nothing
    const large = diff(lines(2001), ['inserted', ...lines(2001)]);
    const largeKinds = kinds(large);

    assert.equal(large.length, 2002);
    assert.equal(largeKinds.filter((kind) => kind === 'add').length, 0);
    assert.equal(largeKinds.filter((kind) => kind === 'change').length, 2002);
    assert.equal(largeKinds.filter((kind) => kind === 'same').length, 0);
    assert.deepEqual(plain(large[0]), {kind: 'change', left: 'line 0', right: 'inserted'});
    assert.deepEqual(plain(large[2001]), {kind: 'change', left: null, right: 'line 2000'});
});

/**
 * A page of the body response: `size` lines per page, `total` lines in all
 *
 * @param {number} size
 * @param {number} total
 * @param {number} page
 * @returns {{lines: Array<{n: number, text: string}>, page: number, pages: number, totalLines: number}}
 */
function bodyPage(size, total, page) {
    const pages = Math.max(1, Math.ceil(total / size));
    const first = (page - 1) * size;
    const count = Math.max(0, Math.min(size, total - first));

    return {
        lines: Array.from({length: count}, (_, index) => ({n: first + index + 1, text: ''})),
        page,
        pages,
        totalLines: total
    };
}

test('body paging: the page size is read from the page received, whatever the server serves', () => {
    const {pageSize} = load('model/body-paging');

    for (const size of [50, 500, 1000]) {
        assert.equal(pageSize(bodyPage(size, size * 3 + 7, 1)), size);
        assert.equal(pageSize(bodyPage(size, size * 3 + 7, 2)), size);
        assert.equal(pageSize(bodyPage(size, size * 3 + 7, 4)), size);
    }
});

test('body paging: a body of one page needs no size to place its lines', () => {
    const {pageOfLine} = load('model/body-paging');

    assert.equal(pageOfLine(bodyPage(500, 120, 1), 120), 1);
    assert.equal(pageOfLine({lines: [], page: 1, pages: 1}, 5), 1);
});

test('body paging: a matching line is placed on its page for a page size other than 500', () => {
    const {pageOfLine} = load('model/body-paging');
    const first = bodyPage(100, 1000, 1);
    const third = bodyPage(100, 1000, 3);

    assert.equal(pageOfLine(first, 1), 1);
    assert.equal(pageOfLine(first, 100), 1);
    assert.equal(pageOfLine(first, 101), 2);
    assert.equal(pageOfLine(first, 1000), 10);
    assert.equal(pageOfLine(third, 250), 3);
    assert.equal(pageOfLine(third, 901), 10);
});

test('body paging: the page is kept within the pages the body has', () => {
    const {pageOfLine} = load('model/body-paging');

    assert.equal(pageOfLine(bodyPage(100, 250, 1), 9999), 3);
    assert.equal(pageOfLine(bodyPage(100, 250, 1), 0), 1);
});

test('json response: a successful response gives its parsed body', async () => {
    const {read} = load('model/json-response');
    const response = {ok: true, status: 200, json: () => Promise.resolve({candidates: []})};

    assert.deepEqual(plain(await read(response)), {candidates: []});
});

test('json response: a failed response is rejected with its status and its body is not parsed', async () => {
    const {read} = load('model/json-response');
    let parsed = false;
    const response = {
        ok: false,
        status: 500,
        json: () => {
            parsed = true;

            return Promise.resolve({message: 'boom'});
        }
    };

    await assert.rejects(read(response), (error) => error.message === '500');
    assert.equal(parsed, false);
});
