<?php

// phpcs:disable

namespace QUI\ERP\DemoData\DTO;

use DateTimeImmutable;

if (!class_exists(CreatedDemoData::class, false)) { final readonly class CreatedDemoData { public function __construct(public string $entityType, public string $entityUuid, public ?string $referenceKey = null) {} } }
if (!class_exists(CreatedDemoDataCollection::class, false)) { final readonly class CreatedDemoDataCollection { /** @param list<CreatedDemoData> $items */ public function __construct(private array $items) {} /** @return list<CreatedDemoData> */ public function all(): array { return $this->items; } } }
if (!class_exists(DemoDataDateRange::class, false)) { final readonly class DemoDataDateRange { public function __construct(public DateTimeImmutable $startDate, public DateTimeImmutable $endDate) {} } }
if (!class_exists(DemoDataReference::class, false)) { final readonly class DemoDataReference { public function __construct(public string $providerIdentifier, public string $entityType, public string $entityUuid, public ?string $referenceKey = null) {} } }
if (!class_exists(DemoDataReferenceCollection::class, false)) { final readonly class DemoDataReferenceCollection { /** @param array<string, list<DemoDataReference>> $referencesByProvider */ public function __construct(private array $referencesByProvider = []) {} /** @return list<DemoDataReference> */ public function forProvider(string $providerIdentifier): array { return $this->referencesByProvider[$providerIdentifier] ?? []; } } }
if (!class_exists(DemoDataCreationContext::class, false)) { final readonly class DemoDataCreationContext { /** @param list<DemoDataDateRange> $dateRanges */ public function __construct(private DemoDataReferenceCollection $dependencyData, private array $dateRanges = []) {} /** @return list<DemoDataReference> */ public function getDependencyData(string $providerIdentifier): array { return $this->dependencyData->forProvider($providerIdentifier); } /** @return list<DemoDataDateRange> */ public function getDateRanges(): array { return $this->dateRanges; } } }

namespace QUI\ERP\DemoData\Exception;
if (!class_exists(DemoDataException::class, false)) { class DemoDataException extends \RuntimeException {} }

namespace QUI\ERP\DemoData\Contract;
use Doctrine\DBAL\Connection;
use QUI\Locale;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
if (!interface_exists(DemoDataCreatorInterface::class, false)) { interface DemoDataCreatorInterface { /** @return list<string> */ public function getDependencies(): array; public function createDemoData(DemoDataCreationContext $context): CreatedDemoDataCollection; public function deleteDemoData(DemoDataReferenceCollection $demoData): void; } }
if (!interface_exists(DemoDataProviderInterface::class, false)) { interface DemoDataProviderInterface { public function getIdentifier(): string; public function getTitle(?Locale $locale = null): string; public function getDemoDataCreator(Connection $connection): DemoDataCreatorInterface; } }
