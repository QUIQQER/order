<?php

/**
 * This file contains QUI\ERP\Order\Mail
 */

namespace QUI\ERP\Order;

use IntlDateFormatter;
use QUI;
use QUI\Exception;

use function class_exists;
use function dirname;
use function is_array;
use function is_string;
use function json_decode;
use function pathinfo;
use function rename;
use function strtotime;
use function time;
use function trim;

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
     * @throws Exception|\PHPMailer\PHPMailer\Exception
     */
    public static function sendOrderConfirmationMail(Order $Order): void
    {
        $Customer = $Order->getCustomer();
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            QUI\System\Log::writeRecursive(
                QUI::getLocale()->get('quiqqer/order', 'message.order.missing.customer.mail', [
                    'orderId' => $Order->getUUID(),
                    'customerId' => $Customer->getUUID()
                ])
            );

            return;
        }

        // create order data
        $OrderControl = new QUI\ERP\Order\Controls\Order\Order([
            'orderHash' => $Order->getUUID(),
            'template' => 'OrderLikeBasket'
        ]);

        $Address = null;
        $Customer = $Order->getCustomer();

        $user = $Customer->getName();
        $user = trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
        }

        // mail
        $mailer = QUI::getMailManager();
        $reflection = new \ReflectionMethod($mailer, 'getMailer');
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount > 0) {
            // Methode akzeptiert Parameter (neue Version)
            // @phpstan-ignore-next-line
            $Mailer = $mailer->getMailer([
                'Project' => QUI::getRewrite()->getProject()
            ]);
        } else {
            // Methode akzeptiert keine Parameter (alte Version)
            $Mailer = $mailer->getMailer();
        }


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
        $Order = $OrderControl->getOrder();

        $Articles = $Order->getArticles()->toUniqueList();
        $Articles->hideHeader();

        $Shipping = null;
        $DeliveryAddress = null;

        if (class_exists('QUI\ERP\Shipping\Shipping') && $Order->getShipping()) {
            $Shipping = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();

            $DeliveryAddress?->setAttribute(
                'template',
                dirname(__FILE__) . '/MailTemplates/orderConfirmationAddress.html'
            );
        }

        $InvoiceAddress = $Order->getInvoiceAddress();
        $InvoiceAddress->setAttribute('template', dirname(__FILE__) . '/MailTemplates/orderConfirmationAddress.html');

        // comment
        $comment = '';

        if (QUI::getSession()->get('comment-customer')) {
            $comment .= QUI::getSession()->get('comment-customer') . "\n";
        }

        if (QUI::getSession()->get('comment-message')) {
            $comment .= QUI::getSession()->get('comment-message');
        }

        $comment = trim($comment);


        $Engine->assign([
            'Shipping' => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress' => $InvoiceAddress,
            'Payment' => $Order->getPayment(),
            'comment' => $comment,

            'Order' => $Order,
            'Articles' => $Articles,
            'Address' => $Address,

            'message' => QUI::getLocale()->get(
                'quiqqer/order',
                'order.confirmation.body',
                self::getOrderLocaleVar($Order, $Customer)
            )
        ]);

        $Mailer->setBody(
            $Engine->fetch(dirname(__FILE__) . '/MailTemplates/orderConfirmation.html')
        );

        $Mailer->send();
    }

    /**
     * confirmation mail for the admin
     *
     * @param Order $Order
     * @throws Exception|\PHPMailer\PHPMailer\Exception
     */
    public static function sendAdminOrderConfirmationMail(Order $Order): void
    {
        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();
        $email = $Config->getValue('order', 'orderAdminMails');

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
            'orderHash' => $Order->getUUID(),
            'template' => 'OrderLikeBasket'
        ]);

        $Address = null;
        $Customer = $Order->getCustomer();
        $comments = $Order->getComments()->toArray();

        $user = $Customer->getName();
        $user = trim($user);

        if (empty($user)) {
            $Address = $Customer->getAddress();
            $user = $Address->getName();
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
            $Order = $OrderControl->getOrder();

            $Articles = $Order->getArticles()->toUniqueList();
            $Articles->hideHeader();
        } catch (Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedNumber(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }


        $Shipping = null;
        $DeliveryAddress = null;

        if (class_exists('QUI\ERP\Shipping\Shipping') && $Order->getShipping()) {
            $Shipping = QUI\ERP\Shipping\Shipping::getInstance()->getShippingByObject($Order);
            $DeliveryAddress = $Shipping->getAddress();
            $DeliveryAddress->setAttribute(
                'template',
                dirname(__FILE__) . '/MailTemplates/orderConfirmationAddress.html'
            );
        }

        $InvoiceAddress = $Order->getInvoiceAddress();
        $InvoiceAddress->setAttribute(
            'template',
            dirname(__FILE__) . '/MailTemplates/orderConfirmationAddress.html'
        );

        $Engine->assign([
            'Shipping' => $Shipping,
            'DeliveryAddress' => $DeliveryAddress,
            'InvoiceAddress' => $InvoiceAddress,
            'Payment' => $Order->getPayment(),
            'comments' => $comments,

            'Order' => $Order,
            'Articles' => $Articles,
            'Address' => $Address,

            'message' => QUI::getLocale()->get('quiqqer/order', 'order.confirmation.admin.body', [
                'orderId' => $Order->getPrefixedNumber(),
                'userId' => $Customer->getUUID(),
                'username' => $user
            ])
        ]);

        $Mailer->setBody(
            $Engine->fetch(dirname(__FILE__) . '/MailTemplates/orderConfirmationAdmin.html')
        );

        try {
            $Mailer->send();
        } catch (Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/order', 'exception.order.confirmation.admin', [
                    'orderId' => $Order->getPrefixedNumber(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }
    }

    /**
     * Sends a shipping confirmation to the customer
     *
     * @param AbstractOrder $Order
     * @return void
     *
     * @throws Exception
     */
    public static function sendOrderShippingConfirmation(AbstractOrder $Order): void
    {
        $Customer = $Order->getCustomer();
        $Address = $Customer->getAddress();
        $email = $Customer->getAttribute('email');
        $Country = $Customer->getCountry();

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }

        if (empty($email)) {
            try {
                $User = QUI::getUsers()->get($Customer->getUUID());

                if ($User->getAttribute('email')) {
                    $email = $User->getAttribute('email');
                }
            } catch (Exception) {
            }
        }

        if (empty($email)) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/order', 'exception.shipping.order.no.customer.mail')
            );
        }

        $shippingConfirmation = $Order->getDataEntry('shippingConfirmation');
        $shippingTracking = $Order->getDataEntry('shippingTracking');

        if (is_string($shippingTracking)) {
            $shippingTracking = json_decode($shippingTracking, true);
        }

        if (!is_array($shippingConfirmation)) {
            $shippingConfirmation = [];
        }

        $shippingConfirmation[] = [
            'time' => time(),
            'email' => $email
        ];

        $localeVar = self::getOrderLocaleVar($Order, $Customer);

        $localeVar['trackingInfo'] = '';
        $localeVar['trackingNumber'] = '';
        $localeVar['trackingLink'] = '';

        if (
            !empty($shippingTracking)
            && isset($shippingTracking['type'])
            && isset($shippingTracking['number'])
            && class_exists('QUI\ERP\Shipping\Tracking\Tracking')
        ) {
            $localeVar['trackingInfo'] = QUI::getLocale()->get(
                'quiqqer/order',
                'shipping.order.mail.body.shippingInformation',
                [
                    'trackingLink' => QUI\ERP\Shipping\Tracking\Tracking::getUrl(
                        $shippingTracking['number'],
                        $shippingTracking['type'],
                        $Country
                    ),
                    'trackingNumber' => $shippingTracking['number']
                ]
            );

            $localeVar['trackingNumber'] = $shippingTracking['number'];
            $localeVar['trackingLink'] = QUI\ERP\Shipping\Tracking\Tracking::getUrl(
                $shippingTracking['number'],
                $shippingTracking['type'],
                $Country
            );
        }


        // mail
        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($email);

        $Mailer->setSubject(
            QUI::getLocale()->get(
                'quiqqer/order',
                'shipping.order.mail.subject',
                $localeVar
            )
        );

        $Mailer->setBody(
            QUI::getLocale()->get(
                'quiqqer/order',
                'shipping.order.mail.body',
                $localeVar
            )
        );

        try {
            $Mailer->send();
        } catch (\Exception $Exception) {
            QUI\System\Log::addAlert(
                QUI::getLocale()->get('quiqqer/shipping', 'exception.shipping.order.mail', [
                    'orderId' => $Order->getPrefixedNumber(),
                    'message' => $Exception->getMessage()
                ])
            );

            return;
        }

        $Order->setData('shippingConfirmation', $shippingConfirmation);
        $Order->update(QUI::getUsers()->getSystemUser());
    }

    /**
     * Add the order mail attachments to the
     * like privacy policy, terms and condition and cancellation policy
     *
     * @param QUI\Mail\Mailer $Mail
     * @param OrderInterface $Order
     * @throws Exception
     */
    protected static function addOrderMailAttachments(
        QUI\Mail\Mailer $Mail,
        OrderInterface $Order
    ): void {
        // check if html2pdf is installed
        if (QUI::getPackageManager()->isInstalled('quiqqer/htmltopdf') === false) {
            return;
        }

        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();

        $privacyPolicy = (int)$Config->getValue('mails', 'privacyPolicy');
        $termsAndConditions = (int)$Config->getValue('mails', 'termsAndConditions');
        $cancellationPolicy = (int)$Config->getValue('mails', 'cancellationPolicy');
        $attachments = $Config->getValue('mails', 'attachments');
        $Customer = $Order->getCustomer();

        if ($privacyPolicy) {
            try {
                $Site = QUI\ERP\Utils\Sites::getPrivacyPolicy();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (Exception) {
            }
        }

        if ($termsAndConditions) {
            try {
                $Site = QUI\ERP\Utils\Sites::getTermsAndConditions();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (Exception) {
            }
        }

        if ($cancellationPolicy && !$Customer->isCompany()) {
            try {
                $Site = QUI\ERP\Utils\Sites::getRevocation();

                if ($Site) {
                    $file = self::generatePdfFromSite($Site);
                    $Mail->addAttachment($file);
                }
            } catch (Exception) {
            }
        }

        if (!empty($attachments)) {
            $attachments = explode(',', $attachments);
            $Media = QUI::getProjectManager()->getStandard()->getMedia();

            foreach ($attachments as $attachment) {
                try {
                    $Item = $Media->get((int)$attachment);
                    $Mail->addAttachment($Item->getFullPath());
                } catch (\Exception $Exception) {
                    QUI\System\Log::addAlert('Order mail attachment file error :: ' . $Exception->getMessage());
                }
            }
        }
    }

    /**
     * @param QUI\Projects\Site $Site
     * @return string
     *
     * @throws Exception
     */
    protected static function generatePdfFromSite(QUI\Projects\Site $Site): string
    {
        $Document = new QUI\HtmlToPdf\Document();

        //$Document->setHeaderHTML('');
        $Document->setContentHTML($Site->getAttribute('content'));
        //$Document->setFooterHTML('');

        // create PDF file
        $file = $Document->createPDF();

        // rename for attachment
        $title = $Site->getAttribute('title');

        ['dirname' => $dirname, 'extension' => $extension] = pathinfo($file);
        $newFile = $dirname . '/' . $title . '.' . $extension;

        rename($file, $newFile);

        return $newFile;
    }

    /**
     * Add the order bcc addresses
     *
     * @param QUI\Mail\Mailer $Mailer
     * @throws Exception
     */
    protected static function addBCCMailAddress(QUI\Mail\Mailer $Mailer): void
    {
        $Package = QUI::getPackage('quiqqer/order');
        $Config = $Package->getConfig();
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
     * @param QUI\ERP\ErpEntityInterface $Order
     * @param QUI\Interfaces\Users\User $Customer
     * @return array
     */
    protected static function getOrderLocaleVar(
        QUI\ERP\ErpEntityInterface $Order,
        QUI\Interfaces\Users\User $Customer
    ): array {
        if ($Customer instanceof QUI\ERP\User) {
            $Address = $Customer->getAddress();
        } else {
            $Address = $Customer->getStandardAddress();
        }

        // customer name
        $user = $Customer->getName();
        $user = trim($user);

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
            'orderId' => $Order->getUUID(),
            'orderPrefixedId' => $Order->getPrefixedNumber(),
            'hash' => $Order->getAttribute('hash'),
            'date' => self::dateFormat($Order->getAttribute('date')),
            'systemCompany' => self::getCompanyName(),
            'user' => $user,
            'name' => $user,
            'company' => $Customer->getStandardAddress()->getAttribute('company'),
            'companyOrName' => self::getCompanyOrName($Customer),
            'address' => $Address->render(),
            'email' => $email,
            'salutation' => $Address->getAttribute('salutation'),
            'firstname' => $Address->getAttribute('firstname'),
            'lastname' => $Address->getAttribute('lastname')
        ];
    }

    /**
     * @param $date
     * @return false|string
     */
    public static function dateFormat($date): bool | string
    {
        // date
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        if (!$date) {
            $date = time();
        } else {
            $date = strtotime($date);
        }

        return $Formatter->format($date);
    }

    /**
     * @param QUI\Interfaces\Users\User $Customer
     * @return string
     */
    protected static function getCompanyOrName(QUI\Interfaces\Users\User $Customer): string
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
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
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
