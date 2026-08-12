/**
 * @module package/quiqqer/order/bin/frontend/controls/basket/Button
 *
 * @event onCreate [self]
 * @event onShowBasketBegin [self, pos, height]
 * @event onShowBasketEnd [self]
 *
 * CSS classes which can be used as placeholder
 * - .quiqqer-order-basketButton-sum
 * - .quiqqer-order-basketButton-subSum
 * - .quiqqer-order-basketButton-quantity
 * - .quiqqer-order-basketButton-icon
 */
define('package/quiqqer/order/bin/frontend/controls/basket/Button', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',

    'css!package/quiqqer/order/bin/frontend/controls/basket/Button.css'

], function(QUI, QUIControl, QUILocale) {
    'use strict';

    var lg = 'quiqqer/order';
    let basketPromise = null;
    let currencyPromise = null;
    let orderProcessUrlPromise = null;
    let basketWindowPromise = null;

    const loadBasket = function() {
        if (basketPromise) {
            return basketPromise;
        }

        basketPromise = new Promise(function(resolve, reject) {
            require(['package/quiqqer/order/bin/frontend/Basket'], function(Basket) {
                Basket.ready().then(function() {
                    resolve(Basket);
                }, reject);
            }, reject);
        }).catch(function(error) {
            basketPromise = null;
            throw error;
        });

        return basketPromise;
    };

    const loadCurrency = function() {
        if (currencyPromise) {
            return currencyPromise;
        }

        currencyPromise = new Promise(function(resolve, reject) {
            require(['package/quiqqer/currency/bin/Currency'], resolve, reject);
        }).catch(function(error) {
            currencyPromise = null;
            throw error;
        });

        return currencyPromise;
    };

    const loadOrderProcessUrl = function() {
        if (orderProcessUrlPromise) {
            return orderProcessUrlPromise;
        }

        orderProcessUrlPromise = new Promise(function(resolve, reject) {
            require(['package/quiqqer/order/bin/frontend/OrderProcessUrl'], resolve, reject);
        }).catch(function(error) {
            orderProcessUrlPromise = null;
            throw error;
        });

        return orderProcessUrlPromise;
    };

    const loadBasketWindow = function() {
        if (basketWindowPromise) {
            return basketWindowPromise;
        }

        basketWindowPromise = new Promise(function(resolve, reject) {
            require([
                'package/quiqqer/order/bin/frontend/controls/orderProcess/Window'
            ], resolve, reject);
        }).catch(function(error) {
            basketWindowPromise = null;
            throw error;
        });

        return basketWindowPromise;
    };

    const scheduleBasketLoad = function(onLoad) {
        const load = function() {
            loadBasket().then(onLoad).catch(function(error) {
                console.error(error);
            });
        };

        const scheduleIdle = function() {
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(load, {
                    timeout: 1500
                });
                return;
            }

            window.setTimeout(load, 1200);
        };

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(function() {
                window.requestAnimationFrame(scheduleIdle);
            });
            return;
        }

        window.setTimeout(scheduleIdle, 0);
    };

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/frontend/controls/basket/Button',

        Binds: [
            '$onImport',
            '$onInject',
            'showSmallBasket',
            '$showAddInformation'
        ],

        options: {
            action: 'openSmallBasket', // openSmallBasket, openOrderProcessUrl, openOrderProcess (qui popup). These options are only used on desktop. Mobile always opens order process page
            text: true,
            styles: false,
            batchPosition: {
                right: -16,
                top: -10
            },
            showMiniBasketOnMouseOver: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$Icon = null;
            this.$Text = null;

            this.$BasketSmall = null;
            this.$BasketContainer = null;
            this.$isLoaded = false;
            this.$isImported = false;
            this.$basketConnected = false;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject,
                onDestroy: function() {
                    QUI.removeEvent('onQuiqqerOrderBasketAdd', this.$showAddInformation);
                }.bind(this)
            });

            QUI.addEvent('onQuiqqerOrderBasketAdd', this.$showAddInformation);
        },

        /**
         * Create the domnode element
         *
         * @return {Element|null}
         */
        create: function() {
            if (this.mayBeDisplayed() === false) {
                this.$Elm = new Element('div');

                return this.$Elm;
            }

            var text = QUILocale.get(lg, 'control.basket.button.text');

            this.$Elm = new Element('button', {
                'class': 'quiqqer-order-basketButton button--callToAction',
                'html': '<span class="quiqqer-order-basketButton-icon fa fa-spinner fa-spin"></span>' +
                    '<span class="quiqqer-order-basketButton-text">' + text + '</span>' +
                    '<span class="quiqqer-order-basketButton-batch">' +
                    '   <span class="fa fa-spin fa-spinner"></span>' +
                    '</span>',
                disabled: true,
                'data-qui': 'package/quiqqer/order/bin/frontend/controls/basket/Button'
            });

            if (this.getAttribute('styles')) {
                this.$Elm.setStyles(this.getAttribute('styles'));
            }

            if (this.getAttribute('text') === false) {
                this.$Elm.getElement('.quiqqer-order-basketButton-text').setStyle('display', 'none');
            }

            this.fireEvent('create', [this]);

            return this.$Elm;
        },

        /**
         * event: on import
         */
        $onInject: function() {
            this.$onImport();
        },

        /**
         * event: on import
         */
        $onImport: function() {
            if (this.mayBeDisplayed() === false) {
                return;
            }

            if (this.$isImported) {
                return;
            }

            this.$isImported = true;

            var self = this,
                Elm = this.getElm();

            this.$Icon = Elm.getElement('.quiqqer-order-basketButton-icon');
            this.$Text = Elm.getElement('.quiqqer-order-basketButton-text');
            this.$Batch = Elm.getElement('.quiqqer-order-basketButton-batch');

            const connectBasket = function(Basket) {
                if (self.$basketConnected) {
                    return;
                }

                self.$basketConnected = true;

                Basket.addEvents({
                    onRefresh: function() {
                        if (!Basket.isLoaded()) {
                            return;
                        }

                        isLoaded();
                        self.updateDisplay(Basket);
                    },

                    onRefreshBegin: function() {
                        if (self.$Batch) {
                            self.$Batch.set('html', '<span class="fa fa-spinner fa-spin"></span>');
                        }
                    },

                    onClear: function() {
                        isLoaded();
                        self.updateDisplay(Basket);
                    }
                });

                QUI.addEvent('onQuiqqerCurrencyChange', function() {
                    Basket.refresh();
                });

                isLoaded();
                self.updateDisplay(Basket);
            };

            const loadAndConnectBasket = function() {
                loadBasket().then(connectBasket).catch(function(error) {
                    console.error(error);
                });
            };

            Elm.addEventListener('pointerenter', loadAndConnectBasket, {
                once: true
            });
            Elm.addEventListener('focus', loadAndConnectBasket, {
                once: true
            });
            Elm.addEventListener('click', function() {
                loadAndConnectBasket();

                // on mobile always go to order process page
                if (QUI.getWindowSize().x <= 768) {
                    loadOrderProcessUrl().then(function(getOrderProcessUrl) {
                        return getOrderProcessUrl();
                    }).then(function(url) {
                        window.location = url;
                    }).catch(function(error) {
                        console.error(error);
                    });

                    return;
                }

                if (self.getAttribute('action') === 'openSmallBasket') {
                    self.showSmallBasket();

                    return;
                }

                if (self.getAttribute('action') === 'openOrderProcessUrl') {
                    loadOrderProcessUrl().then(function(getOrderProcessUrl) {
                        return getOrderProcessUrl();
                    }).then(function(url) {
                        window.location = url;
                    }).catch(function(error) {
                        console.error(error);
                    });

                    return;
                }

                loadBasketWindow().then(function(BasketWindow) {
                    new BasketWindow().open();
                }).catch(function(error) {
                    console.error(error);
                });
            });

            var delay = null;

            if (this.getAttribute('showMiniBasketOnMouseOver')) {
                Elm.addEventListener('pointerenter', function() {
                    delay = setTimeout(function() {
                        if (QUI.getWindowSize().x <= 768) {
                            return;
                        }

                        self.showSmallBasket();
                    }, 250);
                });
                Elm.addEventListener('pointerleave', function() {
                    clearTimeout(delay);
                });
            }

            Elm.disabled = false;
            scheduleBasketLoad(connectBasket);

            if (this.$Batch) {
                this.$Batch.set('html', '<span class="fa fa-spinner fa-spin"></span>');
            }

            var isLoaded = function() {
                if (this.$isLoaded) {
                    return;
                }

                if (this.$Icon) {
                    this.$Icon.removeClass('fa-spinner');
                    this.$Icon.removeClass('fa-spin');
                    this.$Icon.addClass(' fa-file-text-o');
                }

                this.$isLoaded = true;
                this.getElm().set('disabled', false);
            }.bind(this);

        },

        /**
         * Show the small basket
         */
        showSmallBasket: function() {
            var self = this,
                pos = this.getElm().getPosition(),
                height = this.getElm().getSize();

            if (!this.$BasketContainer) {
                this.$BasketContainer = new Element('div', {
                    'class': 'quiqqer-order-basket-small-container',
                    html: '<span class="fa fa-spinner fa-spin"></span>',
                    tabindex: -1,
                    events: {
                        blur: function() {
                            // @todo überdenken -> vllt api (wegen paypal express gebraucht)
                            (function() {
                                this.setStyle('display', 'none');
                            }).delay(200, this);
                        }
                    }
                }).inject(document.body);
            }

            this.fireEvent('showBasketBegin', [this, pos, height]);

            this.$BasketContainer.setStyles({
                display: null,
                left: pos.x,
                top: pos.y + height.y
            });

            if (this.$BasketSmall) {
                this.$BasketSmall.refresh();
                this.$BasketContainer.focus();

                this.fireEvent('showBasketEnd', [this]);
                return;
            }

            return loadBasket().then(function(Basket) {
                return new Promise(function(resolve, reject) {
                    require([
                        'package/quiqqer/order/bin/frontend/controls/basket/Small'
                    ], function(Small) {
                        self.$BasketContainer.set('html', '');
                        self.$BasketContainer.focus();

                        self.$BasketSmall = new Small({
                            basketId: Basket.getId()
                        }).inject(self.$BasketContainer);

                        self.fireEvent('showBasketEnd', [self]);
                        resolve();
                    }, reject);
                });
            }).catch(function(error) {
                console.error(error);
            });
        },

        /**
         * Can the mini basket be displayed?
         *
         * @return {boolean}
         */
        mayBeDisplayed: function() {
            if (typeof window.QUIQQER_SITE === 'undefined') {
                return true;
            }

            if (typeof window.QUIQQER_SITE.type === 'undefined') {
                return true;
            }

            return !(window.QUIQQER_SITE.type === 'quiqqer/order:types/orderingProcess' ||
                window.QUIQQER_SITE.type === 'quiqqer/order:types/shoppingCart');
        },

        /**
         * Update the batch
         *
         * @param {object} Basket
         */
        updateDisplay: function(Basket) {
            // sum display
            var SumElm = this.getElm().getElement('.quiqqer-order-basketButton-sum');

            if (SumElm) {
                if (!Basket.getCalculations().sum || Basket.getCalculations().sum === '') {
                    loadCurrency().then(function(Currency) {
                        return Currency.convertWithSign(Basket.getCalculations().sum);
                    }).then((result) => {
                        if (typeOf(result) === 'object') {
                            SumElm.set('text', result.convertedRound);
                        } else {
                            SumElm.set('text', result);
                        }
                    }).catch(function(error) {
                        console.error(error);
                    });
                } else {
                    SumElm.set('text', Basket.getCalculations().sum);
                }
            }

            // subsum display
            var SubSumElm = this.getElm().getElement(
                '.quiqqer-order-basketButton-subSum'
            );

            if (SubSumElm) {
                SubSumElm.set('text', Basket.getCalculations().subSum);
            }

            // quantity display
            var quantity = Basket.getQuantity();
            var QuantityElm = this.getElm().getElement(
                '.quiqqer-order-basketButton-quantity'
            );

            if (QuantityElm) {
                QuantityElm.set('text', quantity);
            }

            if (!this.$Batch) {
                return Promise.resolve();
            }

            this.$Batch.set('text', quantity);

            if (quantity) {
                return this.showBatch();
            }

            return this.hideBatch();
        },

        /**
         * Show the batch
         *
         * @returns {Promise}
         */
        showBatch: function() {
            if (!this.$Batch) {
                return Promise.resolve();
            }

            return new Promise(function(resolve) {
                moofx(this.$Batch).animate({
                    opacity: 1,
                    right: this.$getBatchPosition().right,
                    top: this.$getBatchPosition().top
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Hide the batch
         *
         * @returns {Promise}
         */
        hideBatch: function() {
            if (!this.$Batch) {
                return Promise.resolve();
            }

            return new Promise(function(resolve) {
                moofx(this.$Batch).animate({
                    opacity: 0,
                    right: this.$getBatchPosition().right,
                    top: 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Return the batch position parameter
         *
         * @returns {{top: number, right: number}}
         */
        $getBatchPosition: function() {
            var batchPosition = this.getAttribute('batchPosition'),
                right = -16,
                top = -10;

            if ('right' in batchPosition) {
                right = batchPosition.right;
            }

            if ('top' in batchPosition) {
                top = batchPosition.top;
            }

            return {
                top: top,
                right: right
            };
        },

        /**
         * Show product info at Basket add
         *
         * @param Basket
         * @param Product
         */
        $showAddInformation: function(Basket, Product) {
            if (this.mayBeDisplayed() === false) {
                return;
            }

            if (!Basket.isLoaded()) {
                return;
            }

            var Info = new Element('div', {
                'class': 'quiqqer-order-basketButton-infoBubble',
                html: QUILocale.get(lg, 'basket.add.information')
            }).inject(this.getElm());

            var size = this.getElm().getSize();

            Info.setStyles({
                top: size.y
            });

            Info.addClass('bounceInDown');

            (function() {
                Info.destroy();
            }).delay(2000);

            //this.showSmallBasket();
        }
    });
});
