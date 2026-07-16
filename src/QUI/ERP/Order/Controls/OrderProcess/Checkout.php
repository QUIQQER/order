<?php

/**
 * This file contains QUI\ERP\Order\Controls\OrderProcess\Checkout
 */

namespace QUI\ERP\Order\Controls\OrderProcess;

use Exception;
use QUI;
use QUI\ERP\Order\Basket\Basket;
use QUI\ERP\Order\Basket\BasketOrder;
use QUI\ERP\Order\Basket\BasketGuest;
use QUI\ERP\Order\Settings;

use function dirname;
use function json_decode;
use function trim;

/**
 * Class Address
 * - Tab / Panel for the address
 *
 * @package QUI\ERP\Order\Controls
 */
class Checkout extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * @var Basket|BasketOrder|BasketGuest
     */
    protected Basket | BasketOrder | BasketGuest $Basket;

    /**
     * Basket constructor.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Checkout.css');
    }

    /**
     * Overwrite setAttribute then checkout can react to onGetBody
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $name, mixed $value): void
    {
        if ($name === 'Process') {
            /* @var $Process QUI\ERP\Order\OrderProcess */
            $Process = $value;

            // reset the session termsAndConditions if the step is not the checkout step
            $Process->Events->addEvent('onGetBody', function () use ($Process) {
                $self = $this;

                if ($Process->getAttribute('step') === $self->getName()) {
                    return;
                }

                $Order = $this->getOrder();

                if ($Order === null) {
                    return;
                }

                try {
                    QUI::getSession()->set(
                        'termsAndConditions-' . $Order->getUUID(),
                        0
                    );
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            });
        }

        parent::setAttribute($name, $value);
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        QUI::getEvents()->fireEvent(
            'quiqqerOrderOrderProcessCheckoutOutputBefore',
            [$this]
        );

        $Engine = QUI::getTemplateManager()->getEngine();
        $Order = $this->getOrder();

        if ($Order === null) {
            return '';
        }

        $Order->recalculate();

        $ArticleList = $Order->getArticles();
        $Articles = $ArticleList->toUniqueList();
        $Articles->hideHeader();

        $this->generateCheckboxLinks($Engine);

        // comment
        $comment = '';

        /*if (QUI::getSession()->get('comment-customer')) {
            $comment .= QUI::getSession()->get('comment-customer')."\n";
        }*/

        if (QUI::getSession()->get('comment-message')) {
            $comment .= QUI::getSession()->get('comment-message');
        }

        $comment = trim($comment);


        // invoice address
        $InvoiceAddress = $Order->getInvoiceAddress();

        if (
            $InvoiceAddress->getName() === ''
            && $InvoiceAddress->getPhone() === ''
            && $InvoiceAddress->getAttribute('street_no') === false
            && $InvoiceAddress->getAttribute('zip') === false
            && $InvoiceAddress->getAttribute('city') === false
            && $InvoiceAddress->getAttribute('country') === false
        ) {
            $InvoiceAddress = null;
        }

        $Engine->assign([
            'User' => $Order->getCustomer(),
            'InvoiceAddress' => $InvoiceAddress,
            'DeliveryAddress' => $Order->getDeliveryAddress(),
            'Payment' => $Order->getPayment(),
            'Shipping' => $Order->getShipping(),
            'comment' => $comment,
            'Articles' => $Articles,
            'Order' => $Order
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Checkout.html');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName(null | QUI\Locale $Locale = null): string
    {
        return 'Checkout';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-shopping-cart';
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(): void
    {
        $Order = $this->getOrder();

        if ($Order === null) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.not.found'
            ]);
        }

        $Payment = $Order->getPayment();

        if ($Order->isSuccessful()) {
            return;
        }

        if (!$Payment) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.payment.missing'
            ]);
        }

        if ($Order instanceof QUI\ERP\Order\Order) {
            return;
        }

        if (!QUI::getSession()->get('termsAndConditions-' . $Order->getUUID())) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.order.termsAndConditions.missing'
            ]);
        }
    }

    /**
     * Order was ordered with costs
     *
     * @return void
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save(): void
    {
        if (!empty($_REQUEST['termsAndConditions'])) {
            $Order = $this->getOrder();

            if ($Order === null) {
                return;
            }

            QUI::getSession()->set(
                'termsAndConditions-' . $Order->getUUID(),
                (int)$_REQUEST['termsAndConditions']
            );
        }

        if (!isset($_REQUEST['current']) || $_REQUEST['current'] !== $this->getName()) {
            return;
        }

        if (!isset($_REQUEST['payableToOrder'])) {
            return;
        }

        $this->forceSave();
    }

    /**
     * Save order as start order payment
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function forceSave(): void
    {
        $Order = $this->getOrder();

        if ($Order === null) {
            return;
        }

        $Payment = $Order->getPayment();

        if (!$Payment) {
            return;
        }

        $Order->setData('orderedWithCosts', 1);
        $Order->setData('orderedWithCostsPayment', $Payment->getId());

        if (method_exists($Order, 'save')) {
            $Order->save();
        }
    }

    /**
     * Return a link from a specific site type
     *
     * @param string $config
     * @return string
     */
    public function getLinkOf(string $config): string
    {
        $Config = QUI::getPackage('quiqqer/erp')->getConfig();

        if ($Config === null) {
            throw new QUI\Exception('The quiqqer/erp package configuration is not available.');
        }

        try {
            $values = $Config->get('sites', $config);
            $Project = $this->getProject();
        } catch (QUI\Exception) {
            return '';
        }

        if (!$values) {
            return '';
        }

        $lang = $Project->getLang();
        $values = json_decode($values, true);

        if (empty($values[$lang])) {
            return '';
        }

        try {
            $Site = QUI\Projects\Site\Utils::getSiteByLink($values[$lang]);

            $url = $Site->getUrlRewritten();
            $title = $Site->getAttribute('title');

            $project = $Site->getProject()->getName();
            $lang = $Site->getProject()->getLang();
            $id = $Site->getId();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        return '<a href="' . $url . '" target="_blank" 
            data-project="' . $project . '" 
            data-lang="' . $lang . '" 
            data-id="' . $id . '">' . $title . '</a>';
    }

    /**
     * @param QUI\Interfaces\Template\EngineInterface $Engine
     */
    public function generateCheckboxLinks($Engine): void
    {
        $termsAndConditionsLink = $this->getLinkOf('terms_and_conditions');
        $revocationLink = $this->getLinkOf('revocation');
        $privacyPolicyLink = $this->getLinkOf('privacy_policy');

        $checkboxes = [];
        $checkboxEntries = [];
        $localeLinks = [];

        if (!empty($termsAndConditionsLink)) {
            $localeLinks['terms_and_conditions'] = $termsAndConditionsLink;
        }

        if (!empty($privacyPolicyLink)) {
            $localeLinks['privacy_policy'] = $privacyPolicyLink;
        }

        if (!empty($revocationLink)) {
            $localeLinks['revocation'] = $revocationLink;
        }

        if (!empty($termsAndConditionsLink)) {
            $checkboxEntries[] = QUI::getLocale()->get(
                'quiqqer/order',
                'ordering.step.checkout.termsAndConditionsEntry',
                $localeLinks
            );

            $checkboxes[] = [
                'name' => 'termsAndConditions',
                'text' => QUI::getLocale()->get(
                    'quiqqer/order',
                    'ordering.step.checkout.termsAndConditionsAcceptText',
                    $localeLinks
                )
            ];
        }

        if (!empty($privacyPolicyLink)) {
            $checkboxEntries[] = QUI::getLocale()->get(
                'quiqqer/order',
                'ordering.step.checkout.privacyPolicyEntry',
                $localeLinks
            );

            $checkboxes[] = [
                'name' => 'privacyPolicy',
                'text' => QUI::getLocale()->get(
                    'quiqqer/order',
                    'ordering.step.checkout.privacyPolicyAcceptText',
                    ['privacy_policy' => $privacyPolicyLink]
                )
            ];
        }

        if (!empty($revocationLink)) {
            $checkboxEntries[] = QUI::getLocale()->get(
                'quiqqer/order',
                'ordering.step.checkout.revocationEntry',
                $localeLinks
            );

            $checkboxes[] = [
                'name' => 'revocation',
                'text' => QUI::getLocale()->get(
                    'quiqqer/order',
                    'ordering.step.checkout.revocationAcceptText',
                    ['revocation' => $revocationLink]
                )
            ];
        }

        $hasMandatoryLinks = !empty($checkboxEntries);
        $acceptText = '';

        if ($hasMandatoryLinks) {
            $and = ' ' . QUI::getLocale()->get('quiqqer/order', 'ordering.step.checkout.and') . ' ';

            if (count($checkboxEntries) === 1) {
                $links = $checkboxEntries[0];
            } elseif (count($checkboxEntries) === 2) {
                $links = $checkboxEntries[0] . $and . $checkboxEntries[1];
            } else {
                $last = array_pop($checkboxEntries);
                $links = implode(', ', $checkboxEntries) . $and . $last;
            }

            $acceptText = QUI::getLocale()->get(
                'quiqqer/order',
                'ordering.step.checkout.checkoutAcceptText',
                [
                    'links' => $links
                ]
            );

            QUI::getEvents()->fireEvent(
                'quiqqerOrderOrderProcessCheckoutOutput',
                [$this, &$acceptText]
            );
        }

        $mandatoryLinksDisplay = 'single_checkbox';
        $Config = Settings::getConfig();

        try {
            $mandatoryLinksDisplay = $Config->get('orderProcess', 'mandatoryLinksDisplay');
        } catch (Exception) {
        }

        $Engine->assign([
            'checkboxes' => $checkboxes,
            'acceptText' => $acceptText,
            'hasMandatoryLinks' => $hasMandatoryLinks,
            'mandatoryLinksDisplay' => $mandatoryLinksDisplay
        ]);
    }
}
