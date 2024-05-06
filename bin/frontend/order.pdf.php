<?php

define('QUIQQER_SYSTEM', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/header.php';

$Response = QUI::getGlobalResponse();
$User = QUI::getUserBySession();

if (!isset($_REQUEST['order'])) {
    $Response->setStatusCode(
        \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

if (QUI::getUsers()->isAuth($User) === false) {
    $Response->setStatusCode(
        \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

try {
    $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($_REQUEST['order']);
    $Invoice = $Order->getInvoice();
    $Customer = $Invoice->getCustomer();
} catch (\Exception $Exception) {
    $Response->setStatusCode(
        \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}

// is the user allowed to open the invoice
if ($User->getUUID() !== $Customer->getUUID()) {
    $Response->setStatusCode(
        \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}


try {
    $HtmlPdfDocument = $Invoice->getView()->toPDF();
    $HtmlPdfDocument->download();
} catch (\Exception $Exception) {
    \QUI\System\Log::writeException($Exception);

    $Response->setStatusCode(
        \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST
    );

    echo $Response->getContent();
    $Response->send();
    exit;
}
