/**
 * @module package/quiqqer/order/bin/frontend/controls/OrderProcess
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event QUI Event: onQuiqqerOrderProcessLoad  [this]
 * @event QUI Event: onQuiqqerOrderProcessOpenStep  [this, step]
 * @event QUI Event: onQuiqqerOrderProcessFinish  [orderHash]
 */
require.config({
    paths: {
        'Navigo': URL_OPT_DIR + 'bin/quiqqer-asset/navigo/navigo/lib/navigo.min',
        'HistoryEvents': URL_OPT_DIR + 'bin/quiqqer-asset/history-events/history-events/dist/history-events.min'
    }
});

/* jshint ignore:start */

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

], function(QUI, QUIControl, QUILoader, QUIFormUtils, Basket, Orders, QUIAjax, QUILocale, Navigo) {
    'use strict';

    const lg = 'quiqqer/order';
    let Router, url;

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/frontend/controls/OrderProcess',

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
            '$onProcessingError',
            '$onLoginRedirect',
            '$parseProcessingPaymentChange'
        ],

        options: {
            orderHash: false,
            basket: true, // use the basket for the loading
            basketEditable: true,
            buttons: true,
            showLoader: true
        },

        initialize: function(options) {
            this.parent(options);

            this.$Form = null;
            this.$StepContainer = null;
            this.$Timeline = null;
            this.$runningAnimation = false;
            this.$isResizing = false;
            this.$enabled = true;
            this.$loaded = false;

            this.$Buttons = null;
            this.$Next = null;
            this.$Previous = null;
            this.Loader = new QUILoader();

            this.Loader.addEvents({
                onShow: function() {
                    this.fireEvent('loaderShow', [this]);
                }.bind(this),
                onHide: function() {
                    this.fireEvent('loaderHide', [this]);
                }.bind(this)
            });

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject,
                onLoad: function() {
                    this.$loaded = true;
                }.bind(this)
            });

            window.addEventListener('changestate', this.$onChangeState, false);

            QUI.addEvent('quiqqerCurrencyChange', () => {
                if (this.getElm()) {
                    this.refreshCurrentStep();
                }
            });
        },

        /**
         * @event on change state
         */
        $onChangeState: function() {
            const pathName = window.location.pathname;

            if (pathName.indexOf(url) === -1) {
                return;
            }

            const parts = pathName.trim().split('/').filter(function(value) {
                return value !== '';
            });

            if (parts.length === 1) {
                this.openFirstStep();
                return;
            }

            const current = this.getCurrentStepData();

            if (current.step !== parts[1]) {
                this.openStep(parts[1]);
            }
        },

        /**
         * is process loaded
         *
         * @return {boolean}
         */
        isLoaded: function() {
            return this.$loaded;
        },

        /**
         * event: on import
         */
        $onImport: function() {
            this.$startLoginCheck();


            const self = this;

            if (this.getAttribute('showLoader')) {
                this.Loader.inject(this.getElm());
            }

            if (QUI.Storage.get('checkout-login')) {
                QUI.Storage.remove('checkout-login');

                if (!Basket.isLoaded()) {
                    Basket.addEvent('onLoad', function() {
                        self.$onImport().then(function() {
                            return Basket.toOrder();
                        }).then(function() {
                            self.refreshCurrentStep();
                        });
                    });

                    return;
                }
            }

            url = this.getElm().get('data-url');

            if (window.location.pathname.indexOf(url) === 0) {
                Router = new Navigo(null, false, '');

                // workaround - don't know why its needed, but its needed :D
                Router.on(url + '/*', function() {
                });
            }

            this.$Buttons = this.getElm().getElement('.quiqqer-order-ordering-buttons');
            this.$StepContainer = this.getElm().getElement('.quiqqer-order-ordering-step');
            this.$Timeline = this.getElm().getElement('.quiqqer-order-ordering-timeline');
            this.$TimelineContainer = this.getElm().getElement('.quiqqer-order-ordering-timeline-container');
            this.$Form = this.getElm().getElement('[name="order"]');

            if (!this.$Form) {
                const SimpleCheckout = this.getElm().getParent(
                    '[data-qui="package/quiqqer/order-simple-checkout/bin/frontend/controls/SimpleCheckout"]'
                );

                if (SimpleCheckout) {
                    this.$Form = SimpleCheckout.getElement('form');
                } else {
                    console.error('Order Process: no form found');
                    this.$Form = new Element('form');
                }
            }

            this.$Form.addEvent('submit', function(e) {
                e.stop();
            });

            if (this.getAttribute('buttons') === false) {
                this.$Buttons.setStyle('display', 'none');
            }

            this.$refreshButtonEvents();

            if (this.$Form.get('data-order-hash') && this.$Form.get('data-order-hash') !== '') {
                this.setAttribute('orderHash', this.$Form.get('data-order-hash'));
            }

            if (this.getElm().get('data-qui-option-basketeditable')) {
                this.setAttribute('basketEditable', !!+this.getElm().get('data-qui-option-basketeditable'));
            }

            let Current = this.$TimelineContainer.getFirst('ul li.current'),
                Nobody = this.getElm().getElement('.quiqqer-order-ordering-nobody'),
                Done = Promise.resolve();

            if (!Current) {
                Current = this.$TimelineContainer.getFirst('ul li');
            }

            if (Current) {
                this.setAttribute('current', Current.get('data-step'));
            }

            if (this.getAttribute('current') === 'Basket' && !this.$Next) {
                if (!Basket.isLoaded()) {
                    Basket.addEvent('onLoad', function() {
                        if (Basket.getProducts().length) {
                            self.refreshCurrentStep();
                        }
                    });
                }
            }

            if (Nobody) {
                this.$TimelineContainer.setStyle('display', 'none');

                Done = QUI.parse(Nobody);
            }

            // parse basket container - only in qui popup
            if (!Nobody &&
                this.$StepContainer.getElement('.quiqqer-order-step-basket') &&
                this.$StepContainer.getParent('.qui-window-popup')) {
                Done = QUI.parse(this.$StepContainer.getElement('.quiqqer-order-step-basket'));
            }

            return Done.then(function() {
                if (Nobody) {
                    // own login redirect
                    Nobody.getElements(
                        '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/login/Login"]'
                    ).forEach(function(Node) {
                        const Control = QUI.Controls.getById(Node.get('data-quiid'));

                        if (Control) {
                            Control.setAttribute('ownRedirectOnLogin', self.$onLoginRedirect);
                        }
                    });
                }

                self.fireEvent('load', [self]);
                QUI.fireEvent('quiqqerOrderProcessLoad', [self]);

                self.getElm().addClass('quiqqer-order-ordering');

                // add processing events
                QUI.Controls.getControlsInElement(self.$StepContainer).each(function(Control) {
                    Control.addEvent('onProcessingError', self.$onProcessingError);
                });
            });
        },

        /**
         * event: on import
         */
        $onInject: function() {
            const self = this;
            const Nobody = this.getElm().getElement('.quiqqer-order-ordering-nobody');

            this.getElm().set('data-quiid', this.getId());

            let Prom = new Promise(function(resolve) {
                resolve(self.getAttribute('orderHash'));
            });

            if (!this.getAttribute('orderHash') && !Nobody) {
                Prom = Orders.getLastOrder().then(function(order) {
                    self.setAttribute('orderHash', order.hash);
                    return order.hash;
                });
            }

            Prom.then(function() {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getControl', function(html) {
                    const Ghost = new Element('div', {
                        html: html
                    });

                    const Process = Ghost.getElement(
                        '[data-qui="package/quiqqer/order/bin/frontend/controls/OrderProcess"]'
                    );

                    const styles = Ghost.getElements('style');
                    const scripts = [];

                    Process.getElements('script').forEach(function(Script) {
                        const New = new Element('script');

                        if (Script.get('html')) {
                            New.set('html', Script.get('html'));
                        }

                        if (Script.get('src')) {
                            New.set('src', Script.get('src'));
                        }

                        scripts.push(New);
                        Script.destroy();
                    });

                    self.getElm().set({
                        'data-qui': Process.get('data-qui'),
                        'data-url': Process.get('data-url'),
                        'html': Process.get('html')
                    });

                    styles.inject(self.getElm());
                    scripts.forEach(function(Script) {
                        Script.inject(self.getElm());
                    });

                    self.$onImport();
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    basket: self.getAttribute('basket'),
                    basketEditable: self.getAttribute('basketEditable') ? 1 : 0
                });
            });
        },

        /**
         * login check
         */
        $startLoginCheck: function() {
            const self = this;

            if (this.getElm().getElement('[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/login/Login"]')) {
                return;
            }

            QUIAjax.get('package_quiqqer_order_ajax_frontend_order_isLoggedIn', function(isLoggedIn) {
                if (!isLoggedIn) {
                    // show login
                    self.$showLogin();
                    return;
                }


                (function() {
                    self.$startLoginCheck();
                }).delay(10000);
            }, {
                package: 'quiqqer/order'
            });
        },

        /**
         * show the login window
         */
        $showLogin: function() {
            const self = this;

            require(['package/quiqqer/frontend-users/bin/frontend/controls/login/Window'], function(LoginWindow) {
                new LoginWindow({
                    reload: false,
                    events: {
                        onCancel: function() {
                            window.location.reload();
                        },
                        onSuccess: function() {
                            self.$startLoginCheck();
                        }
                    }
                }).open();
            });
        },

        /**
         * Enables the buttons and timeline
         * -> look at disable
         */
        enable: function() {
            this.$enabled = true;

            if (this.$Next) {
                this.$Next.set('disabled', false);
            }

            if (this.$Previous) {
                this.$Previous.set('disabled', false);
            }

            if (this.$Timeline) {
                this.$Timeline.removeClass('disabled');
            }
        },

        /**
         * Disables the buttons and timeline
         *
         * so the ordering process itself can not be continued.
         * this method is intended to stop the ordering process until everything is filled in correctly.
         */
        disable: function() {
            this.$enabled = false;

            if (this.$Next) {
                this.$Next.set('disabled', true);
            }

            if (this.$Previous) {
                this.$Previous.set('disabled', true);
            }

            if (this.$Timeline) {
                this.$Timeline.addClass('disabled');
            }
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
        addProduct: function(productId, fields, quantity) {
            const self = this;

            quantity = quantity || 1;

            if (!quantity) {
                return Promise.reject('Product need a quantity');
            }

            if (!this.$Timeline) {
                return new Promise(function(resolve) {
                    (function() {
                        self.addProduct(productId, fields, quantity).then(resolve);
                    }).delay(500);
                });
            }

            return new Promise(function(resolve, reject) {
                QUI.fireEvent('onQuiqqerOrderProductAddBegin', [self]);

                QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_addProductToBasketOrder', function() {
                    QUI.fireEvent('onQuiqqerOrderProductAdd', [self]);
                    self.refreshCurrentStep().then(resolve);
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    productId: productId,
                    quantity: quantity,
                    fields: JSON.encode(fields),
                    onError: reject
                });
            });
        },

        /**
         * Add multiple products to the order process
         *
         * @param {Promise} products
         */
        addProducts: function(products) {
            const self = this;

            if (!this.$Timeline) {
                return new Promise(function(resolve) {
                    (function() {
                        self.addProducts(products).then(resolve);
                    }).delay(500);
                });
            }

            return new Promise(function(resolve, reject) {
                QUI.fireEvent('onQuiqqerOrderProductAddBegin', [self]);

                QUIAjax.post('package_quiqqer_order_ajax_frontend_basket_addProductsToBasketOrder', function() {
                    QUI.fireEvent('onQuiqqerOrderProductAdd', [self]);
                    self.refreshCurrentStep().then(resolve);
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    products: JSON.encode(products),
                    onError: reject
                });
            });
        },

        /**
         *
         * @param {Integer} pos
         *
         * @return {Promise}
         */
        removeProductPos: function(pos) {
            const self = this;

            return Orders.removePosition(
                this.getAttribute('orderHash'),
                pos
            ).then(function() {
                return self.refreshCurrentStep();
            });
        },

        /**
         * Return the products of the current order
         *
         * @return {Promise}
         */
        getArticles: function() {
            const self = this;

            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getArticles', resolve, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    onError: reject
                });
            });
        },

        /**
         * Clears the current order
         *
         * @return {Promise}
         */
        clear: function() {
            return Orders.clearOrder(this.getAttribute('orderHash'));
        },

        //endregion

        // region API

        /**
         * Return the order hash of the order process
         *
         * @return {Promise}
         */
        getOrder: function() {
            const self = this,
                oderHash = this.getAttribute('orderHash');

            if (oderHash) {
                return Promise.resolve(oderHash);
            }

            return Orders.getLastOrder().then(function(order) {
                self.setAttribute('orderHash', order.hash);

                return order.hash;
            });
        },

        /**
         * Resize the order process
         */
        resize: function() {
            const self = this;

            if (this.$isResizing) {
                return new Promise(function(resolve) {
                    (function() {
                        self.resize().then(resolve);
                    }).delay(200);
                });
            }

            this.$isResizing = true;

            return new Promise(function(resolve) {
                const Next = self.$StepContainer.getElement('.quiqqer-order-ordering-step-next');
                const Basket = self.$StepContainer.getElement('.quiqqer-order-step-basket');

                const innerHeight = self.$StepContainer.getChildren().filter(function(Node) {
                    if (Node.nodeName === 'STYLE') {
                        return false;
                    }

                    if (Next && Basket) {
                        return Node.hasClass('quiqqer-order-ordering-step-next');
                    }

                    return true;
                }).getSize().map(function(size) {
                    return size.y;
                }).sum();

                const finish = function() {
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
                    callback: function() {
                        self.$StepContainer.setStyle('height', null);
                        finish();
                    }
                });

                finish.delay(300);
            });
        },

        /**
         * Next step
         *
         * @return {Promise}
         */
        next: function() {
            if (this.$enabled === false) {
                return Promise.resolve();
            }

            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            if (!this.$getCount()) {
                return this.refreshCurrentStep();
            }

            if (this.validateStep() === false) {
                return Promise.reject();
            }

            const self = this;

            this.$beginResultRendering(-1);

            return this.saveCurrentStep().then(function() {
                return new Promise(function(resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getNext', function(result) {
                        self.$renderResult(result, 1).then(function() {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
                        current: self.getAttribute('current'),
                        basketEditable: self.getAttribute('basketEditable') ? 1 : 0,
                        onError: reject
                    });
                });
            });
        },

        /**
         * Previous step
         *
         * @return {Promise}
         */
        previous: function() {
            if (this.$enabled === false) {
                return Promise.resolve();
            }

            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            const self = this;

            if (!this.$getCount()) {
                return Promise.resolve();
            }

            this.$beginResultRendering(1);

            return this.saveCurrentStep().then(function() {
                return new Promise(function(resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getPrevious', function(result) {
                        self.$renderResult(result, -1).then(function() {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
                        current: self.getAttribute('current'),
                        basketEditable: self.getAttribute('basketEditable') ? 1 : 0
                    });
                });
            });
        },

        /**
         * Check / valdate the step
         * html5 validation
         *
         * @param {boolean} [stepCheck] - optional
         * @return {boolean}
         */
        validateStep: function(stepCheck) {
            // test html5 required
            const Required = this.getElm().getElements('[required]');

            // validate controls
            let Node = this.$StepContainer.getFirst();

            if (Node.hasClass('quiqqer-order-ordering-step-next')) {
                Node = Node.getFirst();
            }

            const Instance = QUI.Controls.getById(Node.get('data-quiid'));

            if (typeof stepCheck === 'undefined') {
                stepCheck = true;
            }

            if (stepCheck &&
                Instance &&
                typeof Instance.validate === 'function' &&
                typeof Instance.isValid === 'function'
            ) {
                const self = this;

                if (Instance.isValid() === false) {
                    Instance.validate().then(function() {
                        self.validateStep(false);
                    });

                    return false;
                }
            }

            if (Required.length) {
                let i, len, Field;

                for (i = 0, len = Required.length; i < len; i++) {
                    Field = Required[i];

                    if (!('checkValidity' in Field)) {
                        continue;
                    }

                    if (Field.getStyle('display') === 'none') {
                        continue;
                    }

                    if (Field.checkValidity()) {
                        continue;
                    }

                    // chrome validate message
                    if ('reportValidity' in Field) {
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
        send: function() {
            if (this.$enabled === false) {
                return Promise.resolve();
            }

            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            const self = this;

            if (!this.$getCount()) {
                return Promise.resolve();
            }

            this.Loader.setAttribute('opacity', 1);
            this.Loader.setStyles({
                background: 'rgba(255, 255, 255, 1)'
            });

            this.$beginResultRendering();

            const data = QUIFormUtils.getFormData(this.$Form);

            return this.saveCurrentStep().then(function() {
                return new Promise(function(resolve, reject) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_send', function(result) {
                        self.$renderResult(result).then(function() {
                            self.$endResultRendering();
                            resolve();
                        });

                        if (Router) {
                            Router.navigate(result.url);
                        }
                    }, {
                        'package': 'quiqqer/order',
                        orderHash: self.getAttribute('orderHash'),
                        current: self.getAttribute('current'),
                        formData: JSON.stringify(data),
                        onError: reject
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
        openStep: function(step) {
            if (this.$enabled === false) {
                return Promise.resolve();
            }

            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            if (!this.$TimelineContainer) {
                return Promise.resolve();
            }

            const self = this;

            if (!this.$getCount()) {
                const FirstLi = this.$TimelineContainer.getElement('li:first-child');

                step = FirstLi.get('data-step');
            }

            if (this.getCurrentStepData().step === step) {
                this.$beginResultRendering(0);
            } else {
                this.$beginResultRendering(-1);
            }

            return this.saveCurrentStep().then(function() {
                return new Promise(function(resolve) {
                    QUIAjax.get('package_quiqqer_order_ajax_frontend_order_getStep', function(result) {
                        if (self.getCurrentStepData().step === step) {
                            self.$renderResult(result, 0).then(function() {
                                self.$endResultRendering();
                                resolve();
                            });
                        } else {
                            self.$renderResult(result, 1).then(function() {
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
                        basketEditable: self.getAttribute('basketEditable') ? 1 : 0,
                        step: step
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
        refreshCurrentStep: function() {
            return this.openStep(
                this.getCurrentStepData().step
            );
        },

        /**
         * Reload the basket
         *
         * @return {Promise}
         */
        reload: function() {
            const self = this;

            this.$beginResultRendering(0);

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_reload', function(result) {
                    self.setAttribute('orderHash', result.hash);

                    self.$renderResult(result, 0).then(function() {
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
                    step: self.getCurrentStepData().step,
                    basketEditable: self.getAttribute('basketEditable') ? 1 : 0
                });
            });
        },

        /**
         * Opens the first step
         *
         * @return {Promise}
         */
        openFirstStep: function() {
            if (this.$enabled === false) {
                return Promise.resolve();
            }

            if (this.$runningAnimation) {
                return Promise.resolve();
            }

            const FirstLi = this.$TimelineContainer.getElement('li:first-child'),
                firstStep = FirstLi.get('data-step');

            const current = this.getCurrentStepData();

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
        saveCurrentStep: function() {
            const self = this,
                data = QUIFormUtils.getFormData(this.$Form);

            // filter and use only data
            delete data.step;
            delete data.orderId;
            delete data.current;

            const elements = this.$Form.elements;

            for (const n in data) {
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

            return new Promise(function(resolve) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_saveCurrentStep', function(result) {
                    self.setAttribute('orderHash', result.hash);
                    resolve(result);
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    step: self.getAttribute('current'),
                    data: JSON.encode(data)
                });
            });
        },

        /**
         * Return the data of the current step
         *
         * @return {{icon: string, title: string}}
         */
        getCurrentStepData: function() {
            const current = this.getAttribute('current');

            if (!this.$TimelineContainer) {
                return {
                    icon: 'fa-shopping-bag',
                    title: QUILocale.get(lg, 'ordering.title'),
                    step: 'basket'
                };
            }

            const Step = this.$TimelineContainer.getElement('li[data-step="' + current + '"]');

            if (!Step) {
                return {
                    icon: 'fa-shopping-bag',
                    title: QUILocale.get(lg, 'ordering.title'),
                    step: 'basket'
                };
            }

            return {
                icon: Step.get('data-icon'),
                title: Step.getElement('.title').get('text').trim(),
                step: Step.get('data-step')
            };
        },

        /**
         * Return the button html Node element
         *
         * @return {HTMLDivElement}
         */
        getButtonContainer: function() {
            return this.$Buttons;
        },

        /**
         * Return a specific button
         *
         * @param {String} name - next, previous, backToShop, changePayment
         * @return {HTMLDivElement|null}
         */
        getButton: function(name) {
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
        $renderResult: function(result, startDirection) {
            const self = this;

            const Ghost = new Element('div', {
                html: result.html
            });

            let leftPos = 0;

            if (typeof startDirection === 'undefined' || startDirection === -1) {
                leftPos = -10;
            } else {
                if (startDirection === 1) {
                    leftPos = 10;
                }
            }

            this.setAttribute('current', result.step);

            QUI.fireEvent('quiqqerOrderProcessOpenStep', [
                this,
                result.step
            ]);

            // content
            const Error = Ghost.getElement('.quiqqer-order-ordering-error');
            const StepContent = Ghost.getElement('.quiqqer-order-ordering-step');
            const TimeLine = Ghost.getElement('.quiqqer-order-ordering-timeline');
            const Form = Ghost.getElement('[name="order"]');
            const scripts = Ghost.getElements('script');

            if (Form) {
                this.$Form.set('data-order-hash', Form.get('data-order-hash'));
                this.$Form.set('data-products-count', Form.get('data-products-count'));
                this.setAttribute('orderHash', Form.get('data-order-hash'));
            }

            if ('hash' in result && result.hash !== '') {
                this.setAttribute('orderHash', result.hash);
            }

            if (TimeLine) {
                // refresh the timeline
                this.$TimelineContainer.set('html', TimeLine.get('html'));
            }

            // scroll the the timeline step
            // Fx.Scroll();
            const Step = this.$TimelineContainer.getElement('.current');

            if (Step) {
                (function() {
                    new window.Fx.Scroll(this.$Timeline).toElement(Step);
                }).delay(200, this);
            }

            // render container
            const Next = new Element('div', {
                html: StepContent.get('html'),
                'class': 'quiqqer-order-ordering-step-next',
                styles: {
                    left: leftPos,
                    opacity: 0,
                    position: 'relative',
                    top: 0,
                }
            });

            if (Error) {
                Error.inject(this.$StepContainer, 'before');
            }

            Next.inject(this.$StepContainer);

            // load script elements
            scripts.forEach(function(Script) {
                const New = new Element('script');

                if (Script.get('html')) {
                    New.set('html', Script.get('html'));
                }

                if (Script.get('src')) {
                    New.set('src', Script.get('src'));
                }

                New.inject(self.$StepContainer);
            });


            // render buttons
            this.$Buttons.set(
                'html',
                Ghost.getElement('.quiqqer-order-ordering-buttons').get('html')
            );

            // events & animation
            this.$refreshSteps();
            this.$refreshButtonEvents();

            return QUI.parse(this.$StepContainer).then(function() {
                const Prom1 = self.$animate(Next, {
                    left: 0,
                    opacity: 1
                }, {
                    duration: 500
                });

                const Prom2 = self.$animate(self.$StepContainer, {
                    height: Math.max(
                        Next.getSize().y,
                        Next.getComputedSize().totalHeight
                    )
                }, {
                    duration: 500
                });

                // add processing events
                QUI.Controls.getControlsInElement(self.$StepContainer).each(function(Control) {
                    Control.addEvent('onProcessingError', self.$onProcessingError);
                });

                return Promise.all([
                    Prom1,
                    Prom2
                ]).then(function() {
                    return self.$parseProcessingPaymentChange();
                }).then(function() {
                    return self.resize();
                }).then(function() {
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
        $beginResultRendering: function(moveDirection) {
            this.Loader.show();

            const self = this;
            const Container = this.$StepContainer.getChildren();
            let leftPos = 0;

            if (typeof moveDirection === 'undefined' || moveDirection === -1) {
                leftPos = -10;
            } else {
                if (moveDirection === 1) {
                    leftPos = 10;
                }
            }

            this.$StepContainer.setStyles({
                height: this.$StepContainer.getSize().y,
                width: '100%'
            });

            Container.setStyles({
                left: 0,
                position: 'absolute',
                top: 0,
                width: '100%'
            });

            this.getElm().getElements('.quiqqer-order-ordering-error').destroy();

            this.$runningAnimation = true;

            return this.$animate(Container, {
                left: leftPos,
                opacity: 0
            }, {
                duration: 250
            }).then(function() {
                const styles = Container.getElements('style');

                for (let i = 0, len = styles.length; i < len; i++) {
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
        $getCount: function() {
            if (!this.$Form) {
                return 0;
            }

            let productCount = parseInt(this.$Form.get('data-products-count'));

            // if no order hash set, we can ask the basket
            if (!this.getAttribute('orderHash')) {
                productCount = Basket.count();
            }

            return productCount;
        },

        /**
         * Helper function for the end of the fx rendering
         */
        $endResultRendering: function() {
            this.$TimelineContainer.getElements('style').destroy();

            this.scrollIntoView();
            this.$runningAnimation = false;
            this.fireEvent('stepLoaded', [this]);
        },

        /**
         * Refresh the step display
         */
        $refreshSteps: function() {
            const current = this.getAttribute('current');
            const list = this.$TimelineContainer.getElements('li');
            const Timeline = this.$Timeline;

            list.removeClass('current');
            list.removeClass('active');

            if (current === 'Finish' || current === 'finish') {
                moofx(Timeline).animate({
                    height: 0,
                    margin: 0,
                    opacity: 0,
                    padding: 0
                }, {
                    duration: 200,
                    callback: function() {
                        Timeline.setStyle('display', 'none');
                    }
                });
                return;
            }

            for (let i = 0, len = list.length; i < len; i++) {
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
        $refreshButtonEvents: function() {
            if (!this.$Buttons) {
                return;
            }

            this.$Next = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-next');
            this.$Previous = this.$Buttons.getElements('.quiqqer-order-ordering-buttons-previous');

            const self = this,
                list = this.$TimelineContainer.getElements('li');

            list.removeEvents('click');

            list.addEvent('click', function(event) {
                event.stop();

                let Target = event.target;

                if (Target.nodeName !== 'LI') {
                    Target = Target.getParent('li');
                }

                if (Target.hasClass('disabled')) {
                    return;
                }

                if (Target.get('data-step') === 'finish') {
                    return;
                }

                // validate current step
                if (self.$isNextClick(Target.get('data-step')) && self.validateStep() === false) {
                    return;
                }

                self.openStep(Target.get('data-step'));
            });

            this.$Next.removeEvents('click');
            this.$Previous.removeEvents('click');

            this.$Next.addEvent('click', this.$onNextClick);
            this.$Previous.addEvent('click', this.$onPreviousClick);

            self.$Buttons.getElements('[name="changePayment"]').addEvent('click', function(event) {
                event.stop();
                self.showProcessingPaymentChange();
            });


            // double - do not show next button if checkout process is in popup
            if (this.$Next.getParent('.qui-window-popup').length &&
                this.$Next.getParent('.qui-window-popup')[0]) {
                this.$Next.setStyle('display', 'none');
            }
        },

        /**
         *
         * @param stepName
         * @return {boolean}
         */
        $isNextClick: function(stepName) {
            const current = this.getAttribute('current');
            const list = this.$TimelineContainer.getElements('li').map(function(Node) {
                return Node.get('data-step');
            });

            return list.indexOf(stepName) >= list.indexOf(current);
        },

        /**
         * event: on next click
         *
         * @param event
         * @return {Boolean}
         */
        $onNextClick: function(event) {
            event.stop();

            if (this.validateStep() === false) {
                return false;
            }

            if (event.target.get('value') === 'payableToOrder') {
                this.send().catch(function() {
                    // if an error, refresh the current step
                    this.$runningAnimation = false;
                    this.refreshCurrentStep();
                }.bind(this));

                return true;
            }

            this.scrollIntoView();

            this.next().catch(function(e) {
                // nothing
                console.error(e);
            });

            return true;
        },

        /**
         * event: on previous click
         * @param event
         */
        $onPreviousClick: function(event) {
            event.stop();
            this.scrollIntoView();

            this.previous().catch(function(e) {
                // nothing
                console.error(e);
            });
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
        $animate: function(Elm, styles, options) {
            let running = true;

            options = options || {};

            return new Promise(function(resolve) {
                options.callback = function() {
                    running = false;
                    resolve();
                };

                if (!Elm || !moofx(Elm)) {
                    options.callback();
                    return;
                }

                moofx(Elm).animate(styles, options);

                (function() {
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
        $onProcessingError: function() {
            if (this.getAttribute('current') !== 'Processing') {
                return Promise.resolve();
            }

            this.Loader.show();

            let self = this,
                Container = this.getElm().getElement('.quiqqer-order-step-processing'),
                Payments = Container.getElement('.quiqqer-order-processing-payments');

            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_order_ajax_frontend_order_processing_getPayments', function(result) {
                    if (result !== '') {
                        if (!Payments) {
                            Payments = new Element('div', {
                                'class': 'quiqqer-order-processing-payments'
                            }).inject(Container);
                        }

                        Payments.set('html', result);
                    } else {
                        if (Payments) {
                            Payments.destroy();
                        }
                    }

                    self.$parseProcessingPaymentChange().then(function() {
                        self.resize();
                        self.Loader.hide();

                        resolve();
                    });
                }, {
                    'package': 'quiqqer/order',
                    orderHash: self.getAttribute('orderHash'),
                    onError: reject
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
        showProcessingPaymentChange: function() {
            if (this.getAttribute('current') !== 'Processing') {
                return Promise.resolve();
            }

            const Container = this.getElm().getElement('.quiqqer-order-step-processing');

            if (!Container) {
                return Promise.resolve();
            }

            const children = Container.getChildren().filter(function(Child) {
                return !Child.hasClass('quiqqer-order-processing-payments');
            });

            const self = this,
                Button = this.$Buttons.getElements('[name="changePayment"]');

            this.Loader.setAttribute('opacity', 1);
            this.Loader.setStyles({
                background: 'rgba(255, 255, 255, 1)'
            });

            this.Loader.show();

            return this.$animate(children, {
                height: 0,
                opacity: 0
            }).then(function() {
                return self.$animate(Button, {
                    height: 0,
                    opacity: 0
                });
            }).then(function() {
                children.setStyle('display', 'none');
                Button.setStyle('display', 'none');

                return self.$onProcessingError();
            }).then(function() {
                const Payments = Container.getElement('.quiqqer-order-processing-payments');

                return self.$animate(Payments, {
                    marginTop: 0
                });
            }).then(function() {
                self.Loader.hide();
            });
        },

        /**
         * Parse the processing step -> payment change events
         *
         * @return {Promise}
         */
        $parseProcessingPaymentChange: function() {
            const Container = this.getElm().getElement('.quiqqer-order-step-processing');

            if (!Container) {
                return Promise.resolve();
            }

            const self = this,
                Payments = Container.getElement('.quiqqer-order-processing-payments');

            if (!Payments) {
                return Promise.resolve();
            }

            const PaymentChange = Payments.getElement('[name="change-payment"]');
            const MainPaymentChange = this.$Buttons.getElement('[name="changePayment"]');

            if (PaymentChange && MainPaymentChange) {
                MainPaymentChange.setStyle('display', 'none');
            } else {
                if (MainPaymentChange) {
                    MainPaymentChange.setStyle('display', null);
                }
            }

            return QUI.parse(Payments).then(function() {
                const Change = Payments.getElement('[name="change-payment"]');

                if (!Change) {
                    return;
                }

                Change.addEvent('click', function(event) {
                    event.stop();

                    self.Loader.show();

                    // save new payment method
                    const paymentId = Payments.getElement('input:checked').value;
                    const orderHash = self.getAttribute('orderHash');

                    Orders.saveProcessingPaymentChange(
                        orderHash,
                        paymentId
                    ).then(function() {
                        return self.send();
                    }).then(function() {
                        self.Loader.hide();
                    });
                });
            });
        },

        /**
         * login redirect behaviour
         */
        $onLoginRedirect: function() {
            require(['package/quiqqer/order/bin/frontend/Basket'], function(GlobalBasket) {
                GlobalBasket.getBasket().then(function(basket) {
                    const products = basket.products;

                    // if there are no products yet, merge without query
                    if (!products.length) {
                        GlobalBasket.setAttribute('mergeLocalStorage', 1);
                        GlobalBasket.load().then(function() {
                            window.location.reload();
                        });
                        return;
                    }

                    let storageData = QUI.Storage.get('quiqqer-basket-products');
                    let storageProducts = [];

                    try {
                        storageData = JSON.decode(storageData);
                        const currentList = storageData.currentList;

                        if (typeof storageData.products !== 'undefined' &&
                            typeof storageData.products[currentList] !== 'undefined') {
                            storageProducts = storageData.products[currentList];
                        }
                    } catch (e) {
                        // nothing
                    }

                    if (!storageProducts.length) {
                        GlobalBasket.load().then(function() {
                            window.location.reload();
                        });

                        return;
                    }

                    GlobalBasket.showMergeWindow().then(function() {
                        window.location.reload();
                    });
                });
            });
        },

        scrollIntoView: function() {
            if (typeof this.getElm().scrollIntoView === 'function') {
                this.getElm().scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                    inline: 'start'
                });
            }
        }
    });
});

/* jshint ignore:end */