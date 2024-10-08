/**
 * @module package/quiqqer/order/bin/backend/controls/panels/order/Address
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/panels/order/Address', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/countries/bin/Countries',
    'Users'

], function(QUI, QUIControl, QUIConfirm, Countries, Users) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/order/bin/backend/controls/panels/order/Address',

        Binds: [
            'refresh',
            '$onImport',
            '$onSetAttribute',
            '$onSelectChange'
        ],

        options: {
            userId: false
        },

        initialize: function(options) {
            this.parent(options);

            this.$Addresses = null;
            this.$Company = null;
            this.$Street = null;
            this.$ZIP = null;
            this.$City = null;
            this.$Country = null;

            this.$Firstname = null;
            this.$Lastname = null;

            this.$loaded = false;
            this.$userId = this.getAttribute('userId');

            this.addEvents({
                onImport: this.$onImport,
                onSetAttribute: this.$onSetAttribute
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            const self = this,
                Elm = this.getElm();

            function ignoreAutoFill(node)
            {
                node.role = 'presentation';
                node.autocomplete = 'off';
            }

            this.$Firstname = Elm.getElement('[name="firstname"]');
            this.$Lastname = Elm.getElement('[name="lastname"]');
            this.$Company = Elm.getElement('[name="company"]');
            this.$Street = Elm.getElement('[name="street_no"]');
            this.$ZIP = Elm.getElement('[name="zip"]');
            this.$City = Elm.getElement('[name="city"]');
            this.$Country = Elm.getElement('[name="country"]');

            if (!this.$Firstname) {
                this.$Firstname = new Element('input');
            }

            if (!this.$Lastname) {
                this.$Lastname = new Element('input');
            }

            this.$Addresses = Elm.getElement('[name="addresses"]');
            this.$Addresses.addEvent('change', this.$onSelectChange);

            this.$Firstname.disabled = false;
            this.$Lastname.disabled = false;
            this.$Company.disabled = false;
            this.$Street.disabled = false;
            this.$ZIP.disabled = false;
            this.$City.disabled = false;

            ignoreAutoFill(this.$Firstname);
            ignoreAutoFill(this.$Lastname);
            ignoreAutoFill(this.$Company);
            ignoreAutoFill(this.$Street);
            ignoreAutoFill(this.$ZIP);
            ignoreAutoFill(this.$City);

            Countries.getCountries().then(function(result) {
                new Element('option', {
                    value: '',
                    html: ''
                }).inject(self.$Country);

                for (let code in result) {
                    if (!result.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html: result[code]
                    }).inject(self.$Country);
                }

                if (self.getAttribute('country')) {
                    self.$Country.value = self.getAttribute('country');
                }

                self.$Country.disabled = false;
                self.$loaded = true;
            });
        },

        /**
         * Return the current value
         *
         * @return {{company: *, street: (*|Document.street_no|Document.address.street_no), zip: *, city: (string|string|*)}}
         */
        getValue: function() {
            return {
                id: this.$Addresses.value,
                aid: this.$Addresses.value,
                uid: this.$userId,
                firstname: this.$Firstname.value,
                lastname: this.$Lastname.value,
                company: this.$Company.value,
                street_no: this.$Street.value,
                zip: this.$ZIP.value,
                city: this.$City.value,
                country: this.$Country.value
            };
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function(value) {
            if (typeOf(value) !== 'object') {
                return;
            }

            if ('company' in value) {
                this.$Company.value = value.company;
            }

            if ('street_no' in value) {
                this.$Street.value = value.street_no;
            }

            if ('zip' in value) {
                this.$ZIP.value = value.zip;
            }

            if ('city' in value) {
                this.$City.value = value.city;
            }

            if ('firstname' in value) {
                this.$Firstname.value = value.firstname;
            }

            if ('lastname' in value) {
                this.$Lastname.value = value.lastname;
            }

            if ('country' in value) {
                if (this.$loaded) {
                    this.$Country.value = value.country;
                } else {
                    this.setAttribute('country', value.country);
                }
            }

            if ('uid' in value) {
                this.$userId = value.uid;
                this.loadAddresses().then(() => {
                    if ('id' in value) {
                        this.$Addresses.value = value.id;
                    }
                }).catch(function() {
                    this.$Addresses.disabled = true;
                }.bind(this));
            }
        },

        /**
         * Return the selected user
         *
         * @return {Promise}
         */
        getUser: function() {
            if (!this.$userId) {
                return Promise.reject('No User-ID');
            }

            const User = Users.get(this.$userId);

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * Refresh the address list
         *
         * @return {Promise}
         */
        refresh: function() {
            const self = this;

            this.$Addresses.set('html', '');

            if (!this.$userId) {
                return Promise.reject();
            }

            return this.loadAddresses().then(function() {
                self.$onSelectChange();
            }).catch(function() {
                self.$Addresses.disabled = true;
            });
        },

        /**
         * Load the addresses
         */
        loadAddresses: function() {
            const self = this;

            this.$Addresses.set('html', '');
            this.$Addresses.disabled = true;

            if (!this.$userId) {
                return Promise.reject();
            }

            return this.getUser().then(function(User) {
                return User.getAddressList();
            }).then(function(addresses) {
                new Element('option', {
                    value: '',
                    html: '',
                    'data-value': ''
                }).inject(self.$Addresses);

                for (let i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value: addresses[i].id,
                        html: addresses[i].text,
                        'data-value': JSON.encode(addresses[i])
                    }).inject(self.$Addresses);
                }

                self.$Addresses.disabled = false;

                if (addresses.length) {
                    self.$Addresses.value = addresses[0].id;
                }
            }).catch(function(err) {
                if (typeof err.getMessage === 'function') {
                    console.error(err.getMessage());
                    return;
                }

                if (typeOf(err) === 'classes/users/User') {
                    // user not found
                    return;
                }

                console.error(err);
            });
        },

        /**
         * event : on select change
         */
        $onSelectChange: function() {
            const Select = this.$Addresses;

            const options = Select.getElements('option').filter(function(Option) {
                return Option.value === Select.value;
            });

            if (!options.length) {
                return;
            }

            const data = JSON.decode(options[0].get('data-value'));

            this.$Company.value = data.company;
            this.$Street.value = data.street_no;
            this.$ZIP.value = data.zip;
            this.$City.value = data.city;
            this.$Firstname.value = data.firstname;
            this.$Lastname.value = data.lastname;
            this.$Country.value = data.country;
        },

        /**
         * event: on attribute change
         *
         * @param {String} key
         * @param {String} value
         */
        $onSetAttribute: function(key, value) {
            const self = this;

            if (key === 'userId') {
                this.$userId = value;

                self.refresh().then(function() {
                    self.$Addresses.disabled = false;
                }).catch(function() {
                    self.$Addresses.disabled = true;
                });
            }
        }
    });
});
