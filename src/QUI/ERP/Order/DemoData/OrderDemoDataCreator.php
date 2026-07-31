<?php

declare(strict_types=1);

namespace QUI\ERP\Order\DemoData;

use DateTimeImmutable;
use QUI;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataDateRange;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\ERP\DemoData\Exception\DemoDataException;
use QUI\ERP\Order\Factory;
use QUI\ERP\Order\Handler;

final class OrderDemoDataCreator implements DemoDataCreatorInterface
{
    private const PROVIDER_IDENTIFIER = 'quiqqer.order';
    private const ENTITY_TYPE = 'order';

    public function getDependencies(): array
    {
        return ['quiqqer.customer'];
    }

    public function createDemoData(DemoDataCreationContext $context): CreatedDemoDataCollection
    {
        $customers = $this->getCustomerReferences($context);
        $dateRanges = $context->getDateRanges();
        $createdDemoData = [];

        if ($dateRanges === []) {
            $now = new DateTimeImmutable();
            $dateRanges = [new DemoDataDateRange($now, $now)];
        }

        foreach ($dateRanges as $dateIndex => $dateRange) {
            foreach ($customers as $customerReference) {
                $systemUser = QUI::getUsers()->getSystemUser();
                $order = Factory::getInstance()->create($systemUser);
                $order->setCustomer(QUI::getUsers()->get($customerReference->entityUuid));
                $order->setAttribute('date', $dateRange->startDate->format('Y-m-d H:i:s'));
                $order->setAttribute('no_invoice_auto_create', true);
                $order->save($systemUser);

                $createdDemoData[] = new CreatedDemoData(
                    self::ENTITY_TYPE,
                    $order->getUUID(),
                    'order_' . ($dateIndex + 1) . '_' . $customerReference->referenceKey
                );
            }
        }

        return new CreatedDemoDataCollection($createdDemoData);
    }

    public function deleteDemoData(DemoDataReferenceCollection $demoData): void
    {
        $systemUser = QUI::getUsers()->getSystemUser();

        foreach ($demoData->forProvider(self::PROVIDER_IDENTIFIER) as $reference) {
            if ($reference->entityType !== self::ENTITY_TYPE) {
                throw new DemoDataException('Order demo data reference has an invalid entity type.');
            }

            Handler::getInstance()->getOrderByHash($reference->entityUuid)->delete($systemUser);
        }
    }

    /**
     * @return list<DemoDataReference>
     */
    private function getCustomerReferences(DemoDataCreationContext $context): array
    {
        $customers = array_values(array_filter(
            $context->getDependencyData('quiqqer.customer'),
            static fn(DemoDataReference $reference): bool => $reference->entityType === 'customer'
        ));

        if ($customers === []) {
            throw new DemoDataException('Customer demo data references are missing.');
        }

        return $customers;
    }
}
