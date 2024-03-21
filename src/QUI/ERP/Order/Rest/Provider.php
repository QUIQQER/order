<?php

namespace QUI\ERP\Order\Rest;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use QUI;
use QUI\ERP\Products\Field\Exception as EcoynProductFieldException;
use QUI\ERP\User as ErpUser;
use QUI\REST\Response;
use QUI\REST\Server;
use QUI\REST\Utils\RequestUtils;
use QUI\Utils\Security\Orthos;
use Slim\Routing\RouteCollectorProxy;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\Database\DB;
use QUI\ERP\Constants as ERPConstants;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Order\Utils\Utils as OrderUtils;

use function date_create;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;

/**
 * REST API provider for quiqqer/order
 */
class Provider implements QUI\REST\ProviderInterface
{
    /**
     * @param OrderHandler|null $OrderHandler
     * @param DB|null $DB
     */
    public function __construct(
        protected ?OrderHandler $OrderHandler = null,
        protected ?DB $DB = null,
        protected ?CurrencyHandler $CurrencyHandler = null
    ) {
        if (is_null($this->OrderHandler)) {
            $this->OrderHandler = OrderHandler::getInstance();
        }

        if (is_null($this->DB)) {
            $this->DB = QUI::getDatabase();
        }

        if (is_null($this->CurrencyHandler)) {
            $this->CurrencyHandler = new CurrencyHandler();
        }
    }

    /**
     * @param Server $Server
     * @return void
     */
    public function register(Server $Server): void
    {
        $Slim = $Server->getSlim();

        // Register paths
        $Slim->group('/order', function (RouteCollectorProxy $RouteCollector) {
            $RouteCollector->post('/insert', [$this, 'insert']);

            // Specific for syncing
            $RouteCollector->get('/getOrderUuidsInDateRange', [$this, 'getOrderUuidsInDateRange']);
        });
    }

    // region Endpoints

    /**
     * POST /order/insert
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return Response
     */
    public function insert(RequestInterface $Request, ResponseInterface $Response, array $args): MessageInterface
    {
        $requiredFields = [
            'orders'
        ];

        foreach ($requiredFields as $requiredField) {
            $fieldValue = RequestUtils::getFieldFromRequest($Request, $requiredField);

            if (empty($fieldValue)) {
                return $this->parseErrorResponse(
                    $Response,
                    'Missing / empty field: "' . $requiredField . '".',
                    ErrorCode::MISSING_FIELD->value,
                    SymfonyResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $orders = RequestUtils::getFieldFromRequest($Request, 'orders');

        if (!is_array($orders)) {
            return $this->parseErrorResponse(
                $Response,
                'orders field is not an array.',
                ErrorCode::FIELD_VALUE_INVALID->value,
                SymfonyResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            foreach ($orders as $k => $bookingData) {
                $this->createOrderFromRequestOrderData($bookingData, $k);
            }
        } catch (RestProviderException $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return $this->parseErrorResponse(
                $Response,
                $Exception->getMessage(),
                $Exception->getCode(),
                SymfonyResponse::HTTP_BAD_REQUEST
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $this->parseErrorResponse(
                $Response,
                'An unexpected error occurred. Please contact the API provider.',
                ErrorCode::SERVER_ERROR->value
            );
        }

        return $this->parseSuccessResponse(
            $Response,
            [
                ResponseField::SUCCESS->value => true
            ],
            SymfonyResponse::HTTP_CREATED
        );
    }

    /**
     * GET /order/getOrderUuidsInDateRange
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return Response
     */
    public function getOrderUuidsInDateRange(
        RequestInterface $Request,
        ResponseInterface $Response,
        array $args
    ): MessageInterface {
        $requiredFields = [
            'from',
            'to'
        ];

        foreach ($requiredFields as $requiredField) {
            $fieldValue = RequestUtils::getFieldFromRequest($Request, $requiredField);

            if (empty($fieldValue)) {
                return $this->parseErrorResponse(
                    $Response,
                    'Missing / empty field: "' . $requiredField . '".',
                    ErrorCode::MISSING_FIELD->value,
                    SymfonyResponse::HTTP_BAD_REQUEST
                );
            }

            $Date = date_create($fieldValue);

            if (!$Date) {
                return $this->parseErrorResponse(
                    $Response,
                    "Field $fieldValue cannot be parsed as a date (date_create).",
                    ErrorCode::FIELD_VALUE_INVALID->value,
                    SymfonyResponse::HTTP_BAD_REQUEST
                );
            }
        }

        $FromDate = date_create(RequestUtils::getFieldFromRequest($Request, 'from'));
        $ToDate = date_create(RequestUtils::getFieldFromRequest($Request, 'to'));

        try {
            $orders = $this->OrderHandler->getAllCreatedInDateRange($FromDate, $ToDate);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $this->parseErrorResponse(
                $Response,
                'An unexpected error occurred. Please contact the API provider.',
                ErrorCode::SERVER_ERROR->value
            );
        }

        $orderUuids = [];

        foreach ($orders as $Order) {
            $orderUuids[] = $Order->getUuid();
        }

        return $this->parseSuccessResponse(
            $Response,
            [
                ResponseField::ORDER_UUIDS->value => $orderUuids
            ]
        );
    }

    // endregion

    /**
     * Get file containting OpenApi definition for this API.
     *
     * @return string|false - Absolute file path or false if no definition exists
     */
    public function getOpenApiDefinitionFile(): string|false
    {
        // @todo
        return false;
    }

    /**
     * Get unique internal API name.
     *
     * This is required for requesting specific data about an API (i.e. OpenApi definition).
     *
     * @return string - Only letters; no other characters!
     */
    public function getName(): string
    {
        return 'QuiqqerOrder';
    }

    /**
     * Get title of this API.
     *
     * @param QUI\Locale|null $Locale (optional)
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'RestProvider.title');
    }

    // region Response

    /**
     * Set data to successful response and return it.
     *
     * @param ResponseInterface $Response
     * @param string|array $msg
     * @param int $statusCode (optional) - [default: 200]
     * @return ResponseInterface
     */
    protected function parseSuccessResponse(
        ResponseInterface $Response,
        string|array $msg,
        int $statusCode = SymfonyResponse::HTTP_OK
    ): ResponseInterface {
        $body = [
            'msg' => $msg,
            'error' => false
        ];

        $Response->getBody()->write(json_encode($body));

        return $Response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Set data to error response and return it.
     *
     * @param ResponseInterface $Response
     * @param string|array $msg
     * @param int $errorCode
     * @param int $statusCode (optional) - [default: 200]
     * @return ResponseInterface
     */
    protected function parseErrorResponse(
        ResponseInterface $Response,
        string|array $msg,
        int $errorCode,
        int $statusCode = SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
    ): ResponseInterface {
        $body = [
            'msg' => $msg,
            'error' => true,
            'errorCode' => $errorCode
        ];

        $Response->getBody()->write(json_encode($body));

        return $Response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    // endregion

    // region Utils

    /**
     * @param array $orderData
     * @param int $key
     * @return void
     *
     * @throws QUI\Users\Exception
     * @throws RestProviderException
     * @throws EcoynProductFieldException
     * @throws QUI\Exception
     * @throws QUI\ERP\Order\Exception
     */
    protected function createOrderFromRequestOrderData(array $orderData, int $key): void
    {
        // orderField => isRequired
        $orderFields = [
            'idPrefix' => false,
            'prefixedId' => true,
            'invoiceId' => false,
            'hash' => true,
            'globalProcessId' => true,
            'cDate' => true,
            'cUser' => false,
            'data' => false,
            'customerId' => false,
            'customer' => true,
            'comments' => false,
            'statusMails' => false,
            'currency' => false,

            'articles' => true,
            'addressDelivery' => false,
            'addressInvoice' => false,
            'paymentId' => true,
            'status' => true,
            'paidStatus' => false,
            'shippingStatus' => false,
            'shipping' => false
        ];

        foreach ($orderFields as $orderField => $isRequired) {
            if (empty($orderData[$orderField])) {
                if ($isRequired) {
                    throw new RestProviderException(
                        "Missing / empty required field in order data ($key): $orderField.",
                        ErrorCode::MISSING_FIELD->value
                    );
                }

                continue;
            }

            $value = $orderData[$orderField];

            // Validate variable type
            switch ($orderField) {
                // string
                case 'idPrefix':
                case 'prefixedId':
                case 'invoiceId':
                case 'hash':
                case 'globalProcessId':
                case 'currency':
//                case 'customerId': // @todo muss eigentlich UUID sein, ist aber noch INT
//                case 'cUser':  // @todo muss eigentlich UUID sein, ist aber noch INT
                    if (!is_string($value)) {
                        throw new RestProviderException(
                            "Invalid value type for '$orderField' in order data ($key). Value must be string.",
                            ErrorCode::FIELD_VALUE_INVALID->value
                        );
                    }
                    break;

                // numeric
                case 'status':
                case 'paidStatus':
                case 'paymentId':
                case 'shippingStatus':
                case 'shipping':
                    if (!is_numeric($value)) {
                        throw new RestProviderException(
                            "Invalid value type for '$orderField' in order data ($key). Value must be numeric.",
                            ErrorCode::FIELD_VALUE_INVALID->value
                        );
                    }
                    break;

                // array
                case 'articles':
                case 'customer':
                case 'data':
                case 'comments':
                case 'statusMails':
                case 'addressDelivery':
                case 'addressInvoice':
                    if (!is_array($value)) {
                        throw new RestProviderException(
                            "Invalid value type for '$orderField' in booking data ($key). Value must be array.",
                            ErrorCode::FIELD_VALUE_INVALID->value
                        );
                    }
                    break;

                // date
                case 'cDate':
                    if (!is_string($value)) {
                        throw new RestProviderException(
                            "Invalid value type for '$orderField' in booking data ($key). Value must be string.",
                            ErrorCode::FIELD_VALUE_INVALID->value
                        );
                    }

                    $Date = date_create($value);

                    if (!$Date) {
                        throw new RestProviderException(
                            "Invalid value type for '$orderField' in booking data ($key). Value must be valid date.",
                            ErrorCode::FIELD_VALUE_INVALID->value
                        );
                    }
                    break;
            }
        }

        // Validate order hash (must be unique)
        $orderHash = $orderData['hash'];
        $this->validateInsertOrderHash($orderHash);

        // Validate customer
        if (!empty($orderData['customerId'])) {
            $this->validateInsertCustomerId($orderData['customerId']);
        }

        // Validate currency
        if (!empty($orderData['currency'])) {
            $this->validateInsertCurrency($orderData['currency']);
        }

        $dbRow = [
            'id_prefix' => !empty($orderData['idPrefix']) ? $orderData['idPrefix'] : OrderUtils::getOrderPrefix(),
            'id_str' => $orderData['prefixedId'],
            'hash' => $orderData['hash'],
            'globalProcessId' => $orderData['globalProcessId'],
            'status' => (int)$orderData['status'],
            'customer' => json_encode($orderData['customer']),
            'createDate' => $orderData['createDate'],
            'articles' => json_encode($orderData['articles']),

            'payment_id' => (int)$orderData['paymentId'],
            'invoice_id' => !empty($orderData['invoiceId']) ? $orderData['invoiceId'] : null,
            'paid_status' => !empty($orderData['paidStatus']) ?
                (int)$orderData['paidStatus'] :
                ERPConstants::PAYMENT_STATUS_OPEN,
            'shipping_status' => !empty($orderData['shippingStatus']) ?
                (int)$orderData['shippingStatus'] :
                null,
            'shipping_id' => !empty($orderData['shipping']) ?
                (int)$orderData['shipping'] :
                null,
            'customerId' => !empty($orderData['customerId']) ?
                $orderData['customerId'] :
                null,
            'c_user' => !empty($orderData['cUser']) ?
                $orderData['cUser'] :
                null,
            'addressInvoice' => !empty($orderData['addressInvoice']) ?
                json_encode($orderData['addressInvoice']) :
                null,
            'addressDelivery' => !empty($orderData['addressDelivery']) ?
                json_encode($orderData['addressDelivery']) :
                null,
            'c_date' => !empty($orderData['cDate']) ?
                $orderData['cDate'] :
                null,
            'data' => !empty($orderData['data']) ?
                json_encode($orderData['data']) :
                null,
            'comments' => !empty($orderData['comments']) ?
                json_encode($orderData['comments']) :
                null,
            'status_mails' => !empty($orderData['statusMails']) ?
                json_encode($orderData['statusMails']) :
                null,
            'currency' => !empty($orderData['currency']) ?
                $orderData['currency'] :
                null,
        ];

        $this->DB->insert($this->OrderHandler->table(), $dbRow);
    }

    /**
     * @param string $orderHash
     * @return void
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     * @throws RestProviderException
     */
    protected function validateInsertOrderHash(string $orderHash): void
    {
        try {
            $this->OrderHandler->getOrderByHash($orderHash);

            throw new RestProviderException(
                "Order with hash / UUID $orderHash already exists. Cannot insert.",
                ErrorCode::ORDER_UUID_ALREADY_EXISTS->value
            );
        } catch (\Exception $Exception) {
            if ($Exception->getCode() !== $this->OrderHandler::ERROR_ORDER_NOT_FOUND) {
                throw $Exception;
            }
            // all good, order does not exist
        }
    }

    /**
     * @param string $customerId
     * @return void
     *
     * @throws QUI\Users\Exception
     * @throws RestProviderException
     */
    protected function validateInsertCustomerId(string $customerId): void
    {
        try {
            QUI::getUsers()->get($customerId);
        } catch (\Exception $Exception) {
            if ($Exception->getCode() === 404) {
                throw new RestProviderException(
                    "Non-existing customer user in order data: $customerId",
                    ErrorCode::MISSING_FIELD->value
                );
            } else {
                QUI\System\Log::writeException($Exception);
                throw $Exception;
            }
        }
    }

    /**
     * @param string $currency
     * @return void
     * @throws RestProviderException
     */
    protected function validateInsertCurrency(string $currency): void
    {
        if (!$this->CurrencyHandler::existCurrency($currency)) {
            throw new RestProviderException(
                "Non-existing currency in order data: " . $currency,
                ErrorCode::MISSING_FIELD->value
            );
        }
    }

    /**
     * @param array $customerData
     * @return ErpUser
     *
     * @throws RestProviderException
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    protected function parseCustomerFromRequestOrderData(array $customerData): ErpUser
    {
        $requiredFields = [
            'email'
        ];

        foreach ($requiredFields as $requiredField) {
            if (empty($customerData[$requiredField])) {
                throw new RestProviderException(
                    'Missing / empty field in customer data: ' . $requiredField,
                    ErrorCode::MISSING_FIELD->value
                );
            }
        }

        $email = $customerData['email'];

        if (!Orthos::checkMailSyntax($email)) {
            throw new RestProviderException(
                'Customer email address is invalid: ' . $email,
                ErrorCode::FIELD_VALUE_INVALID->value
            );
        }

        $CustomerAddress = new QUI\ERP\Address([
            'salutation' => !empty($customerData['salutation']) ? $customerData['salutation'] : null,
            'firstname' => !empty($customerData['firstname']) ? $customerData['firstname'] : null,
            'lastname' => !empty($customerData['lastname']) ? $customerData['lastname'] : null,
            'company' => !empty($customerData['company']) ? $customerData['company'] : null,
            'country' => !empty($customerData['country']) ? $customerData['country'] : null
        ]);

        $CustomerAddress->addMail($email);

        if (!empty($customerData['mobile'])) {
            $CustomerAddress->addPhone([
                'type' => 'mobile',
                'no' => $customerData['mobile']
            ]);
        }

        $SystemUser = QUI::getUsers()->getSystemUser();
        $Users = QUI::getUsers();

        if ($Users->emailExists($email) || $Users->usernameExists($email)) {
            if ($Users->emailExists($email)) {
                $User = $Users->getUserByMail($email);
            } else {
                $User = $Users->getUserByName($email);
            }

            // Add customer address if not already in customer
            $userHasCustomerAddress = false;

            /** @var QUI\Users\Address $UserAddress */
            foreach ($User->getAddressList() as $UserAddress) {
                if ($UserAddress->equals($CustomerAddress)) {
                    $userHasCustomerAddress = true;
                    break;
                }
            }

            if (!$userHasCustomerAddress) {
                $User->addAddress($CustomerAddress->getAttributes(), $SystemUser);
            }
        } else {
            $User = $Users->createChild($email, $SystemUser);
            $User->getStandardAddress()->setAttributes($CustomerAddress->getAttributes());
            $User->getStandardAddress()->save($SystemUser);

            $User->save($SystemUser);
        }

        $ErpUser = ErpUser::convertUserToErpUser($User);
        $ErpUser->setAttribute('uuid', $User->getUniqueId());

        return $ErpUser;
    }

    // endregion
}
