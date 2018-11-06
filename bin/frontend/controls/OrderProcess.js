/**
 * @module package/quiqqer/order/bin/frontend/controls/OrderProcess
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event QUI Event: onQuiqqerOrderProcessOpenStep  [this, step]
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
            '$onChangeState',
            '$refreshButtonEvents',
            '$beginResultRendering',
            '$endResultRendering',
            '$onProcessingError'
        ],

        options: {
            orderHash : false,
            buttons   : true,
            showLoader: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form             = null;
            this.$StepContainer    = null;
            this.$Timeline         = null;
            this.$runningAnimation = false;
            this.$isResizing       = false;

            this.$Buttons  = null;
            this.$Next     = null;
            this.$Previous = null;
            this.Loader    = new QUILoader();

            if (!this.getAttribute('orderHash') && Basket.getHash()) {
                this.setAttribute('orderHash', Basket.getHash());
            }

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

                // workaround - don't know why its needed, but its needed :D
                Router.on(url + '/*', function () {
                });
            }

            this.$Buttons           = this.getElm().getElement('.quiqqer-order-ordering-buttons');
            this.$StepContainer     = this.getElm().getElement('.quiqqer-order-ordering-step');
            this.$Timeline          = this.getElm().getElement('.quiqqer-order-ordering-timeline');
            this.$TimelineContainer = this.getElm().getElement('.quiqqer-order-ordering-timeline-container');
            this.$Form              = this.getElm().getElement('[name="order"]');

            if (this.getAttribute('buttons') === false) {
                this.$Buttons.setStyle('display', 'none');
            }

            this.$refreshButtonEvents();

            if (this.$Form.get('data-order-hash') && this.$Form.get('data-order-hash') !== '') {
                this.setAttribute('orderHash', this.$Form.get('data-order-hash'));
            }


            var Current = this.$TimelineContainer.getFirst('ul li.current');

            if (!Current) {
                Current = this.$TimelineContainer.getFirst('ul li');
            }

            this.setAttribute('current', Current.get('data-step'));
            this.fireEvent('load', [this]);

            this.getElm().addClass('quiqqer-order-ordering');
        },

        /**
         * event: on import
         */
        $onInject: function () {
            var self = this;

            this.getElm().set('data-quiid', this.getId());

            var Prom = new Promise(function () {
                return self.getAttribute('orderHash');
            });

            if (!this.getAttribute('orderHash')) {
                Prom = Orders.getLastOrder().then(function (order) {
                    self.setAttribute('orderHash', order.hash);
                    return order.hash;
                });
            }

            Prom.then(function () {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getControl', function (html) {
                    var Process = new Element('div', {
                        html: html
                    }).getElement(
                        '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
                    );

                    self.getElm().set({
                        'data-qui': Process.get('data-qui'),
                        'data-url': Process.get('data-url'),
                        'html'    : Process.get('html')
                    });

                    self.$onImport();

                    self.fireEvent('load', [self]);
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash')
                });
            });
        },

        //region products

        /**
         * add a product to the order process
         *
         * @param {Integer} productId
         * @param {Object} fields
         * @param {Integer} [quantity]
         *
         * @return {Promise}
         */
        addProduct: function (productId, fields, quantity) {
            var self = this;

            quantity = quantity || 1;

            if (!quantity) {
                return Promise.reject('Product need a quantity');
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_addProductToBasketOrder', function () {
                    self.refreshCurrentStep().then(resolve);
                }, {
                    'package': 'quiqqer/order',
                    hash     : self.getAttribute('orderHash'),
                    productId: productId,
                    quantity : quantity,
                    fields   : JSON.encode(fields),
                    onError  : reject
                });
            });
        },

        /**
         *
         * @param {Integer} pos
         *
         * @return {Promise}
         */
        removeProductPos: function (pos) {
            var self = this;

            return Orders.removePosition(
                this.getAttribute('orderHash'),
                pos
            ).then(function () {
                return self.refreshCurrentStep();
            });
        },

        //endregion

        // region API

        /**
         * Return the order hash of the order process
         *
         * @return {Promise}
         */
        getOrder: function () {
            var self     = this,
                oderHash = this.getAttribute('orderHash');

            if (oderHash) {
                return Promise.resolve(oderHash);
            }

            return Orders.getLastOrder().then(function (order) {
                self.setAttribute('orderHash', order.hash);

                return order.hash;
            });
        },

        /**
         * Resize the order process
         */
        resize: function () {
            var self = this;

            if (this.$isResizing) {
                return new Promise(function (resolve) {
                    (function () {
                        self.resize().then(resolve);
                    }).delay(200);
                });
            }

            this.$isResizing = true;

            return new Promise(function (resolve) {
                var Next   = self.$StepContainer.getElement('.quiqqer-order-ordering-step-next');
                var Basket = self.$StepContainer.getElement('.quiqqer-order-step-basket');

                var innerHeight = self.$StepContainer.getChildren().filter(function (Node) {
                    if (Node.nodeName === 'STYLE') {
                        return false;
                    }

                    if (Next && Basket) {
                        return Node.hasClass('quiqqer-order-ordering-step-next');
                    }

                    return true;
                }).getSize().map(function (size) {
                    return size.y;
                }).sum();

                var finish = function () {
                    if (self.$isResizing === false) {
                        return;
                    }

                    self.$isResizing = false;
                    resolve();
                    self.fireEvent('change', [self]);
                };

                moofx(self.$StepContainer).animate({
                    height: innerHeight
                }, {
                    duration: 250,
                    callback: finish
                });

                finish.delay(300);
            });
        },

        /**
         * Next step
         *
         * @return {Promise}
         */
        next: function () {
            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            if (!this.$getCount()) {
                return this.refreshCurrentStep();
            }

            if (this.validateStep() === false) {
                return Promise.reject();
            }

            var self = this;

            this.$beginResultRendering(-1);

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getNext', function (result) {
                        self.$renderResult(result, 1).then(function () {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
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
            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            var self = this;

            if (!this.$getCount()) {
                return Promise.resolve();
            }

            this.$beginResultRendering(1);

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getPrevious', function (result) {
                        self.$renderResult(result, -1).then(function () {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
                        current  : self.getAttribute('current')
                    });
                });
            });
        },

        /**
         * Check / valdate the step
         * html5 validation
         *
         * @return {boolean}
         */
        validateStep: function () {
            // test html5 required
            var Required = this.getElm().getElements('[required]');

            if (Required.length) {
                var i, len, Field;

                for (i = 0, len = Required.length; i < len; i++) {
                    Field = Required[i];

                    if (!("checkValidity" in Field)) {
                        continue;
                    }

                    if (Field.checkValidity()) {
                        continue;
                    }

                    // chrome validate message
                    if ("reportValidity" in Field) {
                        Field.reportValidity();
                        return false;
                    }
                }
            }

            return true;
        },

        /**
         * Send the ordering process
         */
        send: function () {
            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            var self = this;

            if (!this.$getCount()) {
                return Promise.resolve();
            }

            this.Loader.setAttribute('opacity', 1);
            this.Loader.setStyles({
                background: 'rgba(255, 255, 255, 1)'
            });

            this.$beginResultRendering();

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_send', function (result) {
                        self.$renderResult(result).then(function () {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
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
            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            var self = this;

            if (!this.$getCount()) {
                var FirstLi = this.$TimelineContainer.getElement('li:first-child');

                step = FirstLi.get('data-step');
            }

            if (this.getCurrentStepData().step === step) {
                this.$beginResultRendering(0);
            } else {
                this.$beginResultRendering(-1);
            }

            return this.saveCurrentStep().then(function () {
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getStep', function (result) {
                        if (self.getCurrentStepData().step === step) {
                            self.$renderResult(result, 0).then(function () {
                                self.$endResultRendering();
                                resolve();
                            });
                        } else {
                            self.$renderResult(result, 1).then(function () {
                                self.$endResultRendering();
                                resolve();
                            });
                        }

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
                        step     : step
                    });
                });
            });
        },

        /**
         * Refresh the current step
         * Saves the current data and reload it
         *
         * @return {Promise}
         */
        refreshCurrentStep: function () {
            return this.openStep(
                this.getCurrentStepData().step
            );
        },

        /**
         * Reload the basket
         *
         * @return {Promise}
         */
        reload: function () {
            var self = this;

            this.$beginResultRendering(0);

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_reload', function (result) {
                    self.setAttribute('orderHash', result.hash);

                    self.$renderResult(result, 0).then(function () {
                        self.$endResultRendering();
                        resolve();
                    });

                    if (Router) {
                        Router.navigate(result.url);
                    }

                    Basket.refresh();
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    step     : self.getCurrentStepData().step
                });
            });
        },

        /**
         * Opens the first step
         *
         * @return {Promise}
         */
        openFirstStep: function () {
            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            var FirstLi   = this.$TimelineContainer.getElement('li:first-child'),
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
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_saveCurrentStep', function (result) {
                    self.setAttribute('orderHash', result.hash);
                    resolve(result);
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
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

            if (!this.$TimelineContainer) {
                return {
                    icon : 'fa-shopping-bag',
                    title: QUILocale.get(lg, 'ordering.title'),
                    step : 'basket'
                };
            }

            var Step = this.$TimelineContainer.getElement('li[data-step="' + current + '"]');

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
         * Return the button html Node element
         *
         * @return {HTMLDivElement}
         */
        getButtonContainer: function () {
            return this.$Buttons;
        },

        /**
         * Return a specific button
         *
         * @param {String} name - next, previous, backToShop, changePayment
         * @return {HTMLDivElement|null}
         */
        getButton: function (name) {
            switch (name) {
                case 'next':
                    return this.$Buttons.getElement('.quiqqer-order-ordering-buttons-next');

                case 'previous':
                    return this.$Buttons.getElement('quiqqer-order-ordering-buttons-previous');

                case 'backToShop':
                    return this.$Buttons.getElement('quiqqer-order-ordering-buttons-backToShop');
            }

            return this.$Buttons.getElement('[name="' + name + '"]');
        },

        // endregion

        /**
         * Render the result of an next / previous request
         *
         * @param {Object} result
         * @param {Integer} [startDirection] - 0 = none, -1 = from left, 1 = from right
         * @return {Promise}
         */
        $renderResult: function (result, startDirection) {
            var self = this;

            var Ghost = new Element('div', {
                html: result.html
            });

            var leftPos = 0;

            if (typeof startDirection === 'undefined' || startDirection === -1) {
                leftPos = -100;
            } else if (startDirection === 1) {
                leftPos = 100;
            }

            this.setAttribute('current', result.step);

            QUI.fireEvent('quiqqerOrderProcessOpenStep', [this, result.step]);

            // content
            var Error       = Ghost.getElement('.quiqqer-order-ordering-error');
            var StepContent = Ghost.getElement('.quiqqer-order-ordering-step');
            var TimeLine    = Ghost.getElement('.quiqqer-order-ordering-timeline');
            var Form        = Ghost.getElement('[name="order"]');

            if (Form) {
                this.$Form.set('data-order-hash', Form.get('data-order-hash'));
                this.$Form.set('data-products-count', Form.get('data-products-count'));
                this.setAttribute('orderHash', Form.get('data-order-hash'));
            }

            if ("hash" in result && result.hash !== '') {
                this.setAttribute('orderHash', result.hash);
            }

            if (TimeLine) {
                // refresh the timeline
                this.$TimelineContainer.set('html', TimeLine.get('html'));
            }

            // scroll the the timeline step
            // Fx.Scroll();
            var Step = this.$TimelineContainer.getElement('.current');

            if (Step) {
                (function () {
                    new window.Fx.Scroll(this.$Timeline).toElement(Step);
                }).delay(200, this);
            }

            // render container
            var Next = new Element('div', {
                html   : StepContent.get('html'),
                'class': 'quiqqer-order-ordering-step-next',
                styles : {
                    'float' : 'left',
                    left    : leftPos,
                    opacity : 0,
                    position: 'relative',
                    top     : 0,
                    width   : '100%'
                }
            });

            if (Error) {
                Error.inject(this.$StepContainer, 'before');
            }

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
                    height: Math.max(
                        Next.getSize().y,
                        Next.getComputedSize().totalHeight
                    )
                }, {
                    duration: 500
                });

                // add processing events
                QUI.Controls.getControlsInElement(self.$StepContainer).each(function (Control) {
                    Control.addEvent('onProcessingError', self.$onProcessingError);
                });

                return Promise.all([
                    Prom1,
                    Prom2
                ]).then(function () {
                    return self.$parseProcessingPaymentChange();
                }).then(function () {
                    return self.resize();
                }).then(function () {
                    self.fireEvent('change', [self]);
                    self.Loader.hide();
                });
            });
        },

        /**
         * Helper function for rendering
         * Execution at the beginning of the result rendering
         *
         * @param {Integer} [moveDirection] - In which direction should be hidden, 0 = none, -1 = left, 1 = right
         * @return {Promise}
         */
        $beginResultRendering: function (moveDirection) {
            this.Loader.show();

            var self      = this;
            var Container = this.$StepContainer.getChildren();
            var leftPos   = 0;

            if (typeof moveDirection === 'undefined' || moveDirection === -1) {
                leftPos = -100;
            } else if (moveDirection === 1) {
                leftPos = 100;
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

            this.getElm().getElements('.quiqqer-order-ordering-error').destroy();

            this.$runningAnimation = true;

            return this.$animate(Container, {
                left   : leftPos,
                opacity: 0
            }, {
                duration: 500
            }).then(function () {
                var styles = Container.getElements('style');

                for (var i = 0, len = styles.length; i < len; i++) {
                    styles[i].inject(self.$TimelineContainer);
                }

                Container.destroy();
            });
        },

        /**
         * Return the product count
         *
         * @return {Number}
         */
        $getCount: function () {
            var productCount = parseInt(this.$Form.get('data-products-count'));

            // if no order hash set, we can ask the basket
            if (!this.getAttribute('orderHash')) {
                productCount = Basket.count();
            }

            return productCount;
        },

        /**
         * Helper function for the end of the fx rendering
         */
        $endResultRendering: function () {
            this.$TimelineContainer.getElements('style').destroy();

            this.$runningAnimation = false;
            this.fireEvent('stepLoaded', [this]);
        },

        /**
         * Refresh the step display
         */
        $refreshSteps: function () {
            var current  = this.getAttribute('current');
            var list     = this.$TimelineContainer.getElements('li');
            var Timeline = this.$Timeline;

            list.removeClass('current');
            list.removeClass('active');

            if (current === 'Finish' || current === 'finish') {
                moofx(Timeline).animate({
                    height : 0,
                    margin : 0,
                    opacity: 0,
                    padding: 0
                }, {
                    duration: 200,
                    callback: function () {
                        Timeline.setStyle('display', 'none');
                    }
                });
                return;
            }

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
            if (!this.$Buttons) {
                return;
            }

            this.$Next     = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-next');
            this.$Previous = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-previous');

            this.$Next.removeEvents('click');
            this.$Previous.removeEvents('click');

            this.$Next.addEvent('click', this.$onNextClick);
            this.$Previous.addEvent('click', this.$onPreviousClick);

            var self = this,
                list = this.$TimelineContainer.getElements('li');

            list.removeEvents('click');

            list.addEvent('click', function (event) {
                event.stop();

                var Target = event.target;

                if (Target.nodeName !== 'LI') {
                    Target = Target.getParent('li');
                }

                if (Target.hasClass('disabled')) {
                    return;
                }

                if (Target.get('data-step') === 'finish') {
                    return;
                }

                self.openStep(Target.get('data-step'));
            });

            self.$Buttons.getElements('[name="changePayment"]').addEvent('click', function (event) {
                event.stop();
                self.showProcessingPaymentChange();
            });
        },

        /**
         * event: on next click
         *
         * @param event
         * @return {Boolean}
         */
        $onNextClick: function (event) {
            event.stop();

            if (this.validateStep() === false) {
                return false;
            }

            if (event.target.get('value') === 'payableToOrder') {
                this.send().catch(function () {
                    // if an error, refresh the current step
                    this.$runningAnimation = false;
                    this.refreshCurrentStep();
                }.bind(this));

                return true;
            }

            this.next().catch(function () {
                // nothing
            });

            return true;
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
         * @param {Element|HTMLElement} Elm
         * @param {object} styles
         * @param {object} [options]
         *
         * @return {Promise}
         */
        $animate: function (Elm, styles, options) {
            var running = true;

            options = options || {};

            return new Promise(function (resolve) {
                options.callback = function () {
                    running = false;
                    resolve();
                };

                moofx(Elm).animate(styles, options);

                (function () {
                    if (running) {
                        running = false;
                        resolve();
                    }
                }).delay(2000);
            });
        },

        /**
         * Execute a processing error
         * This method is triggered when a payment trigger a payment error
         * The processing step can now display a payment change step
         *
         * @return {Promise}
         */
        $onProcessingError: function () {
            if (this.getAttribute('current') !== 'Processing') {
                return Promise.resolve();
            }

            this.Loader.show();

            var self      = this,
                Container = this.getElm().getElement('.quiqqer-order-step-processing'),
                Payments  = Container.getElement('.quiqqer-order-processing-payments');

            if (!Payments) {
                Payments = new Element('div', {
                    'class': 'quiqqer-order-processing-payments'
                }).inject(Container);
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_processing_getPayments', function (result) {
                    Payments.set('html', result);

                    self.$parseProcessingPaymentChange().then(function () {
                        self.resize();
                        self.Loader.hide();

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    onError  : reject
                });
            });
        },

        /**
         * This method shows the payment change, if it is allowed
         * - it hides the order payment step
         * - it shows the payment change
         *
         * @return {Promise}
         */
        showProcessingPaymentChange: function () {
            if (this.getAttribute('current') !== 'Processing') {
                return Promise.resolve();
            }

            var Container = this.getElm().getElement('.quiqqer-order-step-processing');

            if (!Container) {
                return Promise.resolve();
            }

            var children = Container.getChildren().filter(function (Child) {
                return !Child.hasClass('quiqqer-order-processing-payments');
            });

            var self   = this,
                Button = this.$Buttons.getElements('[name="changePayment"]');

            this.Loader.setAttribute('opacity', 1);
            this.Loader.setStyles({
                background: 'rgba(255, 255, 255, 1)'
            });

            this.Loader.show();

            return this.$animate(children, {
                height : 0,
                opacity: 0
            }).then(function () {
                return self.$animate(Button, {
                    height : 0,
                    opacity: 0
                });
            }).then(function () {
                children.setStyle('display', 'none');
                Button.setStyle('display', 'none');

                return self.$onProcessingError();
            }).then(function () {
                var Payments = Container.getElement('.quiqqer-order-processing-payments');

                return self.$animate(Payments, {
                    marginTop: 0
                });
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Parse the processing step -> payment change events
         *
         * @return {Promise}
         */
        $parseProcessingPaymentChange: function () {
            var Container = this.getElm().getElement('.quiqqer-order-step-processing');

            if (!Container) {
                return Promise.resolve();
            }

            var self     = this,
                Payments = Container.getElement('.quiqqer-order-processing-payments');

            if (!Payments) {
                return Promise.resolve();
            }

            return QUI.parse(Payments).then(function () {
                Payments.getElement('[name="change-payment"]').addEvent('click', function (event) {
                    event.stop();

                    self.Loader.show();

                    // save new payment method
                    var paymentId = Payments.getElement('input:checked').value;
                    var orderHash = self.getAttribute('orderHash');

                    Orders.saveProcessingPaymentChange(
                        orderHash,
                        paymentId
                    ).then(function () {
                        return self.send();
                    }).then(function () {
                        self.Loader.hide();
                    });
                });
            });
        }
    });
});