/**
 * @module package/quiqqer/order/bin/backend/controls/panels/order/Communication
 */
define('package/quiqqer/order/bin/backend/controls/panels/order/Communication', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'package/quiqqer/order/bin/backend/Orders',
    'Locale',
    'Mustache',
    'Editors',

    'text!package/quiqqer/order/bin/backend/controls/panels/order/Communication.html',
    'css!package/quiqqer/order/bin/backend/controls/panels/order/Communication.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, Comments, Orders,
             QUILocale, Mustache, Editors, template) {
    "use strict";

    var lg = 'quiqqer/order';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/order/bin/backend/controls/panels/order/Communication',

        Binds: [
            '$onInject',
            'openAddCommentDialog'
        ],

        options: {
            orderId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode Element
         */
        create: function () {
            var Elm = this.parent();

            Elm.set('html', Mustache.render(template, {
                textMailsTitle   : QUILocale.get(lg, 'statusMailsTitle'),
                textCommentsTitle: QUILocale.get(lg, 'commentsTitle')
            }));

            this.$Comments = new Comments({
                comments: []
            }).inject(Elm.getElement('.quiqqer-order-comments'));

            new QUIButton({
                textimage: 'fa fa-plus',
                text     : QUILocale.get(lg, 'dialog.add.comment.title'),
                events   : {
                    onClick: this.openAddCommentDialog
                }
            }).inject(Elm.getElement('.quiqqer-order-comments-button'));

            return Elm;
        },

        /**
         * Refresh the data
         */
        refresh: function () {
            var self = this;

            Orders.get(this.getAttribute('orderId')).then(function (data) {
                self.$Comments.unserialize(data.comments);
                self.fireEvent('load', [self]);
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * Open the add dialog window
         */
        openAddCommentDialog: function () {
            var self = this;

            new QUIConfirm({
                title    : QUILocale.get(lg, 'dialog.add.comment.title'),
                icon     : 'fa fa-edit',
                maxHeight: 600,
                maxWidth : 800,
                events   : {
                    onOpen: function (Win) {
                        Win.getContent().set('html', '');
                        Win.Loader.show();

                        Editors.getEditor(null).then(function (Editor) {
                            Win.$Editor = Editor;

                            Win.$Editor.addEvent('onLoaded', function () {
                                Win.$Editor.switchToWYSIWYG();
                                Win.$Editor.showToolbar();
                                Win.$Editor.setContent(self.getAttribute('content'));
                                Win.Loader.hide();
                            });

                            Win.$Editor.inject(Win.getContent());
                            Win.$Editor.setHeight(200);
                        });
                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        self.addComment(Win.$Editor.getContent()).then(function () {
                            return self.refresh();
                        }).then(function () {
                            Win.$Editor.destroy();
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        /**
         * add a comment to the order
         *
         * @param {String} message
         */
        addComment: function (message) {
            return Orders.addComment(this.getAttribute('orderId'), message);
        }
    });
});