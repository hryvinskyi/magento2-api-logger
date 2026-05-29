/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'ko',
    'mage/translate'
], function (Column, ko, $t) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ApiLogger/grid/cells/headers',

            // Number of header rows shown before the "Show all" toggle appears.
            previewLimit: 3
        },

        /**
         * Returns the structured header rows ({key, value}) prepared on the backend.
         *
         * @param {Object} record - Grid row record.
         * @returns {Array}
         */
        getRows: function (record) {
            var rows = record[this.index];

            return Array.isArray(rows) ? rows : [];
        },

        /**
         * Returns the total number of header rows for a record.
         *
         * @param {Object} record - Grid row record.
         * @returns {Number}
         */
        getCount: function (record) {
            return this.getRows(record).length;
        },

        /**
         * Whether the cell holds more rows than the preview limit and can be expanded.
         *
         * @param {Object} record - Grid row record.
         * @returns {Boolean}
         */
        isExpandable: function (record) {
            return this.getCount(record) > this.previewLimit;
        },

        /**
         * Returns (creating on first access) the per-record, per-column expanded flag.
         *
         * Keyed by column index so the request- and response-header columns, which share
         * the same record object, toggle independently.
         *
         * @param {Object} record - Grid row record.
         * @returns {Function} Knockout observable.
         */
        getExpandedObservable: function (record) {
            var key = '_aplogHeadersExpanded_' + this.index;

            if (!record[key]) {
                record[key] = ko.observable(false);
            }

            return record[key];
        },

        /**
         * Whether the cell is currently expanded.
         *
         * @param {Object} record - Grid row record.
         * @returns {Boolean}
         */
        isExpanded: function (record) {
            return this.getExpandedObservable(record)();
        },

        /**
         * Returns the rows that should currently be rendered, honouring the preview limit.
         *
         * @param {Object} record - Grid row record.
         * @returns {Array}
         */
        getVisibleRows: function (record) {
            var rows = this.getRows(record);

            if (!this.isExpandable(record) || this.isExpanded(record)) {
                return rows;
            }

            return rows.slice(0, this.previewLimit);
        },

        /**
         * Returns the label for the expand/collapse toggle.
         *
         * @param {Object} record - Grid row record.
         * @returns {String}
         */
        toggleLabel: function (record) {
            if (this.isExpanded(record)) {
                return $t('Show less');
            }

            return $t('Show all %1 headers').replace('%1', this.getCount(record));
        },

        /**
         * Toggles the expanded state of a cell without triggering the row's field action.
         *
         * @param {Object} record - Grid row record.
         * @param {Object} event - DOM click event.
         * @returns {Boolean} Always false to suppress the default action.
         */
        onToggle: function (record, event) {
            var observable = this.getExpandedObservable(record);

            if (event) {
                event.stopPropagation();
            }

            observable(!observable());

            return false;
        }
    });
});
