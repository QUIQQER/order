ERP Order
========

This module is not functional at the moment.
This module is in heavy development 

Paketname:

    quiqqer/order


Features (Funktionen)
--------

- Ordersystem
- Basket


Installation
------------

Der Paketname ist: quiqqer/order


Mitwirken
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/order/issues
- Source Code: https://dev.quiqqer.com/quiqqer/order


Support
-------

Falls Sie einen Fehler gefunden haben oder Verbesserungen wünschen,
senden Sie bitte eine E-Mail an support@pcsg.de.


Lizenz
-------


Entwickler
--------

- onQuiqqerOrderSuccessful [Order]

- onQuiqqerOrderDeleteBegin [Order]
- onQuiqqerOrderDelete [$orderId, $orderData]

- onQuiqqerOrderCopyBegin [Order]
- onQuiqqerOrderCopy [Order]

- onQuiqqerOrderUpdateBegin [Order]
- onQuiqqerOrderUpdate [Order]

- quiqqerOrderOrderProcessCheckoutOutput [AbstractOrderingStep, &text]

### Order Events

- onOrderStart [Order]
- onOrderSuccess [Order]
- onOrderAbort [Order]