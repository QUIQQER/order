/**
 * @module package/quiqqer/order/bin/backend/controls/panels/order/Customer
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/windows/Confirm
 * @require Users
 * @require Locale
 */
define('package/quiqqer/order/bin/backend/controls/panels/order/InvoiceAddress', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Users',
    'Locale'

], function (QUI, QUIControl, QUIConfirm, Users, QUILocale) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/order/InvoiceAddress',

        Binds: [
            '$onImport',
            '$onUserChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Customer = null;
            this.$Company  = null;
            this.$Street   = null;
            this.$ZIP      = null;
            this.$City     = null;

            this.$loaded   = false;
            this.$setValue = false;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            this.$Company = Elm.getElement('[name="company"]');
            this.$Street  = Elm.getElement('[name="street_no"]');
            this.$ZIP     = Elm.getElement('[name="zip"]');
            this.$City    = Elm.getElement('[name="city"]');

            var Customer = Elm.getElement('[name="customer"]');

            Customer.set('data-qui', 'controls/users/Select');
            Customer.set('data-qui-options-max', 1);
            Customer.set('data-qui-options-multiple', 0);

            QUI.parse(Elm).then(function () {
                self.$Customer = QUI.Controls.getById(Customer.get('data-quiid'));
                self.$Customer.addEvent('change', self.$onUserChange);

                self.$Company.disabled = false;
                self.$Street.disabled  = false;
                self.$ZIP.disabled     = false;
                self.$City.disabled    = false;

                self.$loaded = true;
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * event: on customer change
         */
        $onUserChange: function () {
            if (this.$loaded === false) {
                return;
            }

            if (this.$setValue === true) {
                this.$setValue = false;
                return;
            }

            var self = this;

            if (this.$Company.value === '' &&
                this.$Street.value === '' &&
                this.$ZIP.value === '' &&
                this.$City.value === '') {

                this.getUser().then(function (User) {
                    return User.getAddressList();
                }).then(function (address) {
                    if (!address.length) {
                        return;
                    }

                    if (address.length === 1) {
                        self.$Company.value = address[0].company;
                        self.$Street.value  = address[0].street_no;
                        self.$ZIP.value     = address[0].zip;
                        self.$City.value    = address[0].city;

                        self.fireEvent('change', [self]);
                        return;
                    }

                    self.openAddressSelect().then(function (address) {
                        self.$Company.value = address.company;
                        self.$Street.value  = address.street_no;
                        self.$ZIP.value     = address.zip;
                        self.$City.value    = address.city;

                        self.fireEvent('change', [self]);
                    });
                });

                return;
            }

            this.openAddressSelect().then(function (address) {
                self.$Company.value = address.company;
                self.$Street.value  = address.street_no;
                self.$ZIP.value     = address.zip;
                self.$City.value    = address.city;

                self.fireEvent('change', [self]);
            });
        },

        /**
         * Return the current value
         *
         * @return {{company: (string|*), street: (string|*), zip: (string|*), city: (string|*)}}
         */
        getValue: function () {
            return {
                uid      : this.$Customer.getValue(),
                company  : this.$Company.value,
                street_no: this.$Street.value,
                zip      : this.$ZIP.value,
                city     : this.$City.value
            };
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function (value) {
            if (typeOf(value) !== 'object') {
                return;
            }

            if ("company" in value) {
                this.$Company.value = value.company;
            }

            if ("street_no" in value) {
                this.$Street.value = value.street_no;
            }

            if ("zip" in value) {
                this.$ZIP.value = value.zip;
            }

            if ("city" in value) {
                this.$City.value = value.city;
            }

            if ("uid" in value) {
                this.$setValue = true;

                if (this.$Customer) {
                    this.$Customer.setValue(value.uid);
                } else {
                    this.getElm().getElement('[name="customer"]').value = value.uid;
                }
            }
        },

        /**
         * Return the selected user
         *
         * @return {Promise}
         */
        getUser: function () {
            if (!this.$Customer) {
                return Promise.reject('No User-ID');
            }

            var userId = this.$Customer.getValue();

            if (!userId) {
                return Promise.reject('No User-ID');
            }

            var User = Users.get(userId);

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * Open the address select
         */
        openAddressSelect: function () {
            var self = this;

            return new Promise(function (resolve, reject) {

                new QUIConfirm({
                    icon     : 'fa fa-address-card-o',
                    title    : QUILocale.get(lg, 'order.address.confirm.title'),
                    maxHeight: 300,
                    maxWidth : 450,
                    autoclose: false,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();
                            Win.getContent().set('html', '');

                            self.getUser().then(function (User) {
                                return User.getAddressList();
                            }).then(function (addresses) {
                                new Element('div', {
                                    html: QUILocale.get(lg, 'order.address.confirm.information')
                                }).inject(Win.getContent());

                                var Select = new Element('select', {
                                    styles: {
                                        margin  : '20px auto 0',
                                        maxWidth: 400,
                                        width   : '100%'
                                    }
                                });

                                for (var i = 0, len = addresses.length; i < len; i++) {
                                    new Element('option', {
                                        value       : addresses[i].id,
                                        html        : addresses[i].text,
                                        'data-value': JSON.encode(addresses[i])
                                    }).inject(Select);
                                }

                                Select.inject(Win.getContent());

                                Win.Loader.hide();
                            });
                        },

                        onCancel: reject,

                        onSubmit: function (Win) {
                            var Content = Win.getContent(),
                                Select  = Content.getElement('select');

                            var options = Select.getElements('option').filter(function (Option) {
                                return Option.value === Select.value;
                            });

                            if (!options.length) {
                                return;
                            }

                            resolve(
                                JSON.decode(options[0].get('data-value'))
                            );

                            Win.close();
                        }
                    }
                }).open();
            });
        }
    });
});
