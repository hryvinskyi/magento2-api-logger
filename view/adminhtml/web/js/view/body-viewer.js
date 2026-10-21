/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One stored body on the page: numbered, coloured lines loaded a page at a time from the server, a search the server
 * runs over the whole body, copy and download. A newer request always wins over an older one still on its way.
 */
define([
    'Hryvinskyi_ApiLogger/js/model/json-tokens',
    'Hryvinskyi_ApiLogger/js/model/body-paging',
    'Hryvinskyi_ApiLogger/js/model/json-response'
], function (jsonTokens, bodyPaging, jsonResponse) {
    'use strict';

    var TIMEOUT_MS = 15000,
        SEARCH_DELAY_MS = 300;

    /**
     * @param {string} template
     * @param {Array<string|number>} values
     * @returns {string}
     */
    function format(template, values) {
        return values.reduce(function (text, value, index) {
            return text.replace('%' + (index + 1), String(value));
        }, String(template || ''));
    }

    /**
     * @param {string} base
     * @param {Object<string, string|number>} params
     * @returns {string}
     */
    function withQuery(base, params) {
        return base + (base.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams(params).toString();
    }

    /**
     * @param {HTMLElement} element The [data-role="body"] block of one part
     * @param {{bodyUrl: string, labels: Object<string, string>}} config
     * @returns {{load: function(): void}}
     */
    return function (element, config) {
        var part = element.getAttribute('data-part') || 'response',
            lines = element.querySelector('[data-role="body-lines"]'),
            more = element.querySelector('[data-role="body-more"]'),
            note = element.querySelector('[data-role="body-note"]'),
            next = element.querySelector('[data-role="body-next"]'),
            search = element.querySelector('[data-role="body-search"]'),
            hits = element.querySelector('[data-role="body-hits"]'),
            copy = element.querySelector('[data-role="body-copy"]'),
            labels = config.labels || {},
            state = {loaded: false, page: 0, pages: 1, total: 0, search: '', request: 0, controller: null},
            searchTimer = null;

        /**
         * @param {{n: number, text: string}} line
         * @returns {HTMLElement}
         */
        function row(line) {
            var item = document.createElement('div'),
                number = document.createElement('span'),
                code = document.createElement('span');

            item.className = 'rest-body__line';
            number.className = 'rest-body__number';
            number.textContent = String(line.n);
            code.className = 'rest-body__code';
            jsonTokens.highlight(jsonTokens.tokenize(line.text), state.search).forEach(function (token) {
                var piece = document.createElement('span');

                piece.className = 'rest-token rest-token--' + token.type + (token.hit ? ' rest-token--hit' : '');
                piece.textContent = token.text;
                code.appendChild(piece);
            });
            if (state.search !== '' && line.text.toLowerCase().indexOf(state.search.toLowerCase()) !== -1) {
                item.classList.add('rest-body__line--hit');
            }
            item.appendChild(number);
            item.appendChild(code);

            return item;
        }

        /**
         * @param {Object} data
         * @param {boolean} append
         */
        function render(data, append) {
            if (!append) {
                lines.textContent = '';
            }
            data.lines.forEach(function (line) {
                lines.appendChild(row(line));
            });

            state.page = data.page;
            state.pages = data.pages;
            state.total = data.totalLines;
            more.hidden = state.page >= state.pages;
            note.textContent = format(labels.showing, [lines.childElementCount, state.total]);

            if (hits) {
                hits.textContent = state.search === '' ? ''
                    : (data.hits.length === 0 ? labels.noHits
                        : (data.hits.length === 1 ? labels.oneHit : format(labels.hits, [data.hits.length])));
            }
        }

        /**
         * @param {number} page
         * @param {boolean} append
         * @returns {Promise<Object|null>}
         */
        function fetchPage(page, append) {
            var request = ++state.request,
                controller = new AbortController(),
                timer = window.setTimeout(function () {
                    controller.abort();
                }, TIMEOUT_MS);

            if (state.controller) {
                state.controller.abort();
            }
            state.controller = controller;

            return fetch(withQuery(config.bodyUrl, {part: part, page: page, q: state.search}), {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: controller.signal
            }).then(jsonResponse.read).then(function (data) {
                if (request === state.request) {
                    render(data, append);
                }

                return data;
            }).catch(function (error) {
                if (request === state.request && error.name !== 'AbortError') {
                    lines.textContent = labels.loadFailed || '';
                }

                return null;
            }).finally(function () {
                window.clearTimeout(timer);
            });
        }

        /**
         * Search the whole body and show the page with the first matching line
         */
        function runSearch() {
            state.search = search.value.trim();
            fetchPage(1, false).then(function (data) {
                var firstPage = data && data.hits.length ? bodyPaging.pageOfLine(data, data.hits[0]) : 1;

                if (firstPage > 1) {
                    fetchPage(firstPage, false);
                }
            });
        }

        if (!lines) {
            return {
                load: function () {}
            };
        }

        next.addEventListener('click', function () {
            fetchPage(state.page + 1, true);
        });

        search.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(runSearch, SEARCH_DELAY_MS);
        });

        copy.addEventListener('click', function () {
            var text = Array.prototype.map.call(lines.querySelectorAll('.rest-body__code'), function (code) {
                return code.textContent;
            }).join('\n');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    copy.textContent = labels.copied || copy.textContent;
                });
            }
        });

        return {
            load: function () {
                if (!state.loaded) {
                    state.loaded = true;
                    fetchPage(1, false);
                }
            }
        };
    };
});
