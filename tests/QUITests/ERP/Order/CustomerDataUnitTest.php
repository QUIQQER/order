<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Order\Controls\OrderProcess\CustomerData;
use QUI\ERP\Order\Exception;
use QUI\Users\Address;

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
}
