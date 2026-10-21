/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

/**
 * Loads one of the module's AMD files in Node. Its dependencies are other module files, loaded the same way;
 * a pure module needs nothing else, which is what these tests prove.
 */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const WEB_JS = path.resolve(__dirname, '../../view/adminhtml/web/js');
const PREFIX = 'Hryvinskyi_ApiLogger/js/';
const cache = new Map();

/**
 * @param {string} id e.g. "Hryvinskyi_ApiLogger/js/model/line-diff" or "model/line-diff"
 * @returns {*} What the module's factory returns
 */
function load(id) {
    const relative = id.startsWith(PREFIX) ? id.slice(PREFIX.length) : id;

    if (cache.has(relative)) {
        return cache.get(relative);
    }

    const file = path.join(WEB_JS, relative + '.js');
    let exported;
    const sandbox = {
        define: (deps, factory) => {
            exported = factory(...deps.map((dep) => {
                if (!dep.startsWith(PREFIX)) {
                    throw new Error(`${relative} depends on ${dep}, which a pure module must not`);
                }

                return load(dep);
            }));
        },
        URLSearchParams,
        Object,
        Array,
        Number,
        String,
        Error,
        isFinite,
        Infinity
    };

    vm.runInNewContext(fs.readFileSync(file, 'utf8'), sandbox, {filename: file});
    cache.set(relative, exported);

    return exported;
}

module.exports = {load};
