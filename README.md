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

- onQuiqqerOrderPaidStatusChanged [Order, currentPaidStatus, previousPaidStatus]
- onQuiqqerOrderProcessStatusChange [Order, QUI\ERP\Order\ProcessingStatus\Status]

### Order Events

- onOrderStart [Order]
- onOrderSuccess [Order]
- onOrderAbort [Order]

### Template Events

- onQuiqqer::order::orderProcessBasketBegin [Collector, Basket]
- onQuiqqer::order::orderProcessBasketEnd [Collector, Basket]

- onQuiqqer::order::orderProcessCustomerDataBegin [Collector, User, Address]
- onQuiqqer::order::orderProcessCustomerData [Collector, User, Address]
- onQuiqqer::order::orderProcessCustomerEnd [Collector, User, Address]

- onQuiqqer::order::orderProcessCheckoutBegin [Collector, User, Order]
- onQuiqqer::order::orderProcessCheckoutEnd [Collector, User, Order]