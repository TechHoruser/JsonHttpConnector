<?php

namespace mortalswat\JsonHttpConnector;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

/**
 * Class OAuth2
 * @package Domain\JsonHttpConnector
 */
class OAuth2
{
    /** @var string */
    private $method;
    /** @var string */
    private $uri;
    /** @var array */
    private $headers;
    /** @var string */
    private $body;
    /** @var string|null */
    private $token;

    /**
     * OAuth2 constructor.
     * @param $method
     * @param $uri
     * @param $body
     * @param array $headers
     */
    public function __construct($method, $uri, $body, array $headers = [])
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @return null|string
     * @throws OAuth2Exception
     */
    public function getToken()
    {
        if ($this->token === null) {
            $this->refreshToken();
        }

        return $this->token;
    }

    /**
     * @return void
     */
    public function refreshToken()
    {
        $request = new Request(
            $this->method,
            $this->uri,
            $this->headers,
            $this->body
        );

        $client = new Client();

        try {
            $response = $client->send($request);
        } catch (GuzzleException $exception) {
            throw new OAuth2Exception('Problema de solicitud de nuevo token: "' . $exception->getMessage() . '"');
        }

        $arrayResponse = json_decode($response->getBody()->getContents(), true);
        if ($arrayResponse === null) {
            throw new OAuth2Exception('Error al decodificar json de autenticación');
        }

        $this->token = $arrayResponse['access_token'];
    }
}