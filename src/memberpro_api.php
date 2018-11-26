<?php

namespace MemberproApi;

use Exception;
use SimpleXMLElement;

class Memberpro_Api
{
    /**
     * Order
     */
    protected $order;

    /**
     * @var string API Endpoint
     */
    protected $endpoint;

    /**
     * @var array Available API methods with HTTP Method
     */
    protected $availableMethods = [
        'API_AG_GET_CENIK' => 'GET',
        'API_AG_PRODEJ_FINISH' => 'POST',
        'API_AG_PRODEJ_GET_ITEMS' => 'GET',
        'API_AG_PRODEJ_NEW' => 'POST',
        'API_AG_PRODEJ_ITEM_INSERT' => 'POST',
        'API_AG_PRODEJ_GET_VOUCHERS' => 'POST',
    ];

    /**
     * @var array cURL Response headers
     */
    protected $responseHeaders = [];

    /**
     * @var
     */
    protected $responseStatusCode;

    /**
     * MemberproApi constructor.
     */
    public function __construct()
    {
        $this->endpoint = 'http://62.109.132.157:8097/Service_api.asmx';

        $this->getPriceList();
    }

    /**
     * @param $ch
     * @param $line
     * @return int
     */
    public function curlHeader($ch, $line)
    {
        if (preg_match('/^HTTP\/[1-2]\.[0-2]\s([0-9]{3})\s([a-zA-Z\s]+)$/', $line, $m)) {
            $this->responseStatusCode = $m[1];
            return strlen($line);
        }

        $ex = explode(':', $line, 2);

        if (isset($ex[0]) && isset($ex[1])) {
            $this->responseHeaders[$ex[0]] = trim($ex[1]);
        }

        return strlen($line);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        // check if method exists
        if (!isset($this->availableMethods[$name])) {
            throw new Exception('Non existent method called!');
        }

        // prepare data
        $data = '';
        if (isset($arguments[0])) {
            $data = [];
            foreach ($arguments[0] as $arg_name => $arg_value) {
                $data[] = $arg_name . '=' . $arg_value;
            }
            $data = implode('&', $data);
        }

        // prepare endpoint
        $url = $this->endpoint . '/' . $name;

        // init curl handle
        $ch = curl_init();

        // reset reposnse headers
        $this->responseStatusCode = null;
        $this->responseHeaders = [];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        // POST
        if ($this->availableMethods[$name] === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            // set http headers
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($data),
            ]);
        } // GET
        elseif ($this->availableMethods[$name] === 'GET') {
            if ($data) {
                $data = '?' . $data;
            }
            curl_setopt($ch, CURLOPT_URL, $url . $data);
        }

        // enable response headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, 'curlHeader'));

        // send request
        $result = curl_exec($ch);

        // check for errors
        if ($result === false) {
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        // HTTP Error
        if ($this->responseStatusCode !== '200') {
            throw new Exception($result);
        }

        if (strpos($this->responseHeaders['Content-Type'], 'text/xml') === 0) {
            $result = $this->parseXml($result);

            if (isset($result->MAPP_ERROR->CHYBA)) {
                throw new Exception($result->MAPP_ERROR->CHYBA);
            }

            if (!$result->{$name}) {
                throw new Exception('Unexpected result from API');
            }

            $result = $result->{$name};
        }

        // close curl handle
        curl_close($ch);

        return $result;
    }

    /**
     * @param $result
     * @return SimpleXMLElement
     */
    protected function parseXml($result)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        $ns = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('diffgr', $ns['diffgr']);

        $elements = $xml->xpath('//diffgr:diffgram/DocumentElement');
        $data = $elements[0];

        return $data;
    }

    /**
     * Get available items for sale.
     */
    public function getPriceList()
    {
        return $this->API_AG_GET_CENIK();
    }

    /**
     * Creates and returns new Order object.
     *
     * @param string $email
     * @return Order
     * @throws Exception
     */
    public function createNewOrder(string $email): Order
    {
        return new Order($this, $email);
    }

}