/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Runs an asynchronous task one at a time: while a run is pending, asking again does nothing, so a second click
 * cannot send a request twice. Pure: it touches nothing but the task and the callback it is given.
 */
define([], function () {
    'use strict';

    /**
     * @param {function(): (Promise<*>|*)} task
     * @param {function(boolean): void} onBusyChange Told true when a run starts and false when it ends, whether the
     *     task succeeded, failed or threw
     * @returns {function(): ?Promise<*>} Starts a run and answers with its promise, or answers null while one is pending
     */
    function singleFlight(task, onBusyChange) {
        var pending = false;

        return function run() {
            if (pending) {
                return null;
            }
            pending = true;
            onBusyChange(true);

            return new Promise(function (resolve) {
                resolve(task());
            }).finally(function () {
                pending = false;
                onBusyChange(false);
            });
        };
    }

    return Object.freeze({singleFlight: singleFlight});
});
