<?php

namespace QUI\ERP\Order\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Exception;
use IntlDateFormatter;
use QUI;
use QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment as AdvancePayment;
use QUI\ERP\Accounting\Payments\Methods\Invoice\Payment as InvoicePayment;
use QUI\ERP\BankAccounts\Handler as BankAccounts;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Payments\SEPA\Provider as SepaProvider;
use QUI\Interfaces\Users\User;
use QUI\Locale;

use function array_merge;
use function get_class;
use function implode;
use function in_array;
use function number_format;
use function strtotime;
use function time;
use function trim;

use const PHP_EOL;

/**
 * Class OutputProvider
 *
 * Output provider for quiqqer/order:
 *
 * Outputs previews and PDF files for orders
 */
class OutputProviderOrder implements OutputProviderInterface
{
    /**
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'Order';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(Locale $Locale = null)
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/order', 'OutputProvider.entity.title.Order');
    }

    /**
     * Get the entity the output is created for
     *
     * @param string|int $entityId
     * @return \QUI\ERP\Order\Order|\QUI\ERP\Order\OrderInProcess
     *
     * @throws QUI\Exception
     */
    public static function getEntity($entityId)
    {
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->get($entityId);
        } catch (QUI\Exception $exception) {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($entityId);
        }

        return $Order;
    }

    /**
     * Get download filename (without file extension)
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDownloadFileName($entityId): string
    {
        return self::getEntity($entityId)->getPrefixedId();
    }

    /**
     * Get output Locale by entity
     *
     * @param string|int $entityId
     * @return Locale
     *
     * @throws QUI\Exception
     */
    public static function getLocale($entityId): Locale
    {
        $Order = self::getEntity($entityId);
        $Customer = $Order->getCustomer();

        return $Customer->getLocale();
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @return array
     */
    public static function getTemplateData($entityId): array
    {
        $Order = self::getEntity($entityId);
        $OrderView = $Order->getView();
        $Customer = $Order->getCustomer();

        $Address = $Order->getInvoiceAddress();
        $Address->clearMail();
        $Address->clearPhone();

        // list calculation
        $Articles = $Order->getArticles();

        if (get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        // Delivery address
        $DeliveryAddress = $Order->getDeliveryAddress();

        if ($DeliveryAddress->equals($Address)) {
            $DeliveryAddress = false;
        }

        QUI::getLocale()->setTemporaryCurrent($Customer->getLang());

        $OrderView->setAttributes($Order->getAttributes());

        // global order text
        $globalOrderText = '';

        if (QUI::getLocale()->get('quiqqer/order', 'global.order.text') !== '') {
            $globalOrderText = QUI::getLocale()->get('quiqqer/order', 'global.order.text');
        }

        // order number
        $orderNumber = '';

        if ($OrderView->getAttribute('order_id')) {
            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById(
                    $OrderView->getAttribute('order_id')
                );
                $orderNumber = $Order->getPrefixedId();
            } catch (QUI\Exception $Exception) {
            }
        }

        // EPC QR Code
        $epcQrCodeImageSrc = false;

        /* @todo
         * if (Settings::getInstance()->isIncludeQrCode()) {
         * $epcQrCodeImageSrc = self::getEpcQrCodeImageImgSrc($Order);
         * }
         */

        return [
            'this' => $OrderView,
            'ArticleList' => $Articles,
            'Customer' => $Customer,
            'Address' => $Address,
            'DeliveryAddress' => $DeliveryAddress,
            'Payment' => $Order->getPayment(),
            'transaction' => $OrderView->getTransactionText(),
            'projectName' => $Order->getAttribute('project_name'),
            'useShipping' => QUI::getPackageManager()->isInstalled('quiqqer/shipping'),
            'globalOrderText' => $globalOrderText,
            'orderNumber' => $orderNumber,
            'epcQrCodeImageSrc' => $epcQrCodeImageSrc
        ];
    }

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param string|int $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission($entityId, User $User): bool
    {
        if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
            return false;
        }

        try {
            $Customer = self::getEntity($entityId)->getCustomer();

            return $User->getId() === $Customer->getId();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress($entityId): bool|string
    {
        $Customer = self::getEntity($entityId)->getCustomer();

        if (!empty($Customer->getAttribute('contactEmail'))) {
            return $Customer->getAttribute('contactEmail');
        }

        return QUI\ERP\Customer\Utils::getInstance()->getEmailByCustomer($Customer);
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject($entityId): string
    {
        $Order = self::getEntity($entityId);
        $Customer = $Order->getCustomer();

        return $Order->getCustomer()->getLocale()->get(
            'quiqqer/order',
            'order.send.mail.subject',
            self::getOrderLocaleVar($Order, $Customer)
        );
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody($entityId): string
    {
        $Order = self::getEntity($entityId);
        $Customer = $Order->getCustomer();

        return $Customer->getLocale()->get(
            'quiqqer/order',
            'order.send.mail.message',
            self::getOrderLocaleVar($Order, $Customer)
        );
    }

    /**
     * @param $Order
     * @param QUI\ERP\User $Customer
     * @return array
     */
    protected static function getOrderLocaleVar($Order, $Customer): array
    {
        $CustomerAddress = $Customer->getAddress();
        $user = $CustomerAddress->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Customer->getAddress()->getName();
        }

        $user = trim($user);

        // contact person
        $contactPerson = $Order->getAttribute('contact_person');

        if (empty($contactPerson)) {
            // Fetch contact person from live user (if existing)
            $ContactPersonAddress = CustomerUtils::getInstance()->getContactPersonAddress($Customer);

            if ($ContactPersonAddress) {
                $contactPerson = $ContactPersonAddress->getName();
            }
        }

        if (empty($contactPerson)) {
            $contactPerson = $user;
        }

        $contactPersonOrName = $contactPerson;

        if (empty($contactPersonOrName)) {
            $contactPersonOrName = $user;
        }

        return array_merge([
            'orderId' => $Order->getId(),
            'hash' => $Order->getAttribute('hash'),
            'date' => self::dateFormat($Order->getAttribute('date')),
            'systemCompany' => self::getCompanyName(),

            'contactPerson' => $contactPerson,
            'contactPersonOrName' => $contactPersonOrName
        ], self::getCustomerVariables($Customer));
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
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        if (empty($company)) {
            return '';
        }

        return $company;
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
     * @param QUI\ERP\User $Customer
     * @return array
     */
    public static function getCustomerVariables(QUI\ERP\User $Customer): array
    {
        $Address = $Customer->getAddress();

        // customer name
        $user = $Address->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Address->getName();
        }

        $user = trim($user);

        // email
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }

        return [
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
    public static function dateFormat($date)
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
     * Get raw base64 img src for EPC QR code.
     *
     * @param \QUI\ERP\Order\Order $Order
     * @return string|false - Raw <img> "src" attribute with base64 image data or false if code can or must not be generated.
     */
    protected static function getEpcQrCodeImageImgSrc(QUI\ERP\Order\Order $Order)
    {
        try {
            // Check currency (must be EUR)
            if ($Order->getCurrency()->getCode() !== 'EUR') {
                return false;
            }

            // Check payment type (must be "order" or "pay in advance")
            $paymentTypeClassName = $Order->getPayment()->getPaymentType();

            $allowedPaymentTypeClasses = [
                AdvancePayment::class,
                InvoicePayment::class
            ];

            if (!in_array($paymentTypeClassName, $allowedPaymentTypeClasses)) {
                return false;
            }

            $varDir = QUI::getPackage('quiqqer/order')->getVarDir();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }


        // Prefer bank account set in SEPA module if available
        if (QUI::getPackageManager()->isInstalled('quiqqer/payment-sepa')) {
            $creditorBankAccount = SepaProvider::getCreditorBankAccount();
        } else {
            $creditorBankAccount = BankAccounts::getCompanyBankAccount();
        }

        $requiredFields = [
            'accountHolder',
            'iban',
            'bic',
        ];

        foreach ($requiredFields as $requiredField) {
            if (empty($creditorBankAccount[$requiredField])) {
                return false;
            }
        }

        try {
            $paidStatus = $Order->getPaidStatusInformation();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }


        $amount = $paidStatus['toPay'];

        if ($amount <= 0) {
            return false;
        }

        $purposeText = QUI::getLocale()->get(
            'quiqqer/order',
            'OutputProvider.epc_qr_code_purpose',
            [
                'orderNo' => $Order->getId()
            ]
        );

        // @todo Warnung, wenn $purposeText zu lang

        // See
        $qrCodeLines = [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            $creditorBankAccount['bic'],
            $creditorBankAccount['accountHolder'],
            $creditorBankAccount['iban'],
            'EUR' . number_format($amount, 2, '.', ''),
            '',
            '',
            $purposeText
        ];

        $qrCodeText = implode(PHP_EOL, $qrCodeLines);

        $QrOptions = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'pngCompression' => -1
        ]);

        $QrCode = new QRCode($QrOptions);
        return $QrCode->render($qrCodeText);
    }
}
