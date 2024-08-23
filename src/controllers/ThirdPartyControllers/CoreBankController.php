<?php

class CoreBankController
{
    private $client;
    private $baseUrl;
    private $username;
    private $authToken;

    public function __construct()
    {
        $this->baseUrl = 'http://vino.viralcomputers.com:8056/Viral-GateWay';
        $this->username = 'testdrive@rmb.com';
        $this->authToken = 'Bearer eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiJlOTAyN2UwMWQ0M2U1ZGFkM2MzNzE2MDBkMTc0NTEwM2M0MjFjM2UwZjg1YjdhNjRjYzQwYzVjMmIxZWNiN2Y4MGJlNGRkZjQzNWQ2MTk0YTMxMTdhYTkwOWJmNGYwMDIzNDg3YmU0MThmYzVhODNkOTBkY2IyMGIxMTBmY2Y1Y2IwMDc4ZTI0YmE0YTBlZWM2ZjAyNTU4MTAwMTcyMWU5MjcwYzFkMjlkZmIzMWNjYTFjOTk1MDE2MzQxYTNlZTM3MDBlOTFiNjU5MTIzM2JiNGExYjc5ODg3NTRmYzAyYjc2NGFjYzA0YmRiYzFlMDdiYWZmNTMzNzEzMjM2ZTlkMzU4OTg5NTJjOWFmODJiZWNjYmQzY2IwZWRlNDM2YzI3NWYzMTZmN2VmZDI2NDQ1MmU1MTAxOWZiZDcxYjRmMGI1NGIwY2FhZDZhMzY2OTU2MjEwYzZlY2E4ZjJiZTZjIiwiaXNzIjoiIiwiaWF0IjoxNjk5OTY4MDc4LCJleHAiOjE3Nzc3MjgwNzh9.bznX-W7DfgmsaV8WTaa-300c16DEebGps7bw3PBWKpuHptTC4Jzot3Vp_8x5D-YplR7mA9aR1bbOa0dd7TLxYA';
    }

    function generateRequestId()
    {
        $requestId = '';
        for ($i = 0; $i < 20; $i++) {
            $requestId .= mt_rand(0, 9);
        }
    
        return $requestId;
    }
    

    private function makeRequest($method, $path, $data = [])
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Content-Type: application/json',
            'CLIENT_AUTHORIZATION: ' . $this->authToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'statusCode' => $statusCode,
            'response' => json_decode($response, true)
        ];
    }

    public function fundsTransferInternal($srcAcct, $srcBankCode, $destAcct, $destBankCode, $amount, $fee, $description)
    {
        $requestId = $this->generateRequestId();
        $data = [
            'requestId' => $requestId,
            'srcAccount' => $srcAcct,
            'bankCode' => $srcBankCode,
            'destBankCode' => $destBankCode,
            'destAccount' => $destAcct,
            'description' => $description,
            'fee' => $fee,
            'amount' => $amount,
            'token' => '0000'
        ];

        $result = $this->makeRequest('POST', '/api/v2/do-fundstransfer', $data);

        return [
            'code' => 200,
            'status' => $result['statusCode'],
            'requestId' => $requestId,
            'message' => $result['response']['responseDesc'] ?? 'No response description',
        ];
    }

    public function debitNew2($srcAcct, $srcBankCode, $amount, $fee, $description)
    {
        $requestId = $this->generateRequestId();
        $data = [
            'srcAccount' => $srcAcct,
            'srcBankCode' => $srcBankCode,
            'requestId' => $requestId,
            'description' => $description,
            'fee' => $fee,
            'amount' => $amount
        ];

        $result = $this->makeRequest('POST', '/api/v2/debit', $data);

        return [
            'code' => 200,
            'status' => $result['statusCode'],
            'requestId' => $requestId,
            'message' => $result['response']['responseDesc'] ?? 'No response description',
        ];
    }

    public function doReversal2($previousRequestId)
    {
        $requestId = $this->generateRequestId();
        $data = [
            'requestId' => $requestId,
            'ORequestId' => $previousRequestId
        ];

        $result = $this->makeRequest('POST', '/api/v2/do-reversal', $data);

        return [
            'code' => 200,
            'status' => $result['statusCode'],
            'requestId' => $requestId,
            'message' => $result['response']['responseDesc'] ?? 'No response description',
        ];
    }
}
