/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The endpoint picker of the REST logging settings: search, group toggles, select all or none, and the hidden
 * input that carries the chosen "METHOD|/route" values, keeping stored values that have no checkbox. Greyed out
 * while the value is inherited.
 */
define(['Hryvinskyi_ApiLogger/js/model/endpoint-selection'], function (selection) {
    'use strict';

    /**
     * @param {{inputId: string}} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var search = element.querySelector('[data-role="search"]'),
            value = element.querySelector('[data-role="value"]'),
            selectedCount = element.querySelector('[data-role="selected-count"]'),
            visibleCount = element.querySelector('[data-role="visible-count"]'),
            noResults = element.querySelector('[data-role="no-results"]'),
            groups = Array.prototype.slice.call(element.querySelectorAll('[data-role="group"]')),
            collapsedBySearch = new WeakMap(),
            row = value ? value.closest('tr') : null,
            inherit = row ? row.querySelector('.use-default input[type="checkbox"]') : null;

        /**
         * @param {HTMLElement} scope
         * @param {boolean} onlyVisible
         * @returns {Array<HTMLInputElement>}
         */
        function boxes(scope, onlyVisible) {
            return Array.prototype.filter.call(scope.querySelectorAll('[data-role="endpoint"]'), function (box) {
                return !onlyVisible || !box.closest('[data-role="item"]').classList.contains('hidden');
            });
        }

        /**
         * @param {HTMLInputElement} box
         * @returns {string}
         */
        function valueOf(box) {
            return box.value;
        }

        /**
         * @param {HTMLInputElement} box
         * @returns {boolean}
         */
        function isChecked(box) {
            return box.checked;
        }

        function refresh() {
            var all = boxes(element, false),
                saved = selection.chosen(value.value, all.map(valueOf), all.filter(isChecked).map(valueOf));

            value.value = saved.value;
            selectedCount.textContent = String(saved.count);

            groups.forEach(function (group) {
                var visible = boxes(group, true),
                    state = selection.groupState(visible.length, visible.filter(isChecked).length),
                    toggle = group.querySelector('[data-role="group-toggle"]');

                group.querySelector('[data-role="group-count"]').textContent = String(
                    boxes(group, false).filter(isChecked).length
                );
                toggle.checked = state.checked;
                toggle.indeterminate = state.indeterminate;
            });
        }

        function applySearch() {
            var query = search.value,
                shown = 0;

            groups.forEach(function (group) {
                var groupShown = 0;

                group.querySelectorAll('[data-role="item"]').forEach(function (item) {
                    var visible = selection.matches(item.getAttribute('data-search') || '', query);

                    item.classList.toggle('hidden', !visible);
                    groupShown += visible ? 1 : 0;
                });
                group.hidden = groupShown === 0;
                if (!collapsedBySearch.has(group)) {
                    collapsedBySearch.set(group, group.classList.contains('collapsed'));
                }
                group.classList.toggle('collapsed', query.trim() === '' ? collapsedBySearch.get(group) : false);
                shown += groupShown;
            });

            if (query.trim() === '') {
                collapsedBySearch = new WeakMap();
            }
            noResults.classList.toggle('visible', shown === 0);
            visibleCount.textContent = String(shown);
            refresh();
        }

        /**
         * @param {boolean} checked
         * @param {boolean} onlyVisible
         */
        function setAll(checked, onlyVisible) {
            boxes(element, onlyVisible).forEach(function (box) {
                box.checked = checked;
            });
            refresh();
        }

        function applyInheritance() {
            element.classList.toggle('endpoint-selector-disabled', Boolean(inherit && inherit.checked));
        }

        if (!value || !search) {
            return;
        }

        element.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null,
                header = target ? target.closest('[data-role="group-header"]') : null;

            if (header && !target.matches('input')) {
                header.closest('[data-role="group"]').classList.toggle('collapsed');
            }
        });
        element.addEventListener('change', function (event) {
            var target = event.target,
                group;

            if (target.matches('[data-role="group-toggle"]')) {
                group = target.closest('[data-role="group"]');
                boxes(group, true).forEach(function (box) {
                    box.checked = target.checked;
                });
            }
            refresh();
        });
        element.querySelector('[data-role="select-all"]').addEventListener('click', function () {
            setAll(true, true);
        });
        element.querySelector('[data-role="select-none"]').addEventListener('click', function () {
            setAll(false, false);
        });
        element.querySelector('[data-role="expand-all"]').addEventListener('click', function () {
            groups.forEach(function (group) {
                group.classList.remove('collapsed');
            });
        });
        element.querySelector('[data-role="collapse-all"]').addEventListener('click', function () {
            groups.forEach(function (group) {
                group.classList.add('collapsed');
            });
        });
        search.addEventListener('input', applySearch);
        if (inherit) {
            inherit.addEventListener('change', applyInheritance);
            applyInheritance();
        }

        refresh();
    };
});
