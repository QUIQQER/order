/**
 * @module package/quiqqer/order/bin/backend/controls/settings/ProcessingStatus
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/order/bin/backend/controls/settings/ProcessingStatus', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/order/bin/backend/ProcessingStatus',
    'controls/grid/Grid',
    'Locale',
    'Mustache',

    'text!package/quiqqer/order/bin/backend/controls/settings/ProcessingStatus.html',
    'css!package/quiqqer/order/bin/backend/controls/settings/ProcessingStatus.css'

], function (QUI, QUIControl, QUIConfirm, ProcessingStatus, Grid, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/backend/controls/settings/ProcessingStatus',

        Binds: [
            '$onChange',
            '$onImport',
            'refresh',
            '$refreshButtonStatus',
            'openCreateDialog',
            'openDeleteDialog',
            '$onEditClick',
            '$onDeleteClick'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Elm   = null;
            this.$Input = null;
            this.$Grid  = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Refresh
         */
        refresh: function () {
            var self = this;

            ProcessingStatus.getList().then(function (result) {
                for (var i = 0, len = result.data.length; i < len; i++) {
                    result.data[i].colorNode = new Element('span', {
                        html   : result.data[i].color,
                        'class': 'quiqqer-order-processing-status-color',
                        styles : {
                            backgroundColor: result.data[i].color
                        }
                    });

                    result.data[i].notificationStatus = new Element('span', {
                        html: result.data[i].notification ?
                            '<span class="fa fa-check"></span>' :
                            '<span class="fa fa-close"></span>'
                    });
                }

                self.$Grid.setData(result);
                self.$refreshButtonStatus();
            });
        },

        /**
         * resize the grid
         */
        resize: function () {
            this.$Grid.setWidth(
                this.$Elm.getSize().x
            );
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.$Input = this.getElm();
            this.$Elm   = new Element('div').wraps(this.$Input);

            this.$Elm.setStyles({
                width: '100%'
            });

            var w = this.$Elm.getSize().x;

            var Container = new Element('div', {
                styles: {
                    height: 300,
                    width : w
                }
            }).inject(this.$Elm);


            this.$Grid = new Grid(Container, {
                height     : 300,
                width      : w,
                buttons    : [{
                    name  : 'add',
                    text  : QUILocale.get('quiqqer/system', 'add'),
                    events: {
                        onClick: this.openCreateDialog
                    }
                }, {
                    type: 'separator'
                }, {
                    name    : 'edit',
                    text    : QUILocale.get('quiqqer/system', 'edit'),
                    disabled: true,
                    events  : {
                        onClick: this.$onEditClick
                    }
                }, {
                    name    : 'delete',
                    text    : QUILocale.get('quiqqer/system', 'remove'),
                    disabled: true,
                    events  : {
                        onClick: this.$onDeleteClick
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lg, 'processingStatus.grid.id'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 60
                }, {
                    header   : QUILocale.get(lg, 'processingStatus.grid.color'),
                    dataIndex: 'colorNode',
                    dataType : 'node',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType : 'integer',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'processingStatus.grid.notification'),
                    dataIndex: 'notificationStatus',
                    dataType : 'node',
                    width    : 150
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onEditClick
            });

            this.$Grid.refresh();
            this.resize();
        },

        /**
         * Refresh the grid button status (disabled/enabled)
         */
        $refreshButtonStatus: function () {
            var selected = this.$Grid.getSelectedIndices();

            var Edit = this.$Grid.getButtons().filter(function (Button) {
                return Button.getAttribute('name') === 'edit';
            })[0];

            var Delete = this.$Grid.getButtons().filter(function (Button) {
                return Button.getAttribute('name') === 'delete';
            })[0];

            if (!selected.length) {
                Edit.disable();
                Delete.disable();
                return;
            }

            Edit.enable();
            Delete.enable();
        },

        // region Dialogs

        /**
         * Opens the add dialog
         * - Create a Processing Status
         */
        openCreateDialog: function () {
            var self = this;

            new QUIConfirm({
                icon     : 'fa fa-plus',
                title    : QUILocale.get(lg, 'dialog.processingStatus.create.title'),
                maxHeight: 470,
                maxWidth : 600,
                autoclose: false,
                events   : {
                    onOpen: function (Win) {
                        var Content = Win.getContent();

                        Win.Loader.show();

                        var lgPrefix = 'dialog.processingStatus.template.';

                        Content.addClass('quiqqer-order-processing-status-window');
                        Content.set('html', Mustache.render(template, {
                            labelTitle       : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                            labelStatusNo    : QUILocale.get(lg, lgPrefix + 'labelStatusNo'),
                            labelColor       : QUILocale.get(lg, lgPrefix + 'labelColor'),
                            labelNotification: QUILocale.get(lg, lgPrefix + 'labelNotification'),
                            descNotification : QUILocale.get(lg, lgPrefix + 'descNotification')
                        }));

                        var Form = Content.getElement('form');

                        ProcessingStatus.getNextId().then(function (nextId) {
                            Form.elements.id.value = nextId;

                            return QUI.parse(Content);
                        }).then(function () {
                            Win.Loader.hide();
                        });
                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var Form = Win.getContent().getElement('form');

                        require([
                            'qui/utils/Form',
                            'package/quiqqer/order/bin/backend/ProcessingStatus'
                        ], function (FormUtils, ProcessingStatus) {
                            var data  = FormUtils.getFormData(Form),
                                title = {};

                            try {
                                title = JSON.decode(data.title);
                            } catch (e) {
                            }

                            ProcessingStatus.createProcessingStatus(
                                data.id,
                                data.color,
                                title,
                                data.notification.checked
                            ).then(function () {
                                return Win.close();
                            }).then(function () {
                                self.refresh();
                            });
                        });
                    }
                }
            }).open();
        },

        /**
         * Opens the dialog to edit a status
         *
         * @param {Number|String} statusId - ID of the Status
         */
        openEditDialog: function (statusId) {
            var self = this;
            var data = this.$Grid.getData().filter(function (entry) {
                return entry.id === statusId;
            });

            if (!data.length) {
                return;
            }

            data = data[0];

            new QUIConfirm({
                icon     : 'fa fa-edit',
                title    : QUILocale.get(lg, 'dialog.processingStatus.edit.title'),
                maxHeight: 470,
                maxWidth : 600,
                autoclose: false,
                ok_button: {
                    text     : QUILocale.get('quiqqer/core', 'edit'),
                    textimage: 'fa fa-edit'
                },
                events   : {
                    onOpen: function (Win) {
                        var Content = Win.getContent();

                        Win.Loader.show();

                        var lgPrefix = 'dialog.processingStatus.template.';

                        Content.addClass('quiqqer-order-processing-status-window');
                        Content.set('html', Mustache.render(template, {
                            labelTitle       : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                            labelStatusNo    : QUILocale.get(lg, lgPrefix + 'labelStatusNo'),
                            labelColor       : QUILocale.get(lg, lgPrefix + 'labelColor'),
                            labelNotification: QUILocale.get(lg, lgPrefix + 'labelNotification'),
                            descNotification : QUILocale.get(lg, lgPrefix + 'descNotification')
                        }));

                        var Form = Content.getElement('form');

                        ProcessingStatus.getProcessingStatus(data.id).then(function (details) {
                            Form.elements.id.value             = details.id;
                            Form.elements.color.value          = details.color;
                            Form.elements.title.value          = JSON.encode(details.title);
                            Form.elements.notification.checked = details.notification;

                            return QUI.parse(Content);
                        }).then(function () {
                            Win.Loader.hide();
                        });
                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var Form = Win.getContent().getElement('form');

                        require([
                            'qui/utils/Form',
                            'package/quiqqer/order/bin/backend/ProcessingStatus'
                        ], function (FormUtils, ProcessingStatus) {
                            var data  = FormUtils.getFormData(Form),
                                title = {};

                            try {
                                title = JSON.decode(data.title);
                            } catch (e) {
                            }

                            ProcessingStatus.updateProcessingStatus(
                                data.id,
                                data.color,
                                title,
                                data.notification
                            ).then(function () {
                                return Win.close();
                            }).then(function () {
                                self.refresh();
                            });
                        });
                    }
                }
            }).open();
        },

        /**
         * Opens the dialog to delete a status
         *
         * @param {Number|String} statusId - ID of the Status
         */
        openDeleteDialog: function (statusId) {
            var self = this;
            var data = this.$Grid.getData().filter(function (entry) {
                return entry.id === statusId;
            });

            if (!data.length) {
                return;
            }

            new QUIConfirm({
                icon       : 'fa fa-trash',
                texticon   : 'fa fa-trash',
                title      : QUILocale.get(lg, 'dialog.processingStatus.delete.title'),
                text       : QUILocale.get(lg, 'dialog.processingStatus.delete.text'),
                information: QUILocale.get(lg, 'dialog.processingStatus.delete.information', {
                    id   : data[0].id,
                    title: data[0].title
                }),
                maxHeight  : 400,
                maxWidth   : 600,
                autoclose  : false,
                ok_button  : {
                    text     : QUILocale.get('quiqqer/core', 'remove'),
                    textimage: 'fa fa-trash'
                },
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        ProcessingStatus.deleteProcessingStatus(statusId).then(function () {
                            Win.close();
                            self.refresh();
                        });
                    }
                }
            }).open();
        },

        // endregion

        //region Buttons Events

        /**
         * event : on edit click
         */
        $onEditClick: function () {
            var data = this.$Grid.getSelectedData();

            if (data.length) {
                this.openEditDialog(data[0].id);
            }
        },

        /**
         * event : on delete click
         */
        $onDeleteClick: function () {
            var data = this.$Grid.getSelectedData();

            if (data.length) {
                this.openDeleteDialog(data[0].id);
            }
        }

        // endregion
    });
});