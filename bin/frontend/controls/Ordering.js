/**
 * @module package/quiqqer/order/bin/frontend/controls/Ordering
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Ajax
 * @require Locale
 */
define('package/quiqqer/order/bin/frontend/controls/Ordering', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, QUIAjax, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/Ordering',

        Binds: [
            'next',
            'previous',
            '$onImport',
            '$onInject',
            '$onNextClick',
            '$onPreviousClick'
        ],

        options: {
            orderId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form          = null;
            this.$StepContainer = null;
            this.$Timeline      = null;

            this.$Buttons  = null;
            this.$Next     = null;
            this.$Previous = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.$Buttons       = this.getElm().getElement('.quiqqer-order-ordering-buttons');
            this.$StepContainer = this.getElm().getElement('.quiqqer-order-ordering-step');
            this.$Timeline      = this.getElm().getElement('.quiqqer-order-ordering-timeline');

            this.$Next     = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-next');
            this.$Previous = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-previous');

            this.$Next.addEvent('click', this.$onNextClick);
            this.$Previous.addEvent('click', this.$onPreviousClick);

            this.$Form = this.getElm().getElement('[name="order"]');

            this.setAttribute('orderId', parseInt(this.$Form.elements.orderId.value));
        },

        /**
         * event: on import
         */
        $onInject: function () {
            console.log(this.getElm());
        },

        /**
         * Next step
         */
        next: function () {
            var self = this;

            this.$beginResultRendering();

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getNext', function (result) {
                    self.$renderResult(result).then(resolve);
                }, {
                    'package': 'quiqqer/order',
                    orderId  : self.getAttribute('orderId'),
                    current  : self.getAttribute('current'),
                    onError  : reject
                });
            });
        },

        /**
         * Previous step
         */
        previous: function () {
            var self = this;

            this.$beginResultRendering(false);

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getPrevious', function (result) {
                    self.$renderResult(result, false).then(resolve);
                }, {
                    'package': 'quiqqer/order',
                    orderId  : self.getAttribute('orderId'),
                    current  : self.getAttribute('current')
                });
            });
        },

        /**
         * Render the result of an next / previous request
         *
         * @param {object} result
         * @param {boolean} [showFromRight]
         * @return {Promise}
         */
        $renderResult: function (result, showFromRight) {
            var Ghost = new Element('div', {
                html: result.html
            });

            if (typeof showFromRight === 'undefined') {
                showFromRight = true;
            }

            this.setAttribute('current', result.step);
            this.$refreshSteps();

            var Next = new Element('div', {
                html  : Ghost.getElement('.quiqqer-order-ordering-step').get('html'),
                styles: {
                    'float' : 'left',
                    left    : showFromRight ? '100%' : '-100%',
                    opacity : 0,
                    position: 'relative',
                    top     : 0,
                    width   : '100%'
                }
            });

            Next.inject(this.$StepContainer);

            var Prom1 = this.$animate(Next, {
                left   : 0,
                opacity: 1
            }, {
                duration: 500
            });

            var Prom2 = this.$animate(this.$StepContainer, {
                height: Next.getSize().y
            }, {
                duration: 500
            });

            return Promise.all([
                Prom1,
                Prom2
            ]);
        },

        /**
         * Helper function for rendering
         * Execution at the beginning of the result rendering
         *
         * @param {Boolean} [hideToLeft] - In which direction should be hidden
         * @return {Promise}
         */
        $beginResultRendering: function (hideToLeft) {
            var Container = this.$StepContainer.getFirst();

            if (typeof hideToLeft === 'undefined') {
                hideToLeft = true;
            }

            this.$StepContainer.setStyles({
                height  : this.$StepContainer.getSize().y,
                overflow: 'hidden',
                width   : '100%'
            });

            Container.setStyles({
                left    : 0,
                position: 'absolute',
                top     : 0,
                width   : '100%'
            });

            return this.$animate(Container, {
                left   : hideToLeft ? '-100%' : '100%',
                opacity: 0
            }, {
                duration: 500
            }).then(function () {
                Container.destroy();
            });
        },

        /**
         * Refresh the step display
         */
        $refreshSteps: function () {
            var current = this.getAttribute('current');
            var list    = this.$Timeline.getElements('li');

            list.removeClass('current');
            list.removeClass('active');

            for (var i = 0, len = list.length; i < len; i++) {
                list[i].addClass('active');

                if (list[i].get('data-step') === current) {
                    list[i].removeClass('active');
                    list[i].addClass('current');
                    return;
                }
            }
        },

        /**
         * event: on next click
         *
         * @param event
         */
        $onNextClick: function (event) {
            event.stop();
            this.next();
        },

        /**
         * event: on previous click
         * @param event
         */
        $onPreviousClick: function (event) {
            event.stop();
            this.previous();
        },

        /**
         * Animating
         *
         * @param {HTMLElement} Elm
         * @param {object} styles
         * @param {object} options
         * @return {Promise}
         */
        $animate: function (Elm, styles, options) {
            options = options || {};

            return new Promise(function (resolve) {
                options.callback = resolve;
                moofx(Elm).animate(styles, options);
            });
        }
    });
});