<?php

declare(strict_types=1);

namespace QUI\ERP\Order\DemoData;

use DateTimeImmutable;
use QUI;
use QUI\ERP\Accounting\Article;
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
use QUI\ERP\Order\ProcessingStatus\Handler as ProcessingStatusHandler;

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

        foreach ($dateRanges as $dateRange) {
            foreach ($this->getDemoDates($dateRange) as $dateIndex => $date) {
                $systemUser = QUI::getUsers()->getSystemUser();
                $customerReference = $customers[$dateIndex % count($customers)];
                $order = Factory::getInstance()->create($systemUser);
                $order->setCustomer(QUI::getUsers()->get($customerReference->entityUuid));
                $order->setAttribute('date', $date->format('Y-m-d H:i:s'));
                $order->setAttribute('no_invoice_auto_create', true);
                $order->addArticle(new Article([
                    'id' => 1,
                    'articleNo' => 'DEMO-ORDER-' . ($dateIndex + 1),
                    'title' => 'Demo order item',
                    'unitPrice' => 99,
                    'quantity' => 1,
                    'vat' => 19
                ]));
                $statuses = ProcessingStatusHandler::getInstance()->getProcessingStatusList();

                if ($statuses !== []) {
                    $order->setProcessingStatus($statuses[array_rand($statuses)]);
                }
                $order->save($systemUser);

                $createdDemoData[] = new CreatedDemoData(
                    self::ENTITY_TYPE,
                    $order->getUUID(),
                    'order_' . ($dateIndex + 1)
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

    /**
     * @return list<DateTimeImmutable>
     */
    private function getDemoDates(DemoDataDateRange $dateRange): array
    {
        $dates = [];

        for ($year = (int)$dateRange->startDate->format('Y'); $year <= (int)$dateRange->endDate->format('Y'); $year++) {
            $yearStart = max($dateRange->startDate, new DateTimeImmutable($year . '-01-01 00:00:00'));
            $yearEnd = min($dateRange->endDate, new DateTimeImmutable($year . '-12-31 23:59:59'));
            $intervalDays = max(0, (int)$yearStart->diff($yearEnd)->days);

            for ($index = 0; $index < 10; $index++) {
                $dates[] = $yearStart->modify('+' . (int)floor($intervalDays * $index / 9) . ' days');
            }
        }

        return $dates;
    }
}
