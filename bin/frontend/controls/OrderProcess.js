/**
 * @module package/quiqqer/order/bin/frontend/controls/OrderProcess
 */

require.config({
    paths: {
        'Navigo'       : URL_OPT_DIR + 'bin/navigo/lib/navigo.min',
        'HistoryEvents': URL_OPT_DIR + 'bin/history-events/dist/history-events.min'
    }
});

define('package/quiqqer/order/bin/frontend/controls/OrderProcess', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',
    'package/quiqqer/order/bin/frontend/Basket',
    'package/quiqqer/order/bin/frontend/Orders',
    'Ajax',
    'Locale',
    'Navigo',
    'HistoryEvents'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, Basket, Orders, QUIAjax, QUILocale, Navigo) {
    "use strict";

    var lg = 'quiqqer/order';
    var Router, url;

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/frontend/controls/OrderProcess',

        Binds: [
            'next',
            'previous',
            '$onImport',
            '$onInject',
            '$onNextClick',
            '$onPreviousClick',
            '$onChangeState'
        ],

        options: {
            orderId   : false,
            buttons   : true,
            showLoader: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form          = null;
            this.$StepContainer = null;
            this.$Timeline      = null;

            this.$Buttons  = null;
            this.$Next     = null;
            this.$Previous = null;
            this.Loader    = new QUILoader();

            this.Loader.addEvents({
                onShow: function () {
                    this.fireEvent('loaderShow', [this]);
                }.bind(this),
                onHide: function () {
                    this.fireEvent('loaderHide', [this]);
                }.bind(this)
            });

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });

            window.addEventListener('changestate', this.$onChangeState, false);
        },

        /**
         * @event on change state
         */
        $onChangeState: function () {
            var pathName = window.location.pathname;

            if (pathName.indexOf(url) === -1) {
                return;
            }

            var parts = pathName.trim().split('/').filter(function (value) {
                return value !== '';
            });

            if (parts.length === 1) {
                this.openFirstStep();
                return;
            }

            var current = this.getCurrentStepData();

            if (current.step !== parts[1]) {
                this.openStep(parts[1]);
            }
        },

        /**
         * event: on import
         */
        $onImport: function () {
            if (this.getAttribute('showLoader')) {
                this.Loader.inject(this.getElm());
            }

            url = this.getElm().get('data-url');

            if (window.location.pathname.indexOf(url) === 0) {
                Router = new Navigo(null, false, '');

                // workaround - dont know why its needed, but its needed :D
                Router.on(url + '/*', function () {
                });
            }

            this.$Buttons       = this.getElm().getElement('.quiqqer-order-ordering-buttons');
            this.$StepContainer = this.getElm().getElement('.quiqqer-order-ordering-step');
            this.$Timeline      = this.getElm().getElement('.quiqqer-order-ordering-timeline');
            this.$Form          = this.getElm().getElement('[name="order"]');

            if (this.getAttribute('buttons') === false) {
                this.$Buttons.setStyle('display', 'none');
            }

            this.$refreshButtonEvents();

            this.setAttribute('orderId', parseInt(this.$Form.elements.orderId.value));
            this.setAttribute('current', this.$Timeline.getFirst('ul li').get('data-step'));

            this.fireEvent('load', [this]);

            this.getElm().addClass('quiqqer-order-ordering');
        },

        /**
         * event: on import
         */
        $onInject: function () {
            if (!this.getAttribute('orderId')) {
                //this.setAttribute('orderId', Basket.getCurrentOrderId());
            }

            var self = this;
            var Prom = new Promise(function () {
                return self.setAttribute('orderId');
            });

            if (!this.getAttribute('orderId')) {
                Prom = Orders.getLastOrder().then(function (order) {
                    self.setAttribute('orderId', order.id);
                    return order.id;
                });
            }


            Prom.then(function () {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getControl', function (html) {
                    self.getElm().set('html', html);
                    self.$onImport();

                    self.fireEvent('load', [self]);
                }, {
                    'package': 'quiqqer/order',
                    orderId  : self.getAttribute('orderId')
                });
            });
        },

        /**
         * Next step
         *
         * @return {Promise}
         */
        next: function () {
            var self = this;

            if (!parseInt(this.$Form.get('data-products-count'))) {
                return Promise.resolve();
            }

            this.$beginResultRendering();

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getNext', function (result) {
                        self.$renderResult(result).then(resolve);

                        if (Router) {
                            Router.navigate(url + '/' + self.getAttribute('current'));
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderId  : self.getAttribute('orderId'),
                        current  : self.getAttribute('current'),
                        onError  : reject
                    });
                });
            });
        },

        /**
         * Previous step
         *
         * @return {Promise}
         */
        previous: function () {
            var self = this;

            if (!parseInt(this.$Form.get('data-products-count'))) {
                return Promise.resolve();
            }

            this.$beginResultRendering(false);

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getPrevious', function (result) {
                        self.$renderResult(result, false).then(resolve);

                        if (Router) {
                            Router.navigate(url + '/' + self.getAttribute('current'));
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderId  : self.getAttribute('orderId'),
                        current  : self.getAttribute('current')
                    });
                });
            });
        },

        /**
         * Send the ordering process
         */
        send: function () {
            var self = this;

            if (!parseInt(this.$Form.get('data-products-count'))) {
                return Promise.resolve();
            }

            this.$beginResultRendering();

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_send', function (result) {
                        self.$renderResult(result).then(resolve);

                        if (Router) {
                            Router.navigate(url + '/' + self.getAttribute('current'));
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderId  : self.getAttribute('orderId'),
                        current  : self.getAttribute('current'),
                        onError  : reject
                    });
                });
            });
        },

        /**
         * Opens the wanted step
         *
         * @param {String} step - Name of the step
         * @return {Promise}
         */
        openStep: function (step) {
            var self = this;

            if (!parseInt(this.$Form.get('data-products-count'))) {
                var FirstLi = this.$Timeline.getElement('li:first-child');
                step        = FirstLi.get('data-step');
            }

            this.$beginResultRendering();

            this.saveCurrentStep().then(function () {
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getStep', function (result) {
                        self.$renderResult(result).then(resolve);

                        if (Router) {
                            Router.navigate(url + '/' + step);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderId  : self.getAttribute('orderId'),
                        step     : step
                    });
                });
            });
        },

        /**
         * Opens the first step
         *
         * @return {Promise}
         */
        openFirstStep: function () {
            var FirstLi   = this.$Timeline.getElement('li:first-child'),
                firstStep = FirstLi.get('data-step');

            var current = this.getCurrentStepData();

            if (current.step === firstStep) {
                return Promise.resolve();
            }

            return this.openStep(firstStep);
        },

        /**
         * Saves the current step
         *
         * @return {Promise}
         */
        saveCurrentStep: function () {
            var self = this,
                data = QUIFormUtils.getFormData(this.$Form);

            // filter and use only data
            delete data.step;
            delete data.orderId;
            delete data.current;

            var elements = this.$Form.elements;

            for (var n in data) {
                if (!data.hasOwnProperty(n)) {
                    continue;
                }

                if (elements[n].type === 'submit') {
                    delete data[n];
                    continue;
                }

                if (typeOf(elements[n]) === 'collection' &&
                    elements[n][0].type === 'submit') {
                    delete data[n];
                }
            }

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_saveCurrentStep', function () {
                    resolve();
                }, {
                    'package': 'quiqqer/order',
                    orderId  : self.getAttribute('orderId'),
                    step     : self.getAttribute('current'),
                    data     : JSON.encode(data)
                });
            });
        },

        /**
         * Return the data of the current step
         *
         * @return {{icon: string, title: string}}
         */
        getCurrentStepData: function () {
            var current = this.getAttribute('current');
            var Step    = this.$Timeline.getElement('li[data-step="' + current + '"]');

            if (!Step) {
                return {
                    icon : 'fa-shopping-bag',
                    title: QUILocale.get(lg, 'ordering.title'),
                    step : 'basket'
                };
            }

            return {
                icon : Step.get('data-icon'),
                title: Step.getElement('.title').get('text').trim(),
                step : Step.get('data-step')
            };
        },

        /**
         * Render the result of an next / previous request
         *
         * @param {object} result
         * @param {boolean} [showFromRight]
         * @return {Promise}
         */
        $renderResult: function (result, showFromRight) {
            var self = this;

            var Ghost = new Element('div', {
                html: result.html
            });

            if (typeof showFromRight === 'undefined') {
                showFromRight = true;
            }

            this.setAttribute('current', result.step);

            // render container
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

            // render buttons
            this.$Buttons.set(
                'html',
                Ghost.getElement('.quiqqer-order-ordering-buttons').get('html')
            );

            // events & animation
            this.$refreshSteps();
            this.$refreshButtonEvents();

            return QUI.parse(this.$StepContainer).then(function () {
                var Prom1 = self.$animate(Next, {
                    left   : 0,
                    opacity: 1
                }, {
                    duration: 500
                });

                var Prom2 = self.$animate(self.$StepContainer, {
                    height: Next.getSize().y
                }, {
                    duration: 500
                });

                self.Loader.hide();

                return Promise.all([
                    Prom1,
                    Prom2
                ]).then(function () {
                    self.fireEvent('change', [self]);
                });
            });
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

            this.Loader.show();

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
         * Refresh the button events
         */
        $refreshButtonEvents: function () {
            this.$Next     = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-next');
            this.$Previous = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-previous');

            this.$Next.removeEvents('click');
            this.$Previous.removeEvents('click');

            this.$Next.addEvent('click', this.$onNextClick);
            this.$Previous.addEvent('click', this.$onPreviousClick);

            var self = this,
                list = this.$Timeline.getElements('li');

            list.removeEvents('click');

            list.addEvent('click', function (event) {
                event.stop();

                var Target = event.target;

                if (Target.nodeName !== 'LI') {
                    Target = Target.getParent('li');
                }

                if (Target.get('data-step') === 'finish') {
                    return;
                }

                self.openStep(Target.get('data-step'));
            });
        },

        /**
         * event: on next click
         *
         * @param event
         */
        $onNextClick: function (event) {
            event.stop();

            if (event.target.get('value') === 'payableToOrder') {
                this.send();
                return;
            }

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