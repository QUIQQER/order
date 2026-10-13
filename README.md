QUIQQER Order (ERP module)
========

![QUIQQER ORDER](bin/images/Readme.png)

The QUIQQER Order Module integrates complete order management in QUIQQER.

Paketname:

    quiqqer/order

Features (Funktionen)
--------

- Order system
    - Manager Orders
    - Provides an order process for the frontend
    - Integrates the Payment API to the Order Process
    - Integrates the Invoice Module
    - Automatic creation of invoices by orders
    - It is possible to create orders by hand in the administration.

- Basket (Shopping cart)
    - The shopping cart allows the user to place orders
    - There exists a basket and order overview in the administration

Installation
------------

The Package name is: quiqqer/order


MCP integration
---------------

Install the optional `quiqqer/ai-mcp` package and run package setup to expose the order tools.
Grant `quiqqer.order.mcp` to the MCP account. This permission allows reading orders and drafts;
write tools additionally require the corresponding order permissions.

| Tool | Purpose | Additional permission |
| --- | --- | --- |
| `order_get` | Read an order, or a draft with `draft=true` | — |
| `order_search` | Search orders or drafts with pagination, customer and status filters | — |
| `order_draft_create` | Prepare a new order draft | `quiqqer.order.create` |
| `order_draft_update` | Edit an unsubmitted draft owned by the request user, or accessible to a superuser | `quiqqer.order.update` |
| `order_draft_review` | Refresh prices and quantities, validate addresses and list available payment methods | `quiqqer.order.update` |
| `order_draft_submit` | Convert a reviewed draft into a real order | `quiqqer.order.create` |
| `order_reorder` | Prepare a fresh draft from an existing order | `quiqqer.order.copy`, `quiqqer.order.create` |
| `order_update` | Apply permitted changes to an existing order | `quiqqer.order.update` |

Identifiers accept numeric ids, prefixed order numbers and UUIDs/hashes. Orders and drafts
are resolved separately, so identical numeric ids do not select the wrong record.
Draft data supports `customerId`, `currency`, `addressInvoice`, `addressDelivery`, `articles`,
`paymentId`, `status` and `comment`. Supplying `articles` replaces the complete article list;
an empty delivery address removes it. Address fields follow the existing order address API.
Successful or invoiced orders only allow `comment` and processing `status` changes.

Reorders copy customer, addresses, currency and article snapshots into a new draft.
Use `order_draft_review` to refresh prices and check the draft. It returns eligible payment methods,
which can be selected through `paymentId`. Then call `order_draft_submit` with the draft's `uuid`,
the reviewed `totals.sum` as `expectedTotal`, and its `currency`. Submission checks the final
recalculation before creating the order; changed totals, missing articles or changed quantities
stop creation. Retrying the same draft UUID returns the existing order instead of creating another.

Submission uses the existing order creation workflow, including configured automatic invoicing
and order events. Preparation and review keep the record as a draft until submission.

Contribution
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/order/issues
- Source Code: https://dev.quiqqer.com/quiqqer/order

Support
-------

If you have found an error or want improvements, please send an e-mail to support@pcsg.de.


Licence
-------

- GPL-3.0+
- PCSG QEL-1.0

Developers
--------

- onQuiqqerOrderSuccessful [Order]
- onQuiqqerOrderCreated [Order]
- onQuiqqerOrderDeleteBegin [Order]
- onQuiqqerOrderDelete [$orderId, $orderData]
- onQuiqqerOrderCopyBegin [Order]
- onQuiqqerOrderCopy [Order]
- onQuiqqerOrderUpdateBegin [Order]
- onQuiqqerOrderUpdate [Order]
- quiqqerOrderOrderProcessCheckoutOutput [AbstractOrderingStep, &text]
- onQuiqqerOrderPaidStatusChanged [Order, currentPaidStatus, previousPaidStatus]
- onQuiqqerOrderProcessStatusChange [Order, QUI\ERP\Order\ProcessingStatus\Status]
- quiqqerOrderCustomerDataSave [QUI\ERP\Order\Controls\OrderProcess\CustomerData]
- quiqqerOrderCustomerDataSaveEnd [QUI\ERP\Order\Controls\OrderProcess\CustomerData]
- quiqqerOrderBasketRemovePos [QUI\ERP\Order\Basket\Basket, pos]
- quiqqerOrderBasketToOrder [QUI\ERP\Order\Basket\BasketOrder, $Order, QUI\ERP\Products\Product\ProductList]
- onQuiqqerOrderProcessSendBegin [QUI\ERP\Order\OrderProcess]
- onQuiqqerOrderProcessSendCreateOrder [QUI\ERP\Order\OrderProcess]
- onQuiqqerOrderProcessSend [QUI\ERP\Order\OrderProcess]
- onQuiqqerOrderCustomerChange [Order]
- onQuiqqerOrderCustomerCustomerSet [Order]
- onQuiqqerBasketImport [Basket Instance, ProductList]

### Order Events

- onOrderStart [\QUI\ERP\Order\OrderProcess]
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
- onQuiqqer::order::orderProcessFinish [Collector, User, Order]
