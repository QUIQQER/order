<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Address as ERPAddress;
use QUI\ERP\Comments;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Exception;
use QUI\Users\Address;
use QUI\Users\User;
use ReflectionClass;

class CustomerDataUnitTest extends TestCase
{
    public function testMetadataAndMissingOrderBehavior(): void
    {
        $Step = new CustomerData();

        self::assertSame('Customer', $Step->getName());
        self::assertSame('fa-user-o', $Step->getIcon());
        self::assertSame('', $Step->getBody());

        $this->expectException(Exception::class);
        $Step->validate();
    }

    public function testCompleteAddressIsValidWithoutPostalCode(): void
    {
        $Address = $this->createAddress([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'street_no' => 'Example Street 1',
            'zip' => '',
            'city' => 'London',
            'country' => 'GB'
        ]);

        CustomerData::validateAddress($Address);

        self::assertTrue(true);
    }

    public function testEveryRequiredAddressFieldIsValidated(): void
    {
        $validAddress = [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'street_no' => 'Example Street 1',
            'zip' => '12345',
            'city' => 'London',
            'country' => 'GB'
        ];

        foreach (['firstname', 'lastname', 'street_no', 'city', 'country'] as $field) {
            $attributes = $validAddress;
            $attributes[$field] = '';

            try {
                CustomerData::validateAddress($this->createAddress($attributes));
                self::fail('An address without ' . $field . ' must be rejected.');
            } catch (Exception) {
                self::assertTrue(true);
            }
        }
    }

    public function testProtectedAddressChecksDistinguishEmptyInvalidAndValidAddresses(): void
    {
        $Step = new CustomerData();
        $emptyAddress = $this->createAddress([]);
        $invalidAddress = $this->createAddress(['firstname' => 'Ada']);
        $validAddress = $this->createAddress([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'street_no' => 'Example Street 1',
            'city' => 'London',
            'country' => 'GB'
        ]);

        self::assertTrue($this->invokeProtectedBool($Step, 'isAddressEmpty', $emptyAddress));
        self::assertFalse($this->invokeProtectedBool($Step, 'isAddressEmpty', $invalidAddress));
        self::assertFalse($this->invokeProtectedBool($Step, 'isAddressValid', $invalidAddress));
        self::assertTrue($this->invokeProtectedBool($Step, 'isAddressValid', $validAddress));
    }

    public function testValidateKeepsMatchingInvoiceAddress(): void
    {
        $Address = $this->createErpAddress('address-1');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getInvoiceAddress')->willReturn($Address);
        $Order->expects(self::never())->method('setInvoiceAddress');
        $Order->expects(self::never())->method('update');

        $Step = $this->createCustomerDataWithInvoiceAddress($Address);
        $Step->setAttribute('Order', $Order);
        $Step->validate();

        self::assertTrue(true);
    }

    public function testValidateSynchronizesChangedInvoiceAddress(): void
    {
        $CurrentAddress = $this->createErpAddress('address-old');
        $NewAddress = $this->createErpAddress('address-new');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getInvoiceAddress')->willReturn($CurrentAddress);
        $Order->expects(self::once())->method('setInvoiceAddress')->with($NewAddress);
        $Order->expects(self::once())->method('update');

        $Step = $this->createCustomerDataWithInvoiceAddress($NewAddress);
        $Step->setAttribute('Order', $Order);
        $Step->validate();

        self::assertTrue(true);
    }

    public function testSessionCommentsAreAddedOnceToOrder(): void
    {
        $Session = QUI::getSession();
        $originalCustomerComment = $Session->get('comment-customer');
        $originalMessageComment = $Session->get('comment-message');

        try {
            $Session->set('comment-customer', 'Customer note');
            $Session->set('comment-message', 'Message note');

            $Comments = new Comments();
            $Order = $this->createMock(AbstractOrder::class);
            $Order->method('getComments')->willReturn($Comments);
            $Order->expects(self::once())->method('update');

            CustomerData::parseSessionOrderCommentsToOrder($Order);
            CustomerData::parseSessionOrderCommentsToOrder($Order);

            self::assertCount(1, $Comments->toArray());
            self::assertSame("Customer note\nMessage note", $Comments->toArray()[0]['message']);
        } finally {
            $Session->set('comment-customer', $originalCustomerComment);
            $Session->set('comment-message', $originalMessageComment);
        }
    }

    public function testEmptySessionCommentsLeaveOrderUntouched(): void
    {
        $Session = QUI::getSession();
        $originalCustomerComment = $Session->get('comment-customer');
        $originalMessageComment = $Session->get('comment-message');

        try {
            $Session->set('comment-customer', '');
            $Session->set('comment-message', '');
            $Order = $this->createMock(AbstractOrder::class);
            $Order->expects(self::never())->method('getComments');
            $Order->expects(self::never())->method('update');

            CustomerData::parseSessionOrderCommentsToOrder($Order);
            self::assertTrue(true);
        } finally {
            $Session->set('comment-customer', $originalCustomerComment);
            $Session->set('comment-message', $originalMessageComment);
        }
    }

    public function testSaveStopsForAnotherStepMissingAddressOrMissingOrder(): void
    {
        $originalRequest = $_REQUEST;

        try {
            $Step = new CustomerData();

            $_REQUEST = ['current' => 'Payment', 'addressId' => 1];
            $Step->save();

            $_REQUEST = ['current' => 'Customer'];
            $Step->save();

            $_REQUEST = ['current' => 'Customer', 'addressId' => 1];
            $Step->save();

            self::assertTrue(true);
        } finally {
            $_REQUEST = $originalRequest;
        }
    }

    public function testSaveUpdatesAddressUserAndOrder(): void
    {
        $originalRequest = $_REQUEST;
        $Session = QUI::getSession();
        $originalCustomerComment = $Session->get('comment-customer');
        $originalMessageComment = $Session->get('comment-message');
        $addressAttributes = [];
        $userAttributes = [];

        try {
            $_REQUEST = [
                'current' => 'Customer',
                'addressId' => 17,
                'company' => 'Example Ltd.',
                'salutation' => 'ms',
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'street' => ' Example Street ',
                'street_number' => ' 42 ',
                'zip' => '12345',
                'city' => 'London',
                'country' => 'GB',
                'tel' => '+44 123',
                'comment-customer' => 'Customer note',
                'comment-message' => 'Message note',
                'businessType' => 'b2b',
                'vatId' => 'GB123',
                'shipping-address' => -1
            ];

            $User = $this->createMock(User::class);
            $User->method('getAttribute')->willReturnCallback(
                static fn(string $name): mixed => match ($name) {
                    'quiqqer.erp.address', 'quiqqer.erp.euVatId', 'firstname', 'lastname' => '',
                    default => null
                }
            );
            $User->method('setAttribute')->willReturnCallback(
                static function (string $name, mixed $value) use (&$userAttributes): void {
                    $userAttributes[$name] = $value;
                }
            );
            $User->expects(self::once())->method('setCompanyStatus')->with(true);
            $User->expects(self::once())->method('save');
            $User->expects(self::once())->method('refresh');

            $Address = $this->createMock(Address::class);
            $Address->method('getUser')->willReturn($User);
            $Address->method('getUUID')->willReturn('address-uuid');
            $Address->method('setAttribute')->willReturnCallback(
                static function (string $name, mixed $value) use (&$addressAttributes): void {
                    $addressAttributes[$name] = $value;
                }
            );
            $Address->expects(self::once())->method('editPhone')->with(0, '+44 123');
            $Address->expects(self::once())->method('save');

            $Order = $this->createMock(AbstractOrder::class);
            $Order->expects(self::once())->method('setInvoiceAddress')->with($Address);
            $Order->expects(self::once())->method('setDeliveryAddress')->with(['id' => -1]);
            $Order->expects(self::once())->method('setCustomer')->with($User);
            $Order->expects(self::once())->method('update');

            $Step = $this->getMockBuilder(CustomerData::class)
                ->onlyMethods(['getAddressById'])
                ->getMock();
            $Step->method('getAddressById')->with(17)->willReturn($Address);
            $Step->setAttribute('Order', $Order);
            $Step->save();

            self::assertSame('Example Street 42', $addressAttributes['street_no']);
            self::assertSame('Example Ltd.', $addressAttributes['company']);
            self::assertSame(QUI\ERP\Utils\User::IS_NETTO_USER, $userAttributes['quiqqer.erp.isNettoUser']);
            self::assertSame('address-uuid', $userAttributes['quiqqer.erp.address']);
            self::assertSame('GB123', $userAttributes['quiqqer.erp.euVatId']);
            self::assertSame('Ada', $userAttributes['firstname']);
            self::assertSame('Lovelace', $userAttributes['lastname']);
            self::assertSame('Customer note', $Session->get('comment-customer'));
            self::assertSame('Message note', $Session->get('comment-message'));
        } finally {
            $_REQUEST = $originalRequest;
            $Session->set('comment-customer', $originalCustomerComment);
            $Session->set('comment-message', $originalMessageComment);
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createAddress(array $attributes): Address
    {
        $Address = $this->createMock(Address::class);
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => $attributes[$name] ?? null
        );

        return $Address;
    }

    private function createErpAddress(string $uuid): ERPAddress
    {
        $attributes = [
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'street_no' => 'Example Street 1',
            'city' => 'London',
            'country' => 'GB'
        ];
        $Address = $this->createMock(ERPAddress::class);
        $Address->method('getUUID')->willReturn($uuid);
        $Address->method('getAttribute')->willReturnCallback(
            static fn(string $name): mixed => $attributes[$name] ?? null
        );

        return $Address;
    }

    private function createCustomerDataWithInvoiceAddress(Address $Address): CustomerData
    {
        $Step = $this->getMockBuilder(CustomerData::class)
            ->onlyMethods(['getInvoiceAddress'])
            ->getMock();
        $Step->method('getInvoiceAddress')->willReturn($Address);

        return $Step;
    }

    private function invokeProtectedBool(CustomerData $Step, string $method, Address $Address): bool
    {
        $Reflection = new ReflectionClass(CustomerData::class);

        return $Reflection->getMethod($method)->invoke($Step, $Address);
    }
}
