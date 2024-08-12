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
            '$resize',
            'toggle',
            '$mouseMoveHandler',
            '$mouseDownHandler',
            '$mouseUpHandler'
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

            QUI.addEvent('resize', this.$resize);
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

            this.initTabs();
        },

        // region tabs

        /**
         * Init clickable tabs functionality
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

            // scroll active nav elm to the left by page load
            this.$setNavItemPos(this.ActiveNavEntry);

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

                self.$setNavItemPos(NavItem);
                self.toggle(NavItem, target);

                const url    = window.location.href;
                const newUrl = url.split('?')[0] + '?open=' + target;

                history.pushState(null, null, newUrl);
            };

            this.navEntries.forEach((NavEntry) => {
                NavEntry.addEvent('click', clickEvent);
            });

            this.$resize();
        },

        $resize: function () {
            if (this.enableDragToScroll !== 1) {
                return;
            }

            if (this.navTab.scrollWidth > this.navTab.clientWidth) {
                this.navTab.addEventListener('mousedown', this.$mouseDownHandler);
            } else {
                this.navTab.removeEventListener('mousedown', this.$mouseDownHandler);
            }
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

        /**
         * Scroll active nav item to the left edge (on mobile)
         *
         * @param Item
         */
        $setNavItemPos: function (Item) {
            if (!Item) {
                return;
            }

            if (QUI.getWindowSize().x > 767) {
                return;
            }

            const paddingLeft = window.getComputedStyle(this.Nav, null).getPropertyValue('padding-left'),
                marginLeft  = window.getComputedStyle(Item, null).getPropertyValue('padding-left'),
                itemLeftPos = Item.offsetLeft - this.Nav.getBoundingClientRect().left;

            new Fx.Scroll(this.Nav).start(itemLeftPos - parseInt(paddingLeft) - parseInt(marginLeft), 0);
        },

        /**
         * Check if element is in viewport
         * @param element
         * @return {boolean}
         */
        $isInViewport: function (element) {
            const rect = element.getBoundingClientRect();

            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
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

        // region drag to scroll

        /**
         * Init drag to scroll
         */
        $initDragToScroll: function () {
            if (this.navTab.scrollWidth <= this.navTab.clientWidth) {
                return;
            }

            this.navTab.addEventListener('mousedown', this.$mouseDownHandler);
        },

        /**
         * Move handler
         *
         * @param e
         */
        $mouseMoveHandler: function (e) {
            // How far the mouse has been moved
            const dx = e.clientX - this.navPos.x;

            if (this.navPos.x !== dx) {
                this.clicked = true;
            }

            // Scroll the element
            this.navTab.scrollLeft = this.navPos.left - dx;
        },

        /**
         * Mouse down handler
         *
         * @param e
         */
        $mouseDownHandler: function (e) {
            this.navTab.style.userSelect = 'none';

            this.navPos = {
                left: this.navTab.scrollLeft, // The current scroll
                x   : e.clientX, // Get the current mouse position
            };

            document.addEventListener('mousemove', this.$mouseMoveHandler);
            document.addEventListener('mouseup', this.$mouseUpHandler);
        },

        /**
         * Mouse up handler
         */
        $mouseUpHandler: function () {
            document.removeEventListener('mousemove', this.$mouseMoveHandler);
            document.removeEventListener('mouseup', this.$mouseUpHandler);

            this.navTab.style.removeProperty('user-select');

            setTimeout(() => {
                this.clicked = false;
            }, 50);
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