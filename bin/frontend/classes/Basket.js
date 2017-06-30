/**
 * @module package/quiqqer/order/bin/frontend/classes/Basket
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 */
define('package/quiqqer/order/bin/frontend/classes/Basket', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/order/bin/frontend/classes/Basket',

        initialize: function (options) {
            this.parent(options);
        },

        loadLastOrder: function () {

        },

        getOrder: function () {

        },

        addArticle: function () {

        }

    });
});