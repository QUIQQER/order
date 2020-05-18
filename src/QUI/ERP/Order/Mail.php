<?php

/**
 * This file contains QUI\ERP\Order\Mail
 */

namespace QUI\ERP\Order;

use QUI;
use QUI\Projects\Site\Utils as SiteUtils;

/**
 * Class Mail
 *
 * @package QUI\ERP\Order
 */
class Mail
{
    /**
     * confirmation mail for the customer
     *
     * @param Order $Order
     * @throws QUI\Exception
     */
    public static function sendOrderConfirmationMail(Order $Order)
    {
        $Customer = $Order->getCustomer();
        $email    = $Customer->getAttribute('email');

        if (empty($email)) {
            QUI\System\Log::writeRecursive(
                QUI::getLocale()->get('quiqqer/order', 'message.order.missing.customer.mail', [
                    'orderId'    => $Order->getId(),
                    'customerId' => $Customer->getId()
                ])
            );

            return;
        }

        // create order data
        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $Order->getHash(),
            'template'  => 'OrderLikeBasket'
        ]);

        $Address  = null;
        $Customer = $Order->getCustomer();

        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user    = $Address->getName();
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        if (Settings::getInstance()->get('order', 'sendOrderConfirmationToAdmin') && QUI::conf('mail', 'admin_mail')) {
            $Mailer->addBCC(
                QUI::conf('mail', 'admin_mail')
            );
        }

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.subject', [
                'orderId' => $Order->getPrefixedId()
            ])
        );

        $Engine = QUI::getTemplateManager()->getEngine();
        $Order  = $OrderControl->getOrder();

        $Articles = $Order->getArticles()->toUniqueList();
        $Articles->hideHeader();

        $Shipping        = null;
        $DeliveryAddress = null;

        if ($Order->getShipping()) {
            $Shipping        = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();

            if ($DeliveryAddress) {
                $DeliveryAddress->setAttribute(
                    'template',
                    \dirname(__FILE__).'/MailTemplates/orderConfirmationAddress.html'
                );
            }
        }

        $InvoiceAddress = $Order->getInvoiceAddress();
        $InvoiceAddress->setAttribute('template', \dirname(__FILE__).'/MailTemplates/orderConfirmationAddress.html');

        // comment
        $comment = '';

        if (QUI::getSession()->get('comment-customer')) {
            $comment .= QUI::getSession()->get('comment-customer')."\n";
        }

        if (QUI::getSession()->get('comment-message')) {
            $comment .= QUI::getSession()->get('comment-message');
        }

        $comment = \trim($comment);


        $Engine->assign([
            'Shipping'        => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress'  => $InvoiceAddress,
            'Payment'         => $Order->getPayment(),
            'comment'         => $comment,

            'Order'    => $Order,
            'Articles' => $Articles,
            'Address'  => $Address,

            'message' => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.body', [
                'orderId'  => $Order->getPrefixedId(),
                'user'     => $user,
                'username' => $Customer->getUsername(),
            ])
        ]);

        $Mailer->setBody(
            $Engine->fetch(\dirname(__FILE__).'/MailTemplates/orderConfirmation.html')
        );

        $Mailer->send();
    }

    /**
     * confirmation mail for the admin
     *
     * @param Order $Order
     */
    public static function sendAdminOrderConfirmationMail(Order $Order)
    {
        $email = QUI::conf('mail', 'admin_mail');

        if (empty($email)) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'message.order.missing.admin.mail')
            );

            return;
        }

        // create order data
        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $Order->getHash(),
            'template'  => 'OrderLikeBasket'
        ]);

        $Address  = null;
        $Customer = $Order->getCustomer();
        $comments = $Order->getComments()->toArray();

        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user    = $Address->getName();
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/order', 'order.confirmation.admin.subject', [
                'orderId' => $Order->getPrefixedId()
            ])
        );

        self::addOrderMailAttachments($Mailer);


        try {
            $Engine = QUI::getTemplateManager()->getEngine();
            $Order  = $OrderControl->getOrder();

            $Articles = $Order->getArticles()->toUniqueList();
            $Articles->hideHeader();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedId(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }


        $Shipping        = null;
        $DeliveryAddress = null;

        if ($Order->getShipping()) {
            $Shipping        = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();
            $DeliveryAddress->setAttribute(
                'template',
                \dirname(__FILE__).'/MailTemplates/orderConfirmationAddress.html'
            );
        }

        $InvoiceAddress = $Order->getInvoiceAddress();
        $InvoiceAddress->setAttribute(
            'template',
            \dirname(__FILE__).'/MailTemplates/orderConfirmationAddress.html'
        );

        $Engine->assign([
            'Shipping'        => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress'  => $InvoiceAddress,
            'Payment'         => $Order->getPayment(),
            'comments'        => $comments,

            'Order'    => $Order,
            'Articles' => $Articles,
            'Address'  => $Address,

            'message' => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.admin.body', [
                'orderId'  => $Order->getPrefixedId(),
                'userId'   => $Customer->getId(),
                'username' => $user
            ])
        ]);

        $Mailer->setBody(
            $Engine->fetch(\dirname(__FILE__).'/MailTemplates/orderConfirmationAdmin.html')
        );

        try {
            $Mailer->send();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedId(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }
    }

    /**
     * @param $Mail
     */
    protected static function addOrderMailAttachments(QUI\Mail\Mailer $Mail)
    {
        // check if html2pdf is installed
        if (QUI::getPackageManager()->isInstalled('quiqqer/htmltopdf') === false) {
            return;
        }

        $Package  = QUI::getPackage('quiqqer/order');
        $Config   = $Package->getConfig();
        $language = QUI::getLocale()->getCurrent();

        try {
            $DefaultProject = QUI::getProjectManager()->getStandard();
            $Project        = QUI::getProject($DefaultProject->getName(), $language);
        } catch (QUI\Exception $Exception) {
            return;
        }

        $privacyPolicy      = (int)$Config->getValue('mails', 'privacyPolicy');
        $termsAndConditions = (int)$Config->getValue('mails', 'termsAndConditions');
        $cancellationPolicy = (int)$Config->getValue('mails', 'cancellationPolicy');

        if ($privacyPolicy) {
            try {
                $sites = $Project->getSites([
                    'where' => [
                        'type' => 'quiqqer/sitetypes:types/privacypolicy'
                    ],
                    'limit' => 1
                ]);

                if (isset($sites[0])) {
                    $Site = $sites[0];
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if ($termsAndConditions) {
            try {
                $sites = $Project->getSites([
                    'where' => [
                        'type' => 'quiqqer/sitetypes:types/generalTermsAndConditions'
                    ],
                    'limit' => 1
                ]);

                if (isset($sites[0])) {
                    $Site = $sites[0];
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if ($cancellationPolicy) {
            try {
                $sites = $Project->getSites([
                    'where' => [
                        'type' => 'quiqqer/order-cancellation-policy:types/privacypolicy'
                    ],
                    'limit' => 1
                ]);

                if (isset($sites[0])) {
                    $Site = $sites[0];
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }
    }

    /**
     * @return string
     * @throws QUI\Exception
     */
    protected static function generatePdfFromSite(QUI\Projects\Site $Site)
    {
        $Document = new QUI\HtmlToPdf\Document();

        //$Document->setHeaderHTML('<div class="header-test"><p>I am a header</p></div>');
        $Document->setContentHTML($Site->getAttribute('content'));
        //$Document->setFooterHTML('<div class="footer-test">I am a footer</div>');

        // create PDF file
        return $Document->createPDF();
    }
}
