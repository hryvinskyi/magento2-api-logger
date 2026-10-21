/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Turns a fetch response into its JSON body, or into a rejection when the server did not answer with success, so a
 * caller's failure branch runs instead of a success branch fed an error page. Pure: only the response it is given.
 */
define([], function () {
    'use strict';

    /**
     * @param {{ok: boolean, status: number, json: function(): Promise<*>}} response
     * @returns {Promise<*>} The parsed body; rejected with an Error carrying the HTTP status when the response is not ok
     */
    function read(response) {
        return response.ok ? response.json() : Promise.reject(new Error(String(response.status)));
    }

    return Object.freeze({read: read});
});
