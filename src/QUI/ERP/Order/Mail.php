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
     * @throws QUI\Exception|\PHPMailer\PHPMailer\Exception
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
        }

        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        if (Settings::getInstance()->get('order', 'sendOrderConfirmationToAdmin')) {
            self::addBCCMailAddress($Mailer);
        }

        $Mailer->setSubject(
            QUI::getLocale()->get(
                'quiqqer/order',
                'order.confirmation.subject',
                self::getOrderLocaleVar($Order, $Customer)
            )
        );

        self::addOrderMailAttachments($Mailer, $Order);

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

            'message' => QUI::getLocale()->get(
                'quiqqer/order',
                'order.confirmation.body',
                self::getOrderLocaleVar($Order, $Customer)
            )
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
        $Package = QUI::getPackage('quiqqer/order');
        $Config  = $Package->getConfig();
        $email   = $Config->getValue('order', 'orderAdminMails');

        if (empty($email)) {
            $email = QUI::conf('mail', 'admin_mail');
        }

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
            QUI::getLocale()->get(
                'quiqqer/order',
                'order.confirmation.admin.subject',
                self::getOrderLocaleVar($Order, $Customer)
            )
        );

        self::addOrderMailAttachments($Mailer, $Order);


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
     * Add the order mail attachments to the
     * like privacy policy, terms and condition and cancellation policy
     *
     * @param QUI\Mail\Mailer $Mail
     * @param OrderInterface $Order
     */
    protected static function addOrderMailAttachments(
        QUI\Mail\Mailer $Mail,
        OrderInterface $Order
    ) {
        // check if html2pdf is installed
        if (QUI::getPackageManager()->isInstalled('quiqqer/htmltopdf') === false) {
            return;
        }

        $Package  = QUI::getPackage('quiqqer/order');
        $Config   = $Package->getConfig();
        $language = QUI::getLocale()->getCurrent();

        $privacyPolicy      = (int)$Config->getValue('mails', 'privacyPolicy');
        $termsAndConditions = (int)$Config->getValue('mails', 'termsAndConditions');
        $cancellationPolicy = (int)$Config->getValue('mails', 'cancellationPolicy');
        $attachments        = $Config->getValue('mails', 'attachments');
        $Customer           = $Order->getCustomer();

        if ($privacyPolicy) {
            try {
                $Site = QUI\ERP\Utils\Sites::getPrivacyPolicy();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if ($termsAndConditions) {
            try {
                $Site = QUI\ERP\Utils\Sites::getTermsAndConditions();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if ($cancellationPolicy && !$Customer->isCompany()) {
            try {
                $Site = QUI\ERP\Utils\Sites::getRevocation();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if (!empty($attachments)) {
            $attachments = explode(',', $attachments);
            $Media       = QUI::getProjectManager()->getStandard()->getMedia();

            foreach ($attachments as $attachment) {
                try {
                    $Item = $Media->get($attachment);
                    $Mail->addAttachment($Item->getFullPath());
                } catch (\Exception $Exception) {
                    QUI\System\Log::addAlert('Order mail attachment file error :: '.$Exception->getMessage());
                }
            }
        }
    }

    /**
     * @param QUI\Projects\Site $Site
     * @return string
     *
     * @throws QUI\Exception
     */
    protected static function generatePdfFromSite(QUI\Projects\Site $Site)
    {
        $Document = new QUI\HtmlToPdf\Document();

        //$Document->setHeaderHTML('');
        $Document->setContentHTML($Site->getAttribute('content'));
        //$Document->setFooterHTML('');

        // create PDF file
        $file = $Document->createPDF();

        // rename for attachment
        $title = $Site->getAttribute('title');

        ['dirname' => $dirname, 'extension' => $extension] = \pathinfo($file);
        $newFile = $dirname.'/'.$title.'.'.$extension;

        \rename($file, $newFile);

        return $newFile;
    }

    /**
     * Add the order bcc addresses
     *
     * @param QUI\Mail\Mailer $Mailer
     * @throws QUI\Exception
     */
    protected static function addBCCMailAddress(QUI\Mail\Mailer $Mailer)
    {
        $Package    = QUI::getPackage('quiqqer/order');
        $Config     = $Package->getConfig();
        $orderMails = $Config->getValue('order', 'orderAdminMails');

        if (empty($orderMails)) {
            $orderMails = QUI::conf('mail', 'admin_mail');
        }

        if (!empty($orderMails)) {
            $Mailer->addBCC($orderMails);
        }
    }

    //region mail helper

    /**
     * @param OrderInterface|Order|OrderInProcess $Order
     * @param $Customer
     * @return array
     */
    protected static function getOrderLocaleVar(OrderInterface $Order, $Customer): array
    {
        $Address = $Customer->getAddress();

        // customer name
        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $user = $Address->getName();
        }

        // email
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }


        return [
            'orderId'       => $Order->getId(),
            'hash'          => $Order->getAttribute('hash'),
            'date'          => self::dateFormat($Order->getAttribute('date')),
            'systemCompany' => self::getCompanyName(),
            'user'          => $user,
            'name'          => $user,
            'company'       => $Customer->getStandardAddress()->getAttribute('company'),
            'companyOrName' => self::getCompanyOrName($Customer),
            'address'       => $Address->render(),
            'email'         => $email,
            'salutation'    => $Address->getAttribute('salutation'),
            'firstname'     => $Address->getAttribute('firstname'),
            'lastname'      => $Address->getAttribute('lastname')
        ];
    }

    /**
     * @param $date
     * @return false|string
     */
    public static function dateFormat($date)
    {
        // date
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        if (!$date) {
            $date = \time();
        } else {
            $date = \strtotime($date);
        }

        return $Formatter->format($date);
    }

    /**
     * @param QUI\ERP\User $Customer
     * @return string
     */
    protected static function getCompanyOrName(QUI\ERP\User $Customer): string
    {
        $Address = $Customer->getStandardAddress();

        if (!empty($Address->getAttribute('company'))) {
            return $Address->getAttribute('company');
        }

        return $Customer->getName();
    }

    /**
     * Return the company name of the quiqqer system
     *
     * @return string
     */
    protected static function getCompanyName(): string
    {
        try {
            $Conf    = QUI::getPackage('quiqqer/erp')->getConfig();
            $company = $Conf->get('company', 'name');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        if (empty($company)) {
            return '';
        }

        return $company;
    }

    //endregion
}
