/**
 * @module package/quiqqer/order/bin/frontend/controls/Ordering
 *
 * @require qui/QUI
 * @require qui/controls/Control
 */
define('package/quiqqer/order/bin/frontend/controls/Ordering', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/Ordering',

        Binds: [
            '$onImport',
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            console.warn('Ordering import');
        },

        /**
         * event: on import
         */
        $onInject: function () {

        }
    });
});