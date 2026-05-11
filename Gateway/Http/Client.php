<?php
namespace Tabby\Checkout\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Tabby\Checkout\Model\Api\Http\Client as HttpClient;
use Tabby\Checkout\Model\Api\Tabby as TabbyAPI;

class Client extends TabbyAPI implements ClientInterface
{
    const SUCCESS = 200;
    const BAD_DATA = 400;
    const NOT_AUTHORIZED = 401;
    const NO_PERMISSION = 403;
    const NOT_FOUND = 404;
    const I_S_ERROR = 500;

    /**
     * @var array
     */
    private $results = [
        self::SUCCESS,
        self::BAD_DATA,
        self::NOT_AUTHORIZED,
        self::NO_PERMISSION,
        self::NOT_FOUND,
        self::I_S_ERROR
    ];

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {

        $this->client->setTimeout(120);

        foreach ($transferObject->getHeaders() as $key => $value) {
            $this->client->addHeader($key, $value);
        }

        $this->client->send($transferObject->getMethod(), $transferObject->getUri(), $transferObject->getBody());

        $this->logRequest($transferObject->getUri(), $this->client, $transferObject->getBody());

        $response = [];

        switch ($this->client->getStatus()) {
            case 100:
            case 200:
            case 400:
            case 401:
            case 403:
                $response = json_decode($this->client->getBody(), true);
                if ($response === null) {
                    $this->logRequest(
                        $transferObject->getUri(),
                        $this->client,
                        $transferObject->getBody(),
                        "warn",
                        "non json reply received from Tabby API"
                    );
                    $response = [
                        'status'    => 'error',
                        'errorType' => 'nonjson',
                        'error'     => 'Not JSON reply',
                    ];
                }
                break;
            case 404:
                $response = [
                    'status'    => 'error',
                    'errorType' => 'notfound',
                    'error'     => 'Not Found',
                ];
                break;
            case 500:
                $response = [
                    'status'    => 'error',
                    'errorType' => 'iserror',
                    'error'     => 'Internal Server Error',
                ];
                break;
            default:
                $response = [
                    'status'    => 'error',
                    'errorType' => 'httpcode',
                    'error'     => 'Unknown HTTP Code',
                ];
                $this->logRequest(
                    $transferObject->getUri(),
                    $this->client,
                    $transferObject->getBody(),
                    "warn",
                    "Unknown HTTP Code: " . $this->client->getStatus() . ' - ' . $this->client->getBody()
                );
                break;
        }

        return $response;
    }

}
