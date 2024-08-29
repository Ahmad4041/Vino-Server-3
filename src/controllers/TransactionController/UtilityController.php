<?php

class UtilityController
{
    private $localDbConnection;
    private $logDbConnection;
    private $bankDbConnection;
    private $coreBankConnection;
    private $vtPassConnection;

    public function __construct($bankid)
    {
        $this->localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $this->logDbConnection = new MobileLogController(Database::getConnection('log'));
        $this->bankDbConnection = new BankDbController(Database::getConnection($bankid));
        $this->coreBankConnection = new CoreBankController();
        $this->vtPassConnection = new VTPassController();
    }

    public function purchaseUtilityServices($user, $bankid, $request, $logData)
    {
        try {

            $postUtility = $this->postUtilities($request, $user, $bankid);

            if ($postUtility['code'] == 200) {

                $response = [
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => $postUtility['data'],
                    'code' => 200,
                ];

                $logData['response'] = json_encode($response);
                $logData['status'] = 'SUCCESS';
                $logData['note'] = $postUtility['message'];


                $this->logDbConnection->transactionLogDb($logData);
                // $this->localDbConnection->transactionLog($transactionLogData);

                return $response;
            } else {
                $response = [
                    'code' => 403,
                    'dcode' => $postUtility['dcode'],
                    'message' => $postUtility['message'],
                    'data' => $postUtility['data'],
                ];

                $logData['response'] = json_encode($response);
                $logData['status'] = 'Error';
                $logData['note'] = $postUtility['message'];

                $this->logDbConnection->transactionLogDb($logData);

                return $response;
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function postUtilities($request, $user, $bankid)
    {
        $validateUser = $this->bankDbConnection->validateAccount($request['srcAccount'], $user['username']);
        if ($validateUser['code'] == 200) {
            $serviceFee = $this->bankDbConnection->getServiceFee($request['categoryCode']);

            $totalSellCost = $serviceFee['priceSell'] + $request['price'];

            if ($validateUser['data']['BalC1'] < $totalSellCost) {
                return [
                    'code' =>  ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[0],
                    'message' =>  ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1],
                    'stage' => "fee",
                    'data' => ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1],
                ];
            }

            $requestId = generateRequestId();
            $vtPassResponse = $this->callVtPass($request, $validateUser['data']['Telephone'], $bankid, $requestId);
            if ($vtPassResponse['code'] === '000') {
                $purcahse_code = ($vtPassResponse['purchased_code'] === '') ? '' : $vtPassResponse['token'];
                $note = 'Utility payment ' . $request['categoryCode'] . '. ' . $purcahse_code;
                $debitRequest = $this->coreBankConnection->debitNew2($request['srcAccount'], $bankid, number_format((float)$request['price'], 2, '.', ''), number_format((float)$serviceFee['priceSell'], 2, '.', ''), $note);
            }
        }
    }

    public function callVtPass($request, $phoneNo, $bankId, $requestId)
    {
        $categoryCode = strtolower($request['categoryCode']);
        $vtPassConnection = $this->vtPassConnection;

        $requestData = [
            'request_id' => $requestId,
            'serviceID' => $request['serviceProvider'],
            'billersCode' => $request['customerId'],
            'variation_code' => $request['packageCode'],
            'amount' => $request['price'],
            'phone' => $phoneNo,
        ];

        $buyUtils = null;

        $categoryActions = [
            'internet' => function () use ($vtPassConnection, $requestData) {
                return $vtPassConnection->buyData($requestData);
            },
            'electricity' => function () use ($vtPassConnection, $requestData) {
                $verifyMeterNo = $vtPassConnection->verifyMeterNumber($requestData);
                return ($verifyMeterNo['code'] === '000') ? $vtPassConnection->buyElectricity($requestData) : $verifyMeterNo;
            },
            'cable' => function () use ($vtPassConnection, $requestData) {
                $testMode = true; // This should be set based on your application's configuration
                $verifyMeterNo = $vtPassConnection->verifyMeterNumber($requestData);
                if ($verifyMeterNo['code'] === '000' && !$testMode) {
                    return $vtPassConnection->buyCable($requestData);
                }
                return $verifyMeterNo;
            }
        ];

        if (isset($categoryActions[$categoryCode])) {
            $buyUtils = $categoryActions[$categoryCode]();
        }

        return $buyUtils ?? 'Default';
    }
}
