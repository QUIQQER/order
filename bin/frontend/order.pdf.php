<?php

use QUI\System\Log;
use Symfony\Component\HttpFoundation\Response;

define('QUIQQER_SYSTEM', true);

require_once dirname(__FILE__, 5) . '/header.php';

$Response = QUI::getGlobalResponse();
$User = QUI::getUserBySession();

if (!isset($_REQUEST['order'])) {
    $Response->setStatusCode(
        Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

if (QUI::getUsers()->isAuth($User) === false) {
    $Response->setStatusCode(
        Response::HTTP_FORBIDDEN
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

try {
    $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($_REQUEST['order']);
    $Invoice = $Order->getInvoice();
    $Customer = $Invoice->getCustomer();
} catch (Exception $Exception) {
    $Response->setStatusCode(
        Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

// only real invoices
if (!($Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice)) {
    $Response->setStatusCode(
        Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

// is the user allowed to open the invoice
if ($User->getUUID() !== $Customer->getUUID()) {
    $Response->setStatusCode(
        Response::HTTP_FORBIDDEN
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}


try {
    $HtmlPdfDocument = $Invoice->getView()->toPDF();
    $HtmlPdfDocument->download();
} catch (Exception $Exception) {
    Log::writeException($Exception);

    $Response->setStatusCode(
        Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}
