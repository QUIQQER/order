/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/Checkout
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/Checkout', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/orderProcess/Checkout',

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var links = this.getElm().getElements(
                '.quiqqer-order-step-checkout-notice a'
            );

            var click = function (event) {
                var Target = event.target;

                if (Target.nodeName !== 'A') {
                    Target = Target.getParent('a');
                }

                var project = Target.get('data-project'),
                    lang    = Target.get('data-lang'),
                    id      = Target.get('data-id');

                if (project === '' || lang === '' || id === '') {
                    return;
                }

                event.stop();

                require(['package/quiqqer/controls/bin/site/Window'], function (Win) {
                    new Win({
                        showTitle: true,
                        project  : project,
                        lang     : lang,
                        id       : id
                    }).open();
                });

            };

            links.addEvent('click', click);
        }
    });
});