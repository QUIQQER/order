<?php

namespace QUI\ERP\Order\Rest;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use QUI;
use QUI\REST\Response;
use QUI\REST\Server;
use QUI\REST\Utils\RequestUtils;
use Slim\Routing\RouteCollectorProxy;
use QUI\Sync\Entity\SyncTarget;

use function is_array;
use function json_encode;

/**
 * Class Provider
 *
 * REST API provider for quiqqer/invoice
 */
class Provider implements QUI\REST\ProviderInterface
{
    /**
     * @param Server $Server
     * @return void
     */
    public function register(Server $Server): void
    {
        $Slim = $Server->getSlim();

        // Register paths
        $Slim->group('/order', function (RouteCollectorProxy $RouteCollector) {
            $RouteCollector->post('/create', [$this, 'create']);
        });
    }

    /**
     * POST /sync/start
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return Response
     */
    public function create(RequestInterface $Request, ResponseInterface $Response, array $args): Response
    {
        $apiBaseUrl = RequestUtils::getFieldFromRequest($Request, 'apiBaseUrl');

        if (empty($apiBaseUrl)) {
            return $this->getClientErrorResponse(
                'Missing field "apiBaseUrl".',
                ErrorCode::MISSING_PARAMETERS
            );
        }

        $dataFromDateStr = RequestUtils::getFieldFromRequest($Request, 'dataFromDate');

        if (empty($dataFromDateStr)) {
            return $this->getClientErrorResponse(
                'Missing field "dataFromDate".',
                ErrorCode::MISSING_PARAMETERS
            );
        }


        $SyncTarget = new SyncTarget($apiBaseUrl);

        $packages = RequestUtils::getFieldFromRequest($Request, 'packages');

        if (!empty($packages) && is_array($packages)) {
        }

        return $this->getSuccessResponse([
            'success' => true
        ]);
    }

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

    /**
     * Get generic Response with Exception code and message
     *
     * @param string $msg
     * @param ErrorCode $ErrorCode
     * @return Response
     */
    protected function getClientErrorResponse(string $msg, ErrorCode $ErrorCode): Response
    {
        $Response = new Response(400);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => true,
            'errorCode' => $ErrorCode->value
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string|array $msg
     * @return Response
     */
    protected function getSuccessResponse($msg): Response
    {
        $Response = new Response(200);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => false
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string $msg (optional)
     * @return Response
     */
    protected function getServerErrorResponse(string $msg = ''): Response
    {
        $Response = new Response(500);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => true,
            'errorCode' => self::ERROR_CODE_SERVER_ERROR
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }
}
