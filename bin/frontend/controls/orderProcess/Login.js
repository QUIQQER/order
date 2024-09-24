/**
 * @module package/quiqqer/order/bin/frontend/controls/orderProcess/Login
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/frontend/controls/orderProcess/Login', [

    'qui/QUI',
    'qui/controls/Control',
    URL_OPT_DIR + 'bin/quiqqer-asset/animejs/animejs/lib/anime.min.js',

], function (QUI, QUIControl, animejs) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/frontend/controls/orderProcess/Login',

        Binds: [
            '$onImport',
            'toggle'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Nav = null;
            this.navEntries = [];
            this.Main = null;
            this.mainEntries = [];
            this.ActiveNavEntry = null;
            this.ActiveMainEntry = null;
            this.clicked = false;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.getSignUpControl().then(function (Signup) {
                if (!Signup) {
                    return;
                }

                self.getMailRegisterNode(Signup).then(function (MailRegister) {
                    MailRegister.set('data-no-blur-check', 1);
                });
            });

            if (QUI.getWindowSize().x < 767) {
                this.initTabsForMobile();

                return;
            }

            this.initTabs();
        },

        // region tabs

        /**
         * Init clickable tabs functionality (for desktop)
         */
        initTabs: function () {
            const Elm = this.getElm();
            const self = this;

            this.Nav = Elm.querySelector('.quiqqer-order-ordering-nobody-tabNav');
            this.navEntries = Elm.querySelectorAll('.quiqqer-order-ordering-nobody-tabNav__entry');
            this.Main = Elm.querySelector('.quiqqer-order-ordering-nobody-tabs-main__list');
            this.mainEntries = Elm.querySelectorAll('.quiqqer-order-ordering-nobody-tabs-main__item');
            this.ActiveNavEntry = Elm.querySelector('.quiqqer-order-ordering-nobody-tabNav__entry.active');
            this.ActiveMainEntry = Elm.querySelector('.quiqqer-order-ordering-nobody-tabs-main__item.active');

            if (!this.navEntries || !this.mainEntries) {
                return;
            }

            const clickEvent = function (event) {
                event.stop();

                if (self.clicked) {
                    return;
                }

                self.clicked = true;

                let NavItem = event.target;

                if (NavItem.nodeName !== 'LI') {
                    NavItem = NavItem.getParent('li');
                }

                let target = NavItem.getElement('a').getAttribute("href");

                if (target.indexOf('#') === 0) {
                    target = target.substring(1);
                }

                if (!target) {
                    self.clicked = false;
                    return;
                }

                self.toggle(NavItem, target);

                const url    = window.location.href;
                const newUrl = url.split('?')[0] + '?open=' + target;

                history.pushState(null, null, newUrl);
            };

            this.navEntries.forEach((NavEntry) => {
                NavEntry.addEvent('click', clickEvent);
            });
        },

        /**
         * Toggle nav and main content
         *
         * @param NavItem HTMLNode
         * @param target string
         */
        toggle: function (NavItem, target) {
            if (NavItem.hasClass('active')) {
                this.clicked = false;
                return;
            }

            const Content = this.getElm().getElement('[id="' + target + '"]');

            if (!Content) {
                this.clicked = false;
                return;
            }

            const self = this;

            this.Main.setStyle('height', this.Main.offsetHeight);

            Promise.all([
                this.disableNavItem(this.ActiveNavEntry),
                this.hideContent(this.ActiveMainEntry)
            ]).then(function () {
                Content.setStyle('display', null);

                return Promise.all([
                    self.enableNavItem(NavItem),
                    self.showContent(Content),
                    self.$setHeight(Content.offsetHeight)
                ]);
            }).then(function () {
                self.clicked = false;
                self.Main.setStyle('height', null);
            });
        },

        /**
         * Make item inactive
         *
         * @param Item HTMLNode
         * @return Promise
         */
        disableNavItem: function (Item) {
            Item.removeClass('active');

            return Promise.resolve();
        },

        /**
         * Make nav item active
         *
         * @param Item HTMLNode
         * @return Promise
         */
        enableNavItem: function (Item) {
            Item.addClass('active');
            this.ActiveNavEntry = Item;

            return Promise.resolve();
        },

        /**
         * Hide tab content
         *
         * @param Item HTMLNode
         * @return Promise
         */
        hideContent: function (Item) {
            return new Promise((resolve) => {
                this.$slideFadeOut(Item).then(function () {
                    Item.removeClass('active');
                    Item.setStyle('display', 'none');

                    resolve();
                });
            });
        },

        /**
         * Show tab content
         *
         * @param Item HTMLNode
         * @return Promise
         */
        showContent: function (Item) {
            return new Promise((resolve) => {
                this.$slideFadeIn(Item).then(() => {
                    Item.style.display = null;
                    Item.style.opacity = null;
                    Item.addClass('active');
                    this.ActiveMainEntry = Item;

                    resolve();
                });
            });
        },

        /**
         * Set height of tab content container
         *
         * @param height integer
         * @return Promise
         */
        $setHeight: function (height) {
            return this.$animate(this.Main, {
                height: height
            });
        },

        /**
         * Fade out animation (hide)
         *
         * @param Item HTMLNode
         * @return Promise
         */
        $slideFadeOut: function (Item) {
            return this.$animate(Item, {
                opacity   : 0,
                translateX: -5,

            });
        },

        /**
         * Fade in animation (show)
         *
         * @param Item HTMLNode
         * @return Promise
         */
        $slideFadeIn: function (Item) {
            Item.setStyles({
                transform: 'translateX(-5px)',
                opacity  : 0
            });

            return this.$animate(Item, {
                translateX: 0,
                opacity   : 1
            });
        },

        $animate: function (Target, options) {
            return new Promise(function (resolve) {
                options          = options || {};
                options.targets  = Target;
                options.complete = resolve;
                options.duration = options.duration || 250;
                options.easing   = options.easing || 'easeInQuad';

                animejs(options);
            });
        },

        // endregion

        // region tabs for mobile

        /**
         * Initializes the tabs for mobile devices.
         *
         * Handles the click events on the tabs and the back buttons, and toggles the visibility of the tabs and their content.
         *
         * @return {void}
         */
        initTabsForMobile: function () {
            const Elm = this.getElm();
            const self = this;

            this.Tabs = Elm.querySelector('.quiqqer-order-ordering-nobody__tabs');
            this.Nav = Elm.querySelector('.quiqqer-order-ordering-nobody-tabNav');
            this.navEntries = Elm.querySelectorAll('.quiqqer-order-ordering-nobody-tabNav__entry');
            this.Main = Elm.querySelector('.quiqqer-order-ordering-nobody-tabs-main__list');
            this.mainEntries = Elm.querySelectorAll('.quiqqer-order-ordering-nobody-tabs-main__item');
            const backBtns = Elm.querySelectorAll('.quiqqer-order-ordering-nobody-tabs-main__btnBack');

            /**
             * Handles the click event on the tabs for mobile devices.
             *
             * @param {object} event - The click event object.
             * @return {void}
             */
            const clickEvent = function (event) {
                event.stop();

                if (self.clicked) {
                    return;
                }

                self.clicked = true;

                let NavItem = event.target;

                if (NavItem.nodeName !== 'LI') {
                    NavItem = NavItem.getParent('li');
                }

                let target = NavItem.getElement('a').getAttribute("href");

                if (target.indexOf('#') === 0) {
                    target = target.substring(1);
                }

                if (!target) {
                    self.clicked = false;
                    return;
                }

                self.Nav.style.display = 'none';

                self.mainEntries.forEach((MainEntry) => {
                    if (MainEntry.getAttribute('id') === target) {
                        MainEntry.style.display = 'block';
                    } else {
                        MainEntry.style.display = 'none';
                    }
                })

                self.clicked = false;
            };

            /**
             * Handles the back button click event on the tabs for mobile devices.
             *
             * @return {void}
             */
            const backClickEvent = function () {
                self.mainEntries.forEach((MainEntry) => {
                    MainEntry.style.display = 'none';
                });

                self.Nav.style.display = null;
            }

            this.navEntries.forEach((NavEntry) => {
                NavEntry.addEvent('click', clickEvent);
            });

            backBtns.forEach((Btn) => {
                Btn.addEvent('click', backClickEvent);
            });
        },

        // endregion

        /**
         * @return {Promise|*}
         */
        getSignUpControl: function () {
            var SignUp = this.getElm().getElement('.quiqqer-fu-registrationSignUp');

            if (SignUp.get('data-quiid')) {
                return Promise.resolve(
                    QUI.Controls.getById(SignUp.get('data-quiid'))
                );
            }

            return new Promise(function (resolve) {
                SignUp.addEvent('load', function () {
                    resolve(QUI.Controls.getById(SignUp.get('data-quiid')));
                });
            });
        },

        /**
         * @param Signup
         * @return {Promise}
         */
        getMailRegisterNode: function (Signup) {
            var fetchMailControl = function () {
                var EmailRegister = Signup.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-registration-email [name="email"]'
                );

                if (!EmailRegister) {
                    return Promise.resolve(false);
                }

                return Promise.resolve(EmailRegister);
            };


            if (Signup.isLoaded()) {
                return fetchMailControl();
            }

            return new Promise(function (resolve) {
                Signup.addEvent('onLoaded', function () {
                    fetchMailControl().then(resolve);
                });
            });
        }
    });
});