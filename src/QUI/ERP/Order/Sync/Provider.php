<?php

namespace QUI\ERP\Order\Sync;

use QUI;
use DateTime;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\OAuth\Client\ClientException;
use QUI\Sync\Entity\SyncPackage;
use QUI\Sync\Entity\SyncPushProtocolEntry;
use QUI\Sync\Exception\BaseException;
use QUI\Sync\Exception\PackagesNotInSyncException;
use QUI\Sync\Provider\SyncProviderInterface;
use QUI\Sync\Service\Interface\SyncValidationServiceInterface;
use QUI\Sync\Service\SyncValidationService;
use QUI\Sync\Utils\RestUtils;
use QUI\Sync\Entity\SyncTarget;
use QUI\ERP\Order\Rest\Endpoint as OrderRestApiEndpoint;
use QUI\ERP\Order\Rest\ResponseField;

use function date_create;
use function in_array;
use function is_null;

/**
 * quiqqer/sync provider for bookings..
 */
class Provider implements SyncProviderInterface
{
    protected string $package = '';

    /**
     * @param SyncValidationServiceInterface|null $SyncValidationService
     * @param OrderHandler|null $OrderHandler
     */
    public function __construct(
        protected ?SyncValidationServiceInterface $SyncValidationService = null,
        protected ?OrderHandler $OrderHandler = null
    ) {
        if (is_null($this->SyncValidationService)) {
            $this->SyncValidationService = new SyncValidationService();
        }

        if (is_null($this->OrderHandler)) {
            $this->OrderHandler = OrderHandler::getInstance();
        }
    }

    /**
     * Validate if syncing to the given system is possible.
     *
     * @param SyncTarget $SyncTarget
     * @return void
     *
     * @throws ClientException
     * @throws BaseException
     * @throws PackagesNotInSyncException
     */
    public function validateSync(SyncTarget $SyncTarget): void
    {
        $this->SyncValidationService->checkIfPackagesAreInSync(
            [
                'quiqqer/order',
                'quiqqer/quiqqer' // for users via UUID
            ],
            $SyncTarget
        );


        // @todo check if payments are identical on the target system

//        $OAuthClient = RestUtils::getOAuthClientForSyncTarget($SyncTarget);
//        $response = RestUtils::getDataFromRestApiResponse(
//            $OAuthClient->postRequest(
//                'booking/validateSync',
//                [
//                    'capacities' => $capacitiesValidation
//                ]
//            )
//        );
//
//        if (empty($response[ResponseField::MSG->value][ResponseField::CAPACITIES->value])) {
//            throw new PackagesNotInSyncException(
//                [
//                    'quiqqer/booking'
//                ],
//                $SyncTarget,
//                'Capacities of quiqqer/booking on target system are not in sync.'
//            );
//        }
    }

    /**
     * Push data to target system.
     *
     * @param SyncPackage $SyncPackage
     * @param SyncTarget $SyncTarget
     * @param SyncPushProtocolEntry $SyncProtocolEntry
     * @return void
     *
     * @throws BaseException
     * @throws ClientException
     * @throws SyncProviderException
     */
    public function syncPackageToTarget(
        SyncPackage $SyncPackage,
        SyncTarget $SyncTarget,
        SyncPushProtocolEntry $SyncProtocolEntry
    ): void {
        $OAuthClient = RestUtils::getOAuthClientForSyncTarget($SyncTarget);
        $syncOrders = $this->getSyncOrders($SyncTarget, $SyncPackage->getLastSyncDate());

        if (empty($syncOrders)) {
            $SyncProtocolEntry->addMessage(
                "No orders to sync. Target system already has all orders from this system."
            );
            return;
        }

        $response = RestUtils::getDataFromRestApiResponse(
            $OAuthClient->postRequest(
                OrderRestApiEndpoint::INSERT->value,
                [
                    'orders' => $syncOrders
                ]
            )
        );

        $this->parseContentFromApiResponse($response);
    }

    /**
     * Get list of packages that this provider can sync.
     *
     * @return string[]
     */
    public function getSyncablePackages(): array
    {
        return [
            'quiqqer/order'
        ];
    }

    /**
     * @param string $package
     * @return void
     */
    public function setOriginPackage(string $package): void
    {
        $this->package = $package;
    }

    /**
     * @return string
     */
    public function getOriginPackage(): string
    {
        return $this->package;
    }

    /**
     * @param SyncTarget $SyncTarget
     * @param DateTime|null $LastSyncDate
     * @return array
     *
     * @throws BaseException
     * @throws ClientException
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    protected function getSyncOrders(SyncTarget $SyncTarget, ?DateTime $LastSyncDate = null): array
    {
        $LastCreatedBooking = $this->OrderHandler->getLastCreated();

        if (!$LastCreatedBooking) {
            return [];
        }

        if (!$LastSyncDate) {
            $LastSyncDate = date_create('1970-01-01');
        }

        $OAuthClient = RestUtils::getOAuthClientForSyncTarget($SyncTarget);
        $response = RestUtils::getDataFromRestApiResponse(
            $OAuthClient->getRequest(
                OrderRestApiEndpoint::GET_ORDER_UUIDS_IN_DATE_RANGE->value,
                [
                    'from' => $LastSyncDate->format('Y-m-d H:i:s'),
                    'to' => $LastCreatedBooking->getCreateDate()
                ]
            )
        );

        $data = $this->parseContentFromApiResponse($response);

        if (!isset($data[ResponseField::ORDER_UUIDS->value])) {
            throw new SyncProviderException(
                'Sync process failed :: There was an error during an API call to the target system.'
                . ' Error: Incorrect return message content.'
            );
        }

        $orderUuidsOnTargetSystem = $data[ResponseField::ORDER_UUIDS->value];

        $orders = $this->OrderHandler->getAllCreatedInDateRange(
            $LastSyncDate,
            date_create($LastCreatedBooking->getCreateDate())
        );

        $syncOrders = [];

        foreach ($orders as $Order) {
            if (in_array($Order->getUuid(), $orderUuidsOnTargetSystem)) {
                continue;
            }

            $orderData = $Order->toArray();
            $orderData['globalProcessId'] = $Order->getGlobalProcessId();
            $orderData['idPrefix'] = $Order->getIdPrefix();

            $syncOrders[] = $orderData;
        }

        return $syncOrders;
    }

    /**
     * @param mixed $response
     * @return mixed
     *
     * @throws SyncProviderException
     */
    protected function parseContentFromApiResponse(mixed $response): mixed
    {
        if (!empty($response[ResponseField::ERROR->value])) {
            $msg = !empty($response[ResponseField::MSG->value]) ? $response[ResponseField::MSG->value] : '-';
            $errorCode = !empty($response[ResponseField::MSG->value]) ? $response[ResponseField::MSG->value] : '-';

            throw new SyncProviderException(
                'Sync process failed :: There was an error during an API call to the target system.'
                . ' Error: ' . $msg . '(code: ' . $errorCode . ')'
            );
        }

        if (empty($response[ResponseField::MSG->value])) {
            throw new SyncProviderException(
                'Sync process failed :: There was an error during an API call to the target system.'
                . ' Error: No return message content.'
            );
        }

        return $response[ResponseField::MSG->value];
    }
}
