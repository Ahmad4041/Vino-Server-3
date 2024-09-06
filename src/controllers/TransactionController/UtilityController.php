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
                $logData['status'] = 'Success';
                $logData['note'] = $postUtility['data']['description'];


                $this->logDbConnection->transactionLogDb($logData);
                // $this->localDbConnection->transactionLog($transactionLogData);

                return $response;
            } else {
                if (is_array($postUtility['message'])) {
                    $responseMessage = $postUtility['message'][0] ?? '';
                    $logNote = $postUtility['message'][1] ?? '';
                } else {
                    $responseMessage = $postUtility['message'];
                    $logNote = $postUtility['message'];
                }

                $response = [
                    'code' => 200,
                    'dcode' => $postUtility['code'],
                    'message' => $responseMessage,
                    'data' => $postUtility['data'],
                ];

                $logData['response'] = json_encode($response);
                $logData['status'] = 'Error';
                $logData['note'] = $logNote;

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
        // var_dump($validateUser);
        if ($validateUser['code'] == 200) {
            $serviceFee = $this->bankDbConnection->getServiceFee($request['categoryCode']);

            $totalSellCost = $serviceFee['priceSell'] + $request['price'];

            if ($validateUser['data']['BalC1'] < $totalSellCost) {
                return [
                    'code' =>  ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[0],
                    'message' =>  [ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1], ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1]],
                    'stage' => "fee",
                    'data' => ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1],
                ];
            }

            $requestId = generateRequestId();
            $vtPassResponse = $this->callVtPass($request, $validateUser['data']['Telephone'], $bankid, $requestId);
            if ($vtPassResponse['code'] == '000') {
                $purcahse_code = ($vtPassResponse['purchased_code'] === '') ? '' : $vtPassResponse['token'];
                $note = 'Utility payment ' . $request['categoryCode'] . '. ' . $purcahse_code;
                $debitRequest = $this->coreBankConnection->debitNew2($request['srcAccount'], $bankid, number_format((float)$request['price'], 2, '.', ''), number_format((float)$serviceFee['priceSell'], 2, '.', ''), $note);

                $requestId = $debitRequest['requestId'];
                $status = 'SUCCESS';
                if ($debitRequest['status'] == 200) {
                    $totalCost = $serviceFee['priceCost'] + $request['price'];
                    $this->bankDbConnection->balanceCheck($totalCost, 'VINO');
                    $description = 'Client Debit has been done Successfully of amount ' . $totalSellCost . ' from ' . $request['srcAccount'] . '|' . $purcahse_code;
                    $this->localDbConnection->debitDataInsert($requestId, $request['srcAccount'], $bankid,  $request['price'], number_format((float)$serviceFee['priceSell'], 2, '.', ''), $description, $status, $note);
                    return [
                        'code' => 200,
                        'message' => 'Utility transaction has been done Successfully',
                        'data' => [
                            'status' => $status,
                            'reference' => (array_key_exists("purchased_code", $vtPassResponse)) ? $vtPassResponse['purchased_code'] : '',
                            'description' =>  $description
                        ],
                    ];
                }
                $description = 'Client Debit failure of amount ' . $totalSellCost . ' from ' . $request['srcAccount'];
                $this->localDbConnection->debitDataInsert($requestId, $request['srcAccount'], $bankid, $request['price'], $serviceFee['priceSell'], $description, $status, $note);
                return [
                    'code' =>  203,
                    'message' => ['Bank TSS: Issuer or Switch Inoperative', $description],
                    'data' => ErrorCodes::$FAIL_TRANSACTION[1],
                ];
            } else {
                // Check if 'response_description' is an array and extract the 'desc' value if available
                // $desc = is_array($vtPassResponse["response_description"]) && isset($vtPassResponse["response_description"])
                //     ? $vtPassResponse["response_description"]
                //     : '';

                    // var_dump($desc);
                    // var_dump($vtPassResponse['response_description']);
                // Adjust the $description line to include the extracted 'desc' value
                $description = 'VTpass transaction error of amount ' . $totalSellCost . ' from ' . $request['srcAccount'] . ', VTPass API Issue !! ' ;

                // $description = 'VTpass transaction error of amount ' . $totalSellCost . ' from ' . $request['srcAccount'] . ', VTPass API Issue !!' . $vtPassResponse["response_description"];
                $this->localDbConnection->debitDataInsert('', $request['srcAccount'], $bankid, $request['price'], $serviceFee['priceSell'], $description, 'Error', $note = '');
                return [
                    'code' =>  203,
                    'message' => ['Switch Not Responding', $description],
                    'data' => 'VTPass API Issue !!' .   ' Response Code: ' . $vtPassResponse['code'],
                ];
            }
        }
        return $validateUser;
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
                
                if ($verifyMeterNo['code'] === '000') {
                    if (isset($verifyMeterNo['content']['WrongBillersCode']) && $verifyMeterNo['content']['WrongBillersCode'] === true) {
                        return [
                            'status' => 'error',
                            'code' => 403,
                            'message' => $verifyMeterNo['content']['error'] ?? 'Invalid meter number',
                            'data' => $verifyMeterNo
                        ];
                    }
                    
                    if (isset($verifyMeterNo['content']['Can_Vend']) && $verifyMeterNo['content']['Can_Vend'] === 'yes') {
                        return $vtPassConnection->buyElectricity($requestData);
                    } else {
                        return [
                            'status' => 'error',
                            'code' => 403,
                            'message' => 'Cannot vend for this meter at the moment',
                            'data' => $verifyMeterNo
                        ];
                    }
                } else {
                    return [
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'Meter verification failed',
                        'data' => $verifyMeterNo
                    ];
                }
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
