<?php


class CharmsAPI
{
    private $client;
    private $baseUrl;
    private $baseUrl2;
    private $vtuBaseUrl;
    private $api_key;
    private $client_id;
    private $secret;
    private $principalid;
    private $merchant_no;
    private $merchant_name;

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client(['verify' => false]);
        $this->baseUrl = 'http://68.169.60.105:2022/api/bills';
        $this->baseUrl2 = 'http://68.169.60.38:8888';
        $this->vtuBaseUrl = 'https://vtumanager.com/api/v1';
        $this->api_key = 'p4phEtgQcHL3doDKPcq1uvGi3ya3VIXYRMxBKFALTgg';
        $this->client_id = '53a06b08-e3d7-41d4-928a-6e57eb2ab3a4';
        $this->secret = 'd8322f4f-2bf9-4722-809e-230e26c1eb59';
        $this->principalid = 'VIRALCOMPUTERS';
        $this->merchant_no = '9535686178';
        $this->merchant_name = 'VIRAL COMPUTERS';
    }

    private function makeGetRequest($url, $headers = [])
    {
        try {
            $response = $this->client->get($url, ['headers' => $headers]);
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return ['error' => 'API request exception: ' . $e->getMessage()];
        }
    }

    private function makePostRequest($url, $body, $headers = [])
    {
        try {
            $response = $this->client->post($url, ['json' => $body, 'headers' => $headers]);
            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return ['error' => 'API request exception: ' . $e->getMessage()];
        }
    }

    public function getService()
    {
        return $this->makeGetRequest($this->baseUrl . '/GetAllServices');
    }

    public function getServiceByID($serviceID)
    {
        return $this->makeGetRequest($this->baseUrl . '/getserviceByServiceID?serviceID=' . $serviceID);
    }

    public function getServiceByVariation($variation)
    {
        return $this->makeGetRequest($this->baseUrl . '/getservicevariation?variation=' . $variation);
    }

    public function getToken()
    {
        $response = $this->makePostRequest($this->vtuBaseUrl . '/account/login', [
            'principalId' => $this->principalid,
            'secret' => $this->secret
        ]);
        return $response['token'] ?? null;
    }

    public function getTelcos()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->getToken()];
        return $this->makeGetRequest($this->vtuBaseUrl . '/telcos', $headers);
    }

    public function buyTopup($amount, $phoneno, $telcoid, $checksum, $customerName)
    {
        $reference = '20200126';
        $channel = 'VINO';
        $path = '/topups/vend';

        $body = [
            'Amount' => $amount,
            'Reference' => $reference,
            'PhoneNumber' => $phoneno,
            'TelcoCode' => $telcoid,
            'Channel' => $channel,
            'CheckSum' => $checksum,
            'CustomerName' => $customerName,
        ];

        $headers = ['Authorization' => 'Bearer ' . $this->getToken()];
        return $this->makePostRequest($this->vtuBaseUrl . $path, $body, $headers);
    }

    public function findAccount($accountNo, $bankcode)
    {
        $body = [
            'accountnumber' => $accountNo,
            'destinationinstitutioncode' => $bankcode,
        ];

        $headers = [
            'x-api-key' => $this->api_key,
            'clientid' => $this->client_id
        ];

        $response = $this->makePostRequest($this->baseUrl2 . '/api/Banking/AccountLookUp', $body, $headers);

        if (isset($response['error'])) {
            return [
                'message' => "$accountNo Account Information Not Found | " . $response['error'],
                'responseCode' => 400,
                'data' => [],
            ];
        }

        return [
            'message' => "Successful",
            'responseCode' => 200,
            'data' => $response,
        ];
    }

    public function getAllBanks()
    {
        $headers = [
            'x-api-key' => $this->api_key,
            'clientid' => $this->client_id
        ];

        $response = $this->makeGetRequest($this->baseUrl2 . '/api/Banking/GetFinancialInstitutions', $headers);

        if (isset($response['error'])) {
            return [
                'message' => "Bank List Not Found | " . $response['error'],
                'code' => 400,
                'data' => [],
            ];
        }

        return [
            'message' => "Successful",
            'code' => 200,
            'data' => $response['responseData'] ?? [],
        ];
    }

    public function doFundsTransfer($destBankCode, $destAccountNo, $destAccountName, $narration, $amount, $requestid, $nameEnquireRef, $bankname, $banknumber)
    {
        $body = [
            'debitaccountname' => $bankname,
            'debitaccountnumber' => $banknumber,
            'destinationinstitutioncode' => $destBankCode,
            'beneficiaryaccountname' => $destAccountName,
            'beneficiaryaccountnumber' => $destAccountNo,
            'narration' => $narration,
            'amount' => $amount,
            'userReference' => $requestid,
            'nameenquiryref' => $nameEnquireRef,
        ];

        $headers = [
            'x-api-key' => $this->api_key,
            'clientid' => $this->client_id
        ];

        $response = $this->makePostRequest($this->baseUrl2 . '/api/Banking/FundsTransfer', $body, $headers);

        if (isset($response['error'])) {
            return [
                'message' => "$destAccountNo Transfer Failed.",
                'error' => $response['error'],
                'responseCode' => 400,
            ];
        }

        return $response;
    }

    public function makePayment($amount, $customerInfo)
    {
        $body = [
            'amount' => $amount,
            'customer' => $customerInfo,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . getenv('PAYMENT_GATEWAY_API_KEY')
        ];

        $response = $this->makePostRequest(getenv('PAYMENT_GATEWAY_ENDPOINT'), $body, $headers);
        return $response['message'] ?? 'Payment successful';
    }
}
