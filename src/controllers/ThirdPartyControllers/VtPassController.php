<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

class VTPassController
{
    private $client;
    private $liveUrl;
    private $sandboxUrl;
    private $api_key;
    private $secret;
    private $public_key;
    private $islive;

    public function __construct()
    {
        $this->client = new Client(['verify' => false]);
        $this->liveUrl = 'https://api-service.vtpass.com/api';
        $this->sandboxUrl = 'https://sandbox.vtpass.com/api';
        $this->islive = true; // Set this based on your environment

        if ($this->islive) {
            $this->api_key = 'fd84a73091da5ec5256b5dc77ebe2ece';
            $this->public_key = 'PK_1107607e75f83b1738940360987e1886eef3e2d97fd';
            $this->secret = 'SK_6347750fd12069a638bb5a323520f47f0a61f013fd1';
        } else {
            $this->api_key = '99356b3480c28c623d679ae2c9e97aae';
            $this->public_key = 'sandbox_pk_1bd7053e523c183edd5fb96492b0bb92d2ccce0';
            $this->secret = 'SK_25758ce36e7925bde93b47deae22281d7410e44bdcf';
        }
    }

    private function makeRequest($method, $endpoint, $data = [])
    {
        $baseUrl = $this->islive ? $this->liveUrl : $this->sandboxUrl;
        try {
            $response = $this->client->request($method, $baseUrl . $endpoint, [
                'headers' => [
                    'api-key' => $this->api_key,
                    'secret-key' => $this->secret,
                    'public-key' => $this->public_key,
                ],
                'json' => $data,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return ['error' => 'API request exception: ' . $e->getMessage()];
        }
    }

    public function getAirtimeServices($serviceID)
    {
        $response = $this->getServiceByCategory($serviceID);
        return array_filter(array_map(function ($row) {
            return [
                "name" => $row['name'],
                'code' =>  $row['serviceID'],
                'minimium_amount' =>  $row['minimium_amount']
            ];
        }, $response));
    }

    public function buyAirtime($request)
    {
        $data = [
            'request_id' => $request->request_id ?? $this->generateRequestID(),
            'serviceID' => $request->serviceID,
            'amount' => $request->amount,
            'phone' => $request->phone
        ];

        return $this->makeRequest('POST', '/pay', $data);
    }

    public function buyAirtimeLive($serviceID, $phone, $amount)
    {
        $data = [
            'request_id' => $this->generateRequestID(),
            'serviceID' => $serviceID,
            'amount' => $amount,
            'phone' => $phone
        ];

        return $this->makeRequest('POST', '/pay', $data);
    }

    public function getService()
    {
        return $this->makeRequest('GET', '/service-categories');
    }

    public function getServiceByCategory($serviceID)
    {
        return $this->makeRequest('GET', "/services?identifier={$serviceID}");
    }

    public function getServiceByVariation($serviceID)
    {
        $responseData = $this->makeRequest('GET', "/service-variations?serviceID={$serviceID}");

        if (isset($responseData['content']['varations'])) {
            return array_filter(array_map(function ($row) use ($serviceID) {
                return [
                    'name' => $row['name'],
                    'price' => (float)$row['variation_amount'],
                    'code' => (strpos($serviceID, 'Electric') !== false) ? $serviceID : $row['variation_code']
                ];
            }, $responseData['content']['varations']));
        }

        return [];
    }

    public function buyData($request)
    {
        $data = [
            'request_id' => $request->request_id ?? $this->generateRequestID(),
            'serviceID' => $request->serviceID,
            'billersCode' => $request->billersCode,
            'variation_code' => $request->variation_code,
            'amount' => $request->amount,
            'phone' => $request->phone
        ];

        return $this->makeRequest('POST', '/pay', $data);
    }

    public function buyCable($request)
    {
        $data = [
            'request_id' => $request->request_id ?? $this->generateRequestID(),
            'serviceID' => $request->serviceID,
            'billersCode' => $request->billersCode,
            'variation_code' => $request->variation_code,
            'amount' => $request->amount,
            'phone' => $request->phone,
            'subscription_type' => 'change'
        ];

        return $this->makeRequest('POST', '/pay', $data);
    }

    public function Query($request)
    {
        $data = [
            'request_id' => $request->request_id ?? $this->generateRequestID()
        ];

        return $this->makeRequest('POST', '/requery', $data);
    }

    public function verifyMeterNumber($request)
    {
        $data = [
            'serviceID' => $request->serviceID,
            'billersCode' => $request->billersCode,
            'type' => $request->variation_code
        ];

        return $this->makeRequest('POST', '/merchant-verify', $data);
    }

    public function buyElectricity($request)
    {
        $data = [
            'request_id' => $request->request_id ?? $this->generateRequestID(),
            'serviceID' => $request->serviceID,
            'billersCode' => $request->billersCode,
            'variation_code' => $request->variation_code,
            'amount' => $request->amount,
            'phone' => $request->phone
        ];

        return $this->makeRequest('POST', '/pay', $data);
    }

    private function generateRequestID()
    {
        return uniqid();
    }
}
