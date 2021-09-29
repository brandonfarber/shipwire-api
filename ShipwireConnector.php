<?php

namespace mataluis2k\shipwire;

use mataluis2k\shipwire\exceptions\InvalidAuthorizationException;
use mataluis2k\shipwire\exceptions\InvalidRequestException;
use mataluis2k\shipwire\exceptions\ShipwireConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;

class ShipwireConnector
{
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';

    /**
     * Environment method for integration. Possible values: 'live', 'sandbox'
     * @var string
     */
    static $environment = 'live';

    /**
     * Sandbox Base Url for Shipwire API
     * @var string
     */
    static $sandboxBaseUrl = 'https://api.beta.shipwire.com';

    /**
     * Live Base Url for Shipwire API
     * @var string
     */
    static $baseUrl = 'https://api.shipwire.com';

    /**
     * @var string
     */
    static $authorizationCode;

    /**
     * @var string
     */
    static $version = 'v3';

    /**
     * @var HandlerStack
     */
    static $handlerStack;

    private function __construct()
    {
    }

    /**
     * Generates the connection instance for Shipwire
     *
     * @param $username
     * @param $password
     * @param string $environment
     * @param HandlerStack $handlerStack
     */
    public static function init($username, $password, $environment = null, HandlerStack $handlerStack = null)
    {
        self::$authorizationCode = base64_encode($username . ':' . $password);
        if (null !== $environment) {
            self::$environment = $environment;
        }
        if (null !== $handlerStack) {
            self::$handlerStack = $handlerStack;
        }

        self::$instance = null;
    }

    /**
     * @var ShipwireConnector
     */
    private static $instance = null;

    /**
     * @return ShipwireConnector
     * @throws \Exception
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
            self::$instance->getClient();
        }
        return self::$instance;
    }

    /**
     * Gets guzzle client to manage URL Connections
     *
     * @return Client
     * @throws \Exception
     */
    private function getClient()
    {
        if (!isset($this->client)) {
            if (!isset(self::$authorizationCode)) {
                throw new \Exception('Invalid authorization code');
            }
            $config = ['base_uri' => self::getEndpointUrl()];

            if (isset(self::$handlerStack)) {
                $config['handler'] = self::$handlerStack;
            }

            $this->client = new Client($config);
        }
        return $this->client;
    }

    /**
     * Send an api request to Shipwire Endpoint
     *
     * @param string $resource function to be called
     * @param array $params key value parameters
     * @param string $method
     * @param string $body
     * @param bool $onlyResource
     * @return mixed
     * @throws InvalidAuthorizationException
     * @throws InvalidRequestException
     * @throws ShipwireConnectionException
     * @throws \Exception
     */
    public function api($resource, $params = [], $method = "GET", $body = null, $onlyResource = false)
    {
        $client = self::getClient();

        try {
            $headers = [
                'User-Agent'    => 'mataluis2k-shipwireapi/1.0',
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . self::$authorizationCode
            ];

            if ($body !== null) {
                $headers['content-type'] = 'application/json';
            }

            $response = $client->request($method, '/api/v3/'.$resource, [
                'headers' => $headers,
                'query' => $params,
                'body' => $body,
            ]);

            $data = json_decode($response->getBody(), true);

            if ($data['status'] >= 300) {
                throw new ShipwireConnectionException($data['message'], $data['status']);
            }
            return $onlyResource?$data['resource']:$data;
        } catch (RequestException $e) {
            if ($responseBody = $e->getResponse()->getBody()) {
                $data = json_decode($responseBody, true);

                switch ($data['status']) {
                    case 401:
                        throw new InvalidAuthorizationException($data['message'], $data['status']);
                        break;
                    case 400:
                        throw new InvalidRequestException($data['message'], $data['status']);
                        break;
                }
                throw new ShipwireConnectionException($data['message'], $data['status']);
            }
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * Gets the endpoint URL based on
     * @return string
     */
    protected static function getEndpointUrl()
    {
        if (self::$environment == 'live') {
            return self::$baseUrl;
        }
        return self::$sandboxBaseUrl;
    }
}
