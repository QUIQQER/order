<?php

namespace QUITests\ERP\Order;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
use QUITests\ERP\Order\Fixtures\RenderableOrderStep;
use QUITests\ERP\Order\Fixtures\TestableOrderProcess;

class ButtonTextsUnitTest extends TestCase
{
    private const LOCALES = [
        'next' => 'ordering.btn.next',
        'previous' => 'ordering.btn.previous',
        'backToShop' => 'ordering.btn.backToShop',
        'changePayment' => 'ordering.btn.changePayment',
        'payToOrder' => 'ordering.btn.pay.to.order'
    ];

    public function testStepOverridesGeneralTextAndOtherStepsKeepTheirDefaults(): void
    {
        $Process = new TestableOrderProcess();
        $Process->setAttribute('buttonTexts', [
            'next' => 'General next',
            'steps' => [
                'Basket' => ['next' => 'Continue to customer data'],
                'CustomerData' => ['previous' => 'Back to basket']
            ]
        ]);
        $Process->setAttribute('step', 'Basket');

        self::assertSame('Continue to customer data', $Process->getButtonText('next'));
        self::assertSame('General next', $Process->getButtonText('next', 'CustomerData'));
        self::assertSame('Back to basket', $Process->getButtonText('previous', 'CustomerData'));
        self::assertSame(
            QUI::getLocale()->get('quiqqer/order', 'ordering.btn.previous'),
            $Process->getButtonText('previous', 'Basket')
        );
        self::assertSame(
            QUI::getLocale()->get('quiqqer/order', 'ordering.btn.pay.to.order'),
            $Process->getButtonText('payToOrder', 'Checkout')
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTexts(): iterable
    {
        yield 'not configured' => [false];
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'string' => ['Continue'];
        yield 'empty strings' => [['next' => '', 'steps' => ['Basket' => ['next' => " \t\n"]]]];
        yield 'invalid labels' => [['next' => 123, 'steps' => ['Basket' => ['next' => ['html']]]]];
        yield 'invalid steps' => [['steps' => 'Basket']];
        yield 'invalid step' => [['steps' => ['Basket' => 'Continue']]];
    }

    #[DataProvider('invalidTexts')]
    public function testMissingOrInvalidOptionsUseExistingLocales(mixed $texts): void
    {
        $Process = new TestableOrderProcess();
        $Process->setAttribute('buttonTexts', $texts);

        foreach (self::LOCALES as $name => $locale) {
            self::assertSame(
                QUI::getLocale()->get('quiqqer/order', $locale),
                $Process->getButtonText($name, 'Basket')
            );
        }
    }

    public function testEmptyStepTextFallsBackToGeneralText(): void
    {
        $Process = new TestableOrderProcess();
        $Process->setAttribute('buttonTexts', [
            'next' => 'Continue',
            'steps' => ['Basket' => ['next' => ' ']]
        ]);

        self::assertSame('Continue', $Process->getButtonText('next', 'Basket'));
    }

    public function testDefaultsFollowTheCurrentLanguage(): void
    {
        $Locale = QUI::getLocale();
        $originalLanguage = $Locale->getCurrent();
        $Process = new TestableOrderProcess();

        try {
            foreach (['de', 'en', 'es', 'fr', 'it', 'pl', 'pt'] as $language) {
                $Locale->setCurrent($language);

                foreach (self::LOCALES as $name => $locale) {
                    self::assertSame($Locale->get('quiqqer/order', $locale), $Process->getButtonText($name));
                }
            }
        } finally {
            $Locale->setCurrent($originalLanguage);
        }
    }

    public function testTemplateEscapesTextsAndExportsOptionsWithoutChangingButtonActions(): void
    {
        $attack = '"><img src=x onerror=alert(1)>&\'"';
        $texts = array_fill_keys(array_keys(self::LOCALES), $attack);
        $texts['steps'] = ['Basket' => ['next' => $attack . ' step']];
        $Process = new TestableOrderProcess();
        $Process->setAttribute('buttonTexts', $texts);
        $Basket = new RenderableOrderStep('Basket', AbstractOrderingStep::class);
        $Finish = new RenderableOrderStep('Finish', AbstractOrderingStep::class);
        $Process->setTestSteps(['Basket' => $Basket, 'Finish' => $Finish]);
        $Engine = QUI::getTemplateManager()->getEngine();

        foreach ([false, true] as $payable) {
            $Engine->assign([
                'this' => $Process,
                'CurrentStep' => $Basket,
                'steps' => $Process->getSteps(),
                'Order' => null,
                'hash' => '',
                'error' => false,
                'messages' => [],
                'frontendMessages' => [],
                'currentStepContent' => '',
                'previous' => 'CustomerData',
                'next' => 'Checkout',
                'payableToOrder' => $payable,
                'changePayment' => true,
                'backToShopUrl' => '/shop'
            ]);
            $html = $Engine->fetch(dirname(__DIR__, 4) . '/src/QUI/ERP/Order/Controls/OrderProcess.html');
            $Document = new DOMDocument();
            $Document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $XPath = new DOMXPath($Document);
            self::assertSame(0, $XPath->query('//img')->length);
            $ButtonContainer = $XPath->query('//*[@data-name="buttons"]')->item(0);
            self::assertInstanceOf(\DOMElement::class, $ButtonContainer);
            self::assertSame($texts, json_decode($ButtonContainer->getAttribute('data-button-texts'), true));

            foreach (['backToShop', 'previous', 'changePayment', $payable ? 'payToOrder' : 'next'] as $name) {
                $Node = $XPath->query('//*[@data-name="' . $name . '"]')->item(0);
                self::assertInstanceOf(\DOMElement::class, $Node);
                self::assertSame($attack . ($name === 'next' ? ' step' : ''), trim($Node->textContent));
            }

            $Submit = $XPath->query('//button[@name="step"]')->item(0);
            self::assertInstanceOf(\DOMElement::class, $Submit);
            self::assertSame($payable ? 'payableToOrder' : 'Checkout', $Submit->getAttribute('value'));
        }
    }
}
