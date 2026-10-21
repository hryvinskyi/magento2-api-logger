/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The REST call panel of the activity page: request and response tabs whose bodies load when first shown, the
 * export menu, and the replay and compare dialogs. Wording and URLs come from the panel's config.
 */
define([
    'jquery',
    'Hryvinskyi_ApiLogger/js/view/body-viewer',
    'Hryvinskyi_ApiLogger/js/model/line-diff',
    'Hryvinskyi_ApiLogger/js/model/json-response',
    'Hryvinskyi_ApiLogger/js/model/single-flight',
    'Hryvinskyi_ApiLogger/js/model/body-comparison',
    'Magento_Ui/js/modal/modal'
], function ($, bodyViewer, lineDiff, jsonResponse, singleFlight, bodyComparison) {
    'use strict';

    var TIMEOUT_MS = 35000;

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
     * @param {string} tag
     * @param {string} className
     * @param {string} [text]
     * @returns {HTMLElement}
     */
    function node(tag, className, text) {
        var element = document.createElement(tag);

        element.className = className;
        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    }

    /**
     * @param {string} url
     * @param {Object} [options]
     * @returns {Promise<Object>}
     */
    function request(url, options) {
        var controller = new AbortController(),
            timer = window.setTimeout(function () {
                controller.abort();
            }, TIMEOUT_MS);

        return fetch(url, $.extend({
            credentials: 'same-origin',
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: controller.signal
        }, options || {})).then(jsonResponse.read).finally(function () {
            window.clearTimeout(timer);
        });
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
     * @param {?number} ms
     * @returns {string}
     */
    function duration(ms) {
        return ms === null || ms === undefined ? '—' : Math.round(ms).toLocaleString() + ' ms';
    }

    return function (config, element) {
        var labels = config.labels || {},
            viewers = {};

        /**
         * @param {string} tab
         */
        function showTab(tab) {
            element.querySelectorAll('[data-role="tab"]').forEach(function (button) {
                button.setAttribute('aria-selected', String(button.getAttribute('data-tab') === tab));
            });
            element.querySelectorAll('[data-role="pane"]').forEach(function (pane) {
                pane.hidden = pane.getAttribute('data-tab') !== tab;
            });
            if (viewers[tab]) {
                viewers[tab].load();
            }
        }

        element.querySelectorAll('[data-role="body"]').forEach(function (body) {
            viewers[body.getAttribute('data-part')] = bodyViewer(body, config);
        });

        element.querySelectorAll('[data-role="tab"]').forEach(function (button) {
            button.addEventListener('click', function () {
                showTab(button.getAttribute('data-tab'));
            });
        });

        element.querySelectorAll('[data-role="url"]').forEach(function (url) {
            url.addEventListener('click', function () {
                url.classList.toggle('rest-panel__url--open');
            });
        });

        (function exportMenu() {
            var toggle = element.querySelector('[data-role="export-toggle"]'),
                menu = element.querySelector('[data-role="export-menu"]');

            /**
             * @param {boolean} open
             */
            function setOpen(open) {
                menu.hidden = !open;
                toggle.setAttribute('aria-expanded', String(open));
            }

            if (!toggle || !menu) {
                return;
            }
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                setOpen(menu.hidden);
            });
            document.addEventListener('click', function () {
                setOpen(false);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });
        }());

        (function replayDialog() {
            var button = element.querySelector('[data-role="replay"]'),
                dialog = element.querySelector('[data-role="replay-dialog"]'),
                result = dialog ? dialog.querySelector('[data-role="replay-result"]') : null,
                sendButton = null,
                $dialog,
                replayOnce = singleFlight.singleFlight(send, function (busy) {
                    button.disabled = busy;
                    if (sendButton) {
                        sendButton.disabled = busy;
                    }
                });

            /**
             * @param {Object} data
             */
            function show(data) {
                var stats = node('div', 'rest-replay__stats');

                result.textContent = '';
                result.hidden = false;
                if (data.error) {
                    result.appendChild(node('p', 'rest-warning', data.error));

                    return;
                }
                [[labels.original, data.original], [labels.replayed, data.replay]].forEach(function (side) {
                    var box = node('div', 'rest-replay__stat');

                    box.appendChild(node('span', 'rest-replay__label', side[0]));
                    box.appendChild(node('strong', 'rest-replay__value', String(side[1].status || '—')));
                    box.appendChild(node('span', 'rest-replay__label', duration(side[1].durationMs)));
                    stats.appendChild(box);
                });
                result.appendChild(stats);
                result.appendChild(node('pre', 'rest-replay__body', data.replay.body));
            }

            /**
             * @returns {Promise<void>} Settles once the answer, or the failure, is shown
             */
            function send() {
                var body = new FormData();

                body.append('form_key', config.formKey);
                result.hidden = false;
                result.textContent = labels.sending;

                return request(config.replayUrl, {method: 'POST', body: body}).then(show, function () {
                    show({error: labels.replayFailed});
                });
            }

            if (!button || !dialog) {
                return;
            }
            dialog.hidden = false;
            $dialog = $(dialog).modal({
                type: 'popup',
                title: dialog.getAttribute('data-title'),
                autoOpen: false,
                buttons: [{
                    text: labels.cancel,
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                }, {
                    text: labels.replay,
                    class: 'action-primary',
                    click: function (event) {
                        var run;

                        sendButton = event.currentTarget;
                        run = replayOnce();
                        if (run !== null) {
                            run.catch(function () {
                                show({error: labels.replayFailed});
                            });
                        }
                    }
                }]
            });
            button.addEventListener('click', function () {
                result.hidden = true;
                $dialog.modal('openModal');
            });
        }());

        (function compareDialog() {
            var button = element.querySelector('[data-role="compare"]'),
                dialog = element.querySelector('[data-role="compare-dialog"]'),
                rows = dialog ? dialog.querySelector('[data-role="compare-rows"]') : null,
                search = dialog ? dialog.querySelector('[data-role="compare-search"]') : null,
                diffArea = dialog ? dialog.querySelector('[data-role="compare-diff"]') : null,
                picked = null,
                searchTimer = null,
                sameBodyNotes = {},
                $dialog;

            sameBodyNotes[bodyComparison.IDENTICAL] = labels.identical;
            sameBodyNotes[bodyComparison.IDENTICAL_PREFIX] = labels.identicalInFirst;

            /**
             * @param {Object} side One call of the pair
             * @param {string} part "request" or "response"
             * @returns {{text: string, cut: boolean}}
             */
            function body(side, part) {
                return {text: String(side[part] || ''), cut: side[part + 'Cut'] === true};
            }

            /**
             * @param {string} text
             * @returns {HTMLElement} A row spanning the candidate table that carries one message
             */
            function messageRow(text) {
                var row = node('tr', ''),
                    cell = node('td', 'rest-candidates__empty', text);

                cell.colSpan = 6;
                row.appendChild(cell);

                return row;
            }

            /**
             * @param {Array<Object>} candidates
             */
            function showCandidates(candidates) {
                rows.textContent = '';
                if (!candidates || candidates.length === 0) {
                    rows.appendChild(messageRow(labels.noCandidates));

                    return;
                }
                candidates.forEach(function (candidate) {
                    var row = node('tr', 'rest-candidates__row'),
                        pick = node('td', ''),
                        radio = document.createElement('input');

                    radio.type = 'radio';
                    radio.name = 'rest-compare';
                    radio.value = String(candidate.id);
                    radio.addEventListener('change', function () {
                        picked = candidate.id;
                    });
                    pick.appendChild(radio);
                    row.appendChild(pick);
                    [candidate.time, candidate.actor, String(candidate.status || '—'), duration(candidate.durationMs),
                        candidate.correlation].forEach(function (value) {
                        row.appendChild(node('td', '', value));
                    });
                    row.addEventListener('click', function () {
                        radio.checked = true;
                        picked = candidate.id;
                    });
                    rows.appendChild(row);
                });
            }

            function loadCandidates() {
                rows.textContent = '';
                rows.appendChild(messageRow(labels.loading));
                request(withQuery(config.compareUrl, {q: search.value.trim()})).then(function (data) {
                    showCandidates(data.candidates || []);
                }, function () {
                    rows.textContent = '';
                    rows.appendChild(messageRow(labels.loadFailed));
                });
            }

            /**
             * @param {string} title
             * @param {{text: string, cut: boolean}} left
             * @param {{text: string, cut: boolean}} right
             * @returns {HTMLElement}
             */
            function diffTable(title, left, right) {
                var section = node('section', 'rest-diff__section'),
                    table = node('table', 'rest-diff__table'),
                    verdict = bodyComparison.verdict(left, right),
                    changes;

                section.appendChild(node('h3', 'rest-heading', title));
                if (verdict !== bodyComparison.DIFFERENT) {
                    section.appendChild(node('p', 'activity-section__note', sameBodyNotes[verdict]));

                    return section;
                }
                changes = lineDiff.diff(
                    left.text === '' ? [] : left.text.split('\n'),
                    right.text === '' ? [] : right.text.split('\n')
                );
                changes.forEach(function (change) {
                    var row = node('tr', 'rest-diff__row rest-diff__row--' + change.kind);

                    row.appendChild(node('td', 'rest-diff__cell', change.left === null ? '' : change.left));
                    row.appendChild(node('td', 'rest-diff__cell', change.right === null ? '' : change.right));
                    table.appendChild(row);
                });
                section.appendChild(table);

                return section;
            }

            /**
             * @param {Object} data
             */
            function showDiff(data) {
                var summary = node('table', 'rest-diff__summary'),
                    head = node('tr', '');

                diffArea.textContent = '';
                diffArea.hidden = false;
                if (data.error) {
                    diffArea.appendChild(node('p', 'rest-warning', data.error));

                    return;
                }
                head.appendChild(node('th', '', ''));
                head.appendChild(node('th', '', labels.thisCall));
                head.appendChild(node('th', '', format(labels.otherCall, [data.other.id])));
                summary.appendChild(head);
                [[labels.status, 'status'], [labels.duration, 'durationMs']].forEach(function (field) {
                    var row = node('tr', '');

                    row.appendChild(node('th', '', field[0]));
                    row.appendChild(node('td', '', field[1] === 'durationMs'
                        ? duration(data.current.durationMs) : String(data.current.status || '—')));
                    row.appendChild(node('td', '', field[1] === 'durationMs'
                        ? duration(data.other.durationMs) : String(data.other.status || '—')));
                    summary.appendChild(row);
                });
                diffArea.appendChild(summary);
                diffArea.appendChild(diffTable(
                    labels.responseBody,
                    body(data.current, 'response'),
                    body(data.other, 'response')
                ));
                diffArea.appendChild(diffTable(
                    labels.requestBody,
                    body(data.current, 'request'),
                    body(data.other, 'request')
                ));
            }

            if (!button || !dialog) {
                return;
            }
            dialog.hidden = false;
            $dialog = $(dialog).modal({
                type: 'slide',
                title: dialog.getAttribute('data-title'),
                autoOpen: false,
                buttons: [{
                    text: labels.cancel,
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                }, {
                    text: labels.compare,
                    class: 'action-primary',
                    click: function () {
                        if (picked === null) {
                            diffArea.hidden = false;
                            diffArea.textContent = labels.pickFirst;

                            return;
                        }
                        request(withQuery(config.compareUrl, {other: picked})).then(showDiff, function () {
                            showDiff({error: labels.loadFailed});
                        });
                    }
                }]
            });
            search.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(loadCandidates, 300);
            });
            button.addEventListener('click', function () {
                picked = null;
                diffArea.hidden = true;
                $dialog.modal('openModal');
                loadCandidates();
            });
        }());

        showTab('request');
    };
});
