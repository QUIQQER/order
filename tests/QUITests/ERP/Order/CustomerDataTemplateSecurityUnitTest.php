<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Users\Address;
use QUI\Users\User;

class CustomerDataTemplateSecurityUnitTest extends TestCase
{
    public function testDynamicCustomerDataIsEscapedInTemplateOutput(): void
    {
        $attack = '"><script>alert(1)</script>';
        $escapedAttack = '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;';

        $User = $this->createMock(User::class);
        $User->method('getId')->willReturn(123);
        $User->method('getAttribute')->willReturnCallback(
            static fn(string $name): string => $name === 'email' ? $attack : ''
        );

        $Address = $this->createMock(Address::class);
        $Address->method('getId')->willReturn(456);
        $Address->method('getAttribute')->willReturn('');
        $Address->method('getPhone')->willReturn($attack);
        $Address->method('getMobile')->willReturn($attack);
        $Address->method('getFax')->willReturn($attack);
        $Address->method('render')->willReturn('');

        $Engine = QUI::getTemplateManager()->getEngine();
        $Engine->assign([
            'User' => $User,
            'Address' => $Address,
            'Order' => null,
            'b2bSelected' => '',
            'commentMessage' => $attack,
            'commentCustomer' => $attack,
            'settings' => $this->getAddressFieldSettings(),
            'businessTypeIsChangeable' => false,
            'isB2C' => true,
            'isB2B' => false,
            'isOnlyB2B' => false,
            'isOnlyB2C' => true
        ]);

        $html = $Engine->fetch(
            dirname(__DIR__, 4) . '/src/QUI/ERP/Order/Controls/OrderProcess/CustomerData.html'
        );

        self::assertStringNotContainsString($attack, $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString($escapedAttack, $html);
        self::assertStringContainsString('name="tel"', $html);
        self::assertStringContainsString('name="mobile"', $html);
        self::assertStringContainsString('name="fax"', $html);
        self::assertStringContainsString(
            '<textarea name="comment-message">' . $escapedAttack . '</textarea>',
            $html
        );
    }

    /**
     * @return array<string, array{show: bool, required: bool}>
     */
    private function getAddressFieldSettings(): array
    {
        $hidden = ['show' => false, 'required' => false];

        return [
            'company' => $hidden,
            'salutation' => $hidden,
            'firstname' => $hidden,
            'lastname' => $hidden,
            'street_no' => $hidden,
            'zip' => $hidden,
            'city' => $hidden,
            'country' => $hidden,
            'phone' => ['show' => true, 'required' => false],
            'mobile' => ['show' => true, 'required' => false],
            'fax' => ['show' => true, 'required' => false]
        ];
    }
}
