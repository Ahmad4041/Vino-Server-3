<?php

class PayStackController
{
    private $client;
    private $baseUrl;
    private $token;

    public function __construct()
    {
        $this->client = new GuzzleHttp\Client(['verify' => false]);
        $this->baseUrl = getenv('PAYSTACK_IS_LIVE') ?
            getenv('PAYSTACK_LIVE') :
            getenv('PAYSTACK_SANDBOX');
        $this->token = getenv('PAYSTACK_IS_LIVE') ?
            'sk_live_d31b39c55050c62c6c52e71b2636108c1c963271' :
            'sk_test_your_test_key_here';
    }

    public function charge($username, $email, $accountNo, $bankCode, $bankName, $card, $amount)
    {
        $url = '/charge';
        $request = [];

        switch (strtoupper($card['status'])) {
            case 'SEND_OTP':
                $request = [
                    "otp" => $card['otp'],
                    "reference" => $card['reference']
                ];
                $url = '/charge/submit_otp';
                break;
            case 'SEND_PIN':
                $request = [
                    "pin" => $card['pin'],
                    "reference" => $card['reference']
                ];
                $url = '/charge/submit_pin';
                break;
            case 'SEND_PHONE_NO':
                $request = [
                    "otp" => $card['phone'],
                    "reference" => $card['reference']
                ];
                $url = '/charge/submit_phone';
                break;
            case 'OPEN_URL':
                $url = '/charge/' . $card['reference'];
                break;
            default:
                $request = $this->prepareDefaultChargeRequest($username, $email, $accountNo, $bankCode, $bankName, $card, $amount);
                break;
        }

        return $this->chargeFromCard($url, $request);
    }

    private function prepareDefaultChargeRequest($username, $email, $accountNo, $bankCode, $bankName, $card, $amount)
    {
        $customData = [
            ['display_name' => 'Username', 'variable_name' => 'user_name', 'value' => $username],
            ['display_name' => 'Account No', 'variable_name' => 'account_no', 'value' => $accountNo],
            ['display_name' => 'Bank Code', 'variable_name' => 'bank_code', 'value' => $bankCode],
            ['display_name' => 'Bank Name', 'variable_name' => 'bank_name', 'value' => $bankName],
        ];

        return [
            'email' => $email,
            'pin' => $card['pin'],
            'metadata' => ['custom_fields' => $customData],
            'card' => [
                'cvv' => $card['cvv'],
                'expiry_month' => $card['expMonth'],
                'expiry_year' => $card['expYear'],
                'number' => $card['cardNo'],
            ],
            'amount' => $amount,
        ];
    }

    public function chargeFromCard($url, $request = null)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];

        try {
            if ($request !== null) {
                $response = $this->client->post($this->baseUrl . $url, [
                    'headers' => $headers,
                    'json' => $request,
                ]);
            } else {
                $response = $this->client->get($this->baseUrl . $url, [
                    'headers' => $headers,
                ]);
            }

            return json_decode($response->getBody(), true);
        } catch (Exception $e) {
            return ['error' => 'API request exception: ' . $e->getMessage()];
        }
    }

    public function calculatePreGeneratedFee($requestAmount)
    {
        if ($requestAmount > 2500) {
            $merchantFee = min(($requestAmount * 0.015) + 100, 2000);
        } else {
            $merchantFee = $requestAmount * 0.03;
        }
        return round($merchantFee);
    }

    public function calculateAppFee($merchantFee)
    {
        return round($merchantFee * 0.2);
    }
}
