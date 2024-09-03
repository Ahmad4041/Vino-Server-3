<?php

class AddFundsCardWalletController
{
    private $localDbConnection;
    // private $logDbConnection;
    private $bankDbConnection;
    // private $dbConnection;
    private $paystack;

    public function __construct($bankid)
    {
        $this->localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        // $this->logDbConnection = new MobileLogController(Database::getConnection('log'));
        $this->bankDbConnection = new BankDbController(Database::getConnection($bankid));
        // $this->dbConnection = Database::getConnection($bankid);
        $this->paystack = new PayStackController();
    }

    public function cardWalletAddFunds($username, $request, $bankid)
    {
        $bankName = $this->localDbConnection->getBankName($bankid);
        return $this->addFunds($request, $username, $bankid, $bankName['bankName']);
    }

    public function addFunds($request, $username, $bankCode, $bankName)
    {
        $customerDetails = $this->bankDbConnection->getCustomerDetails($username, $request['accountNo']);
        if (!$customerDetails) {
            return $this->createResponse(404, 'Customer not Found!');
        }

        $chargeResult = $this->chargeFromCard($request, $username, $customerDetails, $bankCode, $bankName);

        if ($chargeResult['status'] === 'ACCEPTED') {
            $cardVault = $this->bankDbConnection->createAddMoneyRecord($customerDetails, $request, $chargeResult, $bankCode);
            return $this->createResponse(200, 'Successful', $cardVault);
        } else {
            return $this->createResponse(404, 'Error');
        }
    }

    private function chargeFromCard($request, $username, $customerDetails, $bankCode, $bankName)
    {
        $cardVault = $this->bankDbConnection->getCardVault($request['cardNo'], $username);
        if (!$cardVault) {
            return 'Card does not exist';
        }

        $amounts = $this->calculateAmounts($request['amount']);
        $chargeRequest = $this->prepareChargeRequest($cardVault, $amounts['totalAmount'], $customerDetails['userAcctId'], $bankCode, $bankName);

        $res = $this->processCharge($chargeRequest, $cardVault);

        return array_merge(['status' => 'ACCEPTED'], $amounts);
    }

    private function calculateAmounts($requestedAmount)
    {
        $preGeneratedFee = $this->calculatePreGeneratedFee($requestedAmount);
        $appFee = $this->calculateAppFee($preGeneratedFee);
        $totalAmount = $requestedAmount + $preGeneratedFee + $appFee;

        return [
            'totalAmount' => $totalAmount,
            'appFee' => $appFee,
            'preGeneratedFee' => $preGeneratedFee,
            'merchantFee' => 0,
        ];
    }

    private function prepareChargeRequest($cardVault, $totalAmount, $userAcctId, $bankCode, $bankName)
    {
        if ($cardVault['AuthCode']) {
            return [
                'authorization_code' => $cardVault['AuthCode'],
                'email' => 'payments@viralcomputers.com',
                'amount' => $totalAmount * 100
            ];
        } else {
            return [
                'card' => $this->prepareCardData($cardVault),
                'email' => 'payments@viralcomputers.com',
                'amount' => $totalAmount * 100,
                'metadata' => [
                    'custom_fields' => [
                        ['value' => $userAcctId, 'display_name' => "User Account ID", 'variable_name' => "user_account_id"],
                        ['value' => $bankCode, 'display_name' => "Bank Code", 'variable_name' => "bank_code"],
                        ['value' => $bankName, 'display_name' => "Bank Name", 'variable_name' => "bank_name"]
                    ]
                ]
            ];
        }
    }

    private function processCharge($chargeRequest, $cardVault)
    {
        if (isset($chargeRequest['authorization_code'])) {
            return $this->paystack->chargeFromCard('/transaction/charge_authorization', $chargeRequest);
        } else {
            return $this->paystack->charge($cardVault['Username'], $chargeRequest['email'], $chargeRequest['metadata']['custom_fields'][0]['value'], $chargeRequest['metadata']['custom_fields'][1]['value'], $chargeRequest['metadata']['custom_fields'][2]['value'], $chargeRequest['card'], $chargeRequest['amount']);
        }
    }

    private function prepareCardData($cardVault)
    {
        return [
            'id' => $cardVault['Sno'],
            'authCode' => $cardVault['AuthCode'],
            'cardType' => $cardVault['CardType'],
            'cardNo' => $cardVault['CardNo'],
            'expMonth' => $cardVault['CardExpMonth'],
            'expYear' => $cardVault['CardExpYear'],
            'bank' => $cardVault['CardBank'],
            'channel' => $cardVault['CardChannel'],
            'signature' => $cardVault['CardSignature'],
            'reusable' => strtolower($cardVault['Active']) === "active",
            'countryCode' => $cardVault['CountryCode'],
            'accountName' => $cardVault['CardName'],
            'cvv' => $cardVault['CardCVV'],
            'reference' => $cardVault['TransID'],
            'status' => $cardVault['status'],
            'pin' => $cardVault['pin'],
            'otp' => $cardVault['otp'],
            'phone' => $cardVault['phone'],
            'url' => $cardVault['url'],
        ];
    }

    public function calculatePreGeneratedFee($requestAmount)
    {
        if ($requestAmount > 2500) {
            return min(2000, $this->calculateFee($requestAmount));
        } else {
            return round($requestAmount * 0.03);
        }
    }

    private function calculateFee($requestAmount)
    {
        return round(($requestAmount * 0.015) + 100);
    }

    public function calculateAppFee($preGeneratedFee)
    {
        return round($preGeneratedFee * 0.2);
    }

    private function createResponse($code, $message, $data = '')
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
    }
}
