/**
 * Select an order status status
 *
 * @module package/quiqqer/order/bin/backend/utils/StatusSelect
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onChange [value, this]
 * @event onLoaded [this] - Fires if all statusses have been loaded
 */
define('package/quiqqer/order/bin/backend/utils/StatusSelect', [

    'qui/controls/Control',
    'qui/controls/buttons/Select',

    'package/quiqqer/order/bin/backend/ProcessingStatus',

    'css!package/quiqqer/order/bin/backend/utils/StatusSelect.css'

], function (QUIControl, QUISelect, Statuses) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/backend/utils/StatusSelect',

        Binds: [
            '$onImport',
            '$setValue',
            '$onInject',
            'setValue'
        ],

        options: {
            showIcons            : false,
            placeholderText      : false,
            placeholderSelectable: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Color  = null;
            this.$Select = null;
            this.$Input  = null;
            this.$Elm    = null;

            this.$fireEvents = true;

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * create the domnode element
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'order-status-select field-container-field'
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.$Color = new Element('div', {
                'class': 'order-status-select-color'
            }).inject(this.$Elm);

            this.$Select = new QUISelect({
                'class'  : 'order-status-select-qui',
                showIcons: false,
                events   : {
                    onChange: function (value) {
                        var data  = self.getAttribute('data');
                        var entry = data.filter(function (entry) {
                            return entry.id === value;
                        });

                        if (!entry.length) {
                            self.$Color.setStyle('background-color', null);
                            self.$Input.value = '';

                            if (self.$fireEvents) {
                                self.fireEvent('change', [value, self]);
                            }

                            return;
                        }

                        entry = entry[0];

                        self.$Color.setStyle('background-color', entry.color);
                        self.$Input.value = entry.id;

                        if (self.$fireEvents) {
                            self.fireEvent('change', [value, self]);
                        }
                    }
                }
            }).inject(this.$Elm);

            Statuses.getList().then(function (result) {
                var data = result.data;

                self.setAttribute('data', data);

                for (var i = 0, len = data.length; i < len; i++) {
                    self.$Select.appendChild(
                        data[i].title,
                        data[i].id
                    );
                }

                if (self.$Input.value !== '') {
                    self.$Select.setValue(self.$Input.value);
                }

                self.fireEvent('loaded', [self]);
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            if (this.$Elm.nodeName === 'INPUT') {
                this.$Input      = this.$Elm;
                this.$Input.type = 'hidden';
            }

            if (this.$Elm.nodeName === 'SELECT') {
                this.$Input = this.$Elm;
                this.$Input.setStyle('display', 'none');
            }

            this.create().wraps(this.$Input);
            this.$onInject();
        },

        setValue: function (statusId) {
            this.$Input.value = statusId;

            this.$fireEvents = false;
            this.$Select.setValue(statusId);
            this.$fireEvents = true;
        },

        /**
         * Return the selected value
         *
         * @return {integer}
         */
        getValue: function () {
            return parseInt(this.$Input.value);
        }
    });
});