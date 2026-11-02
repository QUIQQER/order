<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Address;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Mail;
use QUI\ERP\User;

class ShippingConfirmationMailTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function sendResults(): iterable
    {
        yield 'mail accepted' => ['success'];
        yield 'mail transport returns false' => ['false'];
        yield 'mail transport throws' => ['exception'];
    }

    #[DataProvider('sendResults')]
    public function testOnlyAcceptedMailIsRecordedAndTransportFailuresDoNotEscape(string $result): void
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->willReturn('Test Company');
        $Address->method('render')->willReturn('Test address');
        $Customer = $this->createMock(User::class);
        $Customer->method('getName')->willReturn('Test Customer');
        $Customer->method('getLang')->willReturn('en');
        $Customer->method('getLocale')->willReturn(QUI::getLocale());
        $Customer->method('getAttribute')->with('email')->willReturn('shipping@example.test');
        $Customer->method('getAddress')->willReturn($Address);
        $Customer->method('getStandardAddress')->willReturn($Address);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getUUID')->willReturn('shipping-mail-test');
        $Order->method('getPrefixedNumber')->willReturn('BE-SHIPPING-TEST');
        $Order->method('getAttribute')->willReturnCallback(
            static fn (string $key): mixed => match ($key) {
                'date' => '2026-11-02',
                'hash' => 'shipping-mail-test',
                default => null
            }
        );
        $history = [['time' => 1, 'email' => 'previous@example.test']];
        $Order->method('getDataEntry')->willReturnCallback(
            static fn (string $key): mixed => $key === 'shippingConfirmation' ? $history : null
        );

        if ($result === 'success') {
            $Order->expects(self::once())->method('setData')->with(
                'shippingConfirmation',
                self::callback(static fn (array $entries): bool => count($entries) === 2
                    && $entries[0] === $history[0]
                    && $entries[1]['email'] === 'shipping@example.test')
            );
            $Order->expects(self::once())->method('update')->with(QUI::getUsers()->getSystemUser());
        } else {
            $Order->expects(self::never())->method('setData');
            $Order->expects(self::never())->method('update');
        }

        $Mailer = $this->createMock(QUI\Mail\Mailer::class);
        $Mailer->expects(self::once())->method('addRecipient')->with('shipping@example.test');
        if ($result === 'exception') {
            $Mailer->expects(self::once())->method('send')->willThrowException(new QUI\Exception('SMTP unavailable'));
        } else {
            $Mailer->expects(self::once())->method('send')->willReturn($result === 'success');
        }
        $MailManager = $this->createMock(QUI\Mail\Manager::class);
        $MailManager->method('getMailer')->willReturn($Mailer);
        $PreviousManager = QUI::$MailManager;
        QUI::$MailManager = $MailManager;

        try {
            Mail::sendOrderShippingConfirmation($Order);
        } finally {
            QUI::$MailManager = $PreviousManager;
        }
    }
}
