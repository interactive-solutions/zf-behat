<?php
/**
 * @author    Antoine Hedgecock <antoine.hedgecock@gmail.com>
 *
 * @copyright Interactive Solutions AB
 */

namespace InteractiveSolutions\ZfBehat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

class ApiClient
{
    /**
     * @var Response
     */
    public $lastResponse;

    /**
     * @var string
     */
    public $lastResponseBody;

    /**
     * @var array|null
     */
    public $lastRequestBody;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Set a header
     *
     * @param string $key
     * @param string $value
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    /**
     * Remove a specific header if it exists
     *
     * @param string $key
     *
     * @return void
     */
    public function removeHeader($key)
    {
        if (isset($this->headers[$key])) {
            unset($this->headers[$key]);
        }
    }

    /**
     * Remove all the set headers
     *
     * @return void
     */
    public function resetHeaders()
    {
        $this->headers = [];
    }

    /**
     * Send a post request using form-data
     *
     * @param $uri
     * @param $formData
     */
    public function postWithFormData($uri, $formData)
    {
        $this->lastRequestBody = null;

        try {

            $this->lastResponse = $this->client->post($uri, [
                'headers'     => $this->headers,
                'form_params' => $formData,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    /**
     * Send a post request
     *
     * @param string $uri
     * @param array  $body
     * @param array  $params
     *
     * @return void
     */
    public function post($uri, array $body = [], array $params = [])
    {
        $this->lastRequestBody = $body;

        try {

            $this->lastResponse = $this->client->post($uri, [
                'headers' => $this->headers,
                'json'    => $body,
                'query'   => $params,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    /**
     * @param string $uri
     * @param array  $body
     * @param array  $params
     *
     * @return void
     */
    public function upload($uri, array $body = [], array $params = [])
    {
        $this->lastRequestBody = $body;

        try {

            $this->lastResponse = $this->client->post($uri, [
                'query'       => $params,
                'multipart'   => $body,
                'headers'     => $this->headers,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    /**
     * Retrieve a uri
     *
     * @param string $uri
     * @param array  $params
     *
     * @return void
     */
    public function get($uri, array $params = [])
    {
        $this->lastRequestBody = null;

        try {

            $this->lastResponse = $this->client->get($uri, [
                'headers' => $this->headers,
                'query'   => $params,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    /**
     * @param string $uri
     * @param array  $body
     */
    public function put($uri, array $body)
    {
        $this->lastRequestBody = $body;

        try {

            $this->lastResponse = $this->client->put($uri, [
                'json'    => $body,
                'headers' => $this->headers,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    public function patch($uri, array $body)
    {
        $this->lastRequestBody = $body;

        try {

            $this->lastResponse = $this->client->patch($uri, [
                'json'    => $body,
                'headers' => $this->headers,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }

    /**
     * @param string $uri
     */
    public function delete($uri)
    {
        $this->lastRequestBody = null;

        try {

            $this->lastResponse = $this->client->delete($uri, [
                'headers' => $this->headers,
            ]);

        } catch (RequestException $e) {
            $this->lastResponse = $e->getResponse();
        } finally {
            $this->lastResponseBody = $this->lastResponse->getBody()->getContents();
        }
    }
}
