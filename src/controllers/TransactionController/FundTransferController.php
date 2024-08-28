<?php

class FundTransferController
{
    private $localDbConnection;
    private $logDbConnection;
    private $bankDbConnection;
    private $coreBankConnection;
    private $charmsApi;
    private $dbConnection;

    public function __construct($bankid)
    {
        $this->localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $this->logDbConnection = new MobileLogController(Database::getConnection('log'));
        $this->bankDbConnection = new BankDbController(Database::getConnection($bankid));
        $this->coreBankConnection = new CoreBankController();
        $this->charmsApi = new CharmsAPI();
        $this->dbConnection = Database::getConnection($bankid);
    }

    public function fundTransferLogic($bankid, $user, $request, $logData)
    {
        try {
            $transfer = $this->mobileTransferNew($bankid, $user['username'], $request);

            if ($transfer['code'] == 200) {
                $data = [
                    'path' => null,
                    'error' => null,
                ];
                $response = [
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => $data,
                    'code' => 200,
                ];

                $logData['response'] = json_encode($response);
                $logData['status'] = 'Success';
                $logData['note'] = $transfer['message'];


                $this->logDbConnection->transactionLogDb($logData);

                return $response;
            } else {
                $response = [
                    'code' => 403,
                    'dcode' => $transfer['dcode'],
                    'message' => $transfer['message'],
                    'data' => $transfer['data'],
                ];

                $logData['response'] = json_encode($response);
                $logData['status'] = 'Error';
                $logData['note'] = $transfer['message'];

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

    function mobileTransferNew($request, $username, $bankId)
    {
        // Assuming these are passed in the $request array
        $sourceAccount = $request['sourceAccount'];
        $beneficiaryAccountNo = $request['beneficiaryAccountNo'];
        $beneficiaryName = $request['beneficiaryName'];
        $note = $request['note'];
        $saveBeneficiary = $request['saveBeneficiary'];
        $amount = $request['amount'];
        $beneficiaryBankCode = $request['beneficiaryBankCode'];

        // Database connection
        // You'll need to implement your own database connection method
        $db = $this->dbConnection;
        // Get Customer by sourceAccount
        $stmt = $db->prepare("SELECT * FROM tblcustomer WHERE Accountid = ?");
        $stmt->execute([$sourceAccount]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            return [
                'data' => 'Customer Not Found!',
                'code' => 403,
                'message' => ErrorCodes::$FAIL_TRANSACTION[1] . ', ' . 'Customer Not Found!',
                'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
            ];
        }

        // Check Customer Balance is sufficient
        if ($customer['BalC1'] < $amount) {
            return [
                'data' => 'Balance Not Enough',
                'code' => 403,
                'message' => ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1],
                'dcode' => ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[0]
            ];
        }

        $narration = "Transfer for " . $beneficiaryName . " (" . $beneficiaryAccountNo . "), " . $note;

        // CoreBankController equivalent
        $corebanking = new CoreBankController();

        if ($bankId == $beneficiaryBankCode) {
            // Internal Fund Transfer
            $stmt = $db->prepare("SELECT * FROM Fees WHERE Code = 'FEE_IFT'");
            $stmt->execute();
            $chargesIFT = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chargesIFT) {
                return [
                    'data' => 'Transaction Chargers Not Found',
                    'code' => 403,
                    'message' => ErrorCodes::$FAIL_TRANSACTION[1],
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }

            $fee = $chargesIFT['SellPrice'];

            $res = $corebanking->fundsTransferInternal($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, number_format((float)$amount, 2, '.', ''), number_format((float)$fee, 2, '.', ''), $narration);

            if ($res['status'] == 200) {
                if ($saveBeneficiary) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM tblMobileBeneficiaries WHERE AccountNo = ?");
                    $stmt->execute([$beneficiaryAccountNo]);
                    $exists = $stmt->fetchColumn();

                    if (!$exists) {
                        $stmt = $db->prepare("INSERT INTO tblMobileBeneficiaries (Name, AccountNo, BankCode, Username) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username]);
                        return [
                            'code' => 200,
                            'message' => 'Save Beneficery',
                            'data' => true,
                        ];
                    }
                }

                $description = 'Internal Fund Transfer has been done Successfully, on account ' . $beneficiaryAccountNo . '.';
                $this->localDbConnection->debitDataInsert($res['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $description, 'Success', $narration);

                return [
                    'code' => 200,
                    'message' => $description,
                    'data' => $res,
                    // 'customer_name' => $customer['Customername'],
                ];
            } else {
                $description = 'Bank TSS: Internal Fund Transfer Issue, on account ' . $beneficiaryAccountNo . '.';
                $this->localDbConnection->debitDataInsert($res['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

                return [
                    'data' => $res,
                    'code' => 403,
                    'message' => $description,
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }
        } else {
            // External Fund Transfer
            $stmt = $db->prepare("SELECT * FROM Fees WHERE Code = 'FEE_EFT'");
            $stmt->execute();
            $chargesEFT = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chargesEFT) {
                return [
                    'data' => 'Transaction Chargers Not Found',
                    'code' => 403,
                    'message' => ErrorCodes::$FAIL_TRANSACTION[1] . ', ' . 'Transaction Chargers Not Found',
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }

            $fee = $chargesEFT['SellPrice'];

            // Check prebalance
            $stmt = $db->prepare("SELECT TOP 1 1 FROM tblPrebalance");
            $stmt->execute();
            $preBalance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$preBalance) {
                return [
                    'data' => ErrorCodes::$FAIL_TRANSACTION[1],
                    'code' => 403,
                    'message' => 'Bank TSS',
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }

            if ($preBalance['ValBal'] < ($fee + $amount)) {
                return [
                    'data' => ErrorCodes::$FAIL_TRANSACTION[1],
                    'code' => 403,
                    'message' => 'Bank TSS',
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }

            // CharmsController equivalent
            $accountFind = $this->charmsApi;
            $account = $accountFind->findAccount($beneficiaryAccountNo, $beneficiaryBankCode);

            if ($account['responseCode'] == 200) {
                $debit = $corebanking->debitNew2($sourceAccount, $bankId, number_format((float)$amount, 2, '.', ''), number_format((float)$fee, 2, '.', ''), $narration);

                if ($debit['status'] == 200) {
                    $externaltransfer = $accountFind;
                    $requestId = generateRequestId();
                    $bankid = ['bankCode' => $bankId];
                    $bankdebitinfo = $this->localDbConnection->bankCodeCheck($bankid);

                    $status = $externaltransfer->doFundsTransfer(
                        $account['data']['responseData']['destinationinstitutioncode'],
                        $account['data']['responseData']['accountnumber'],
                        $account['data']['responseData']['accountname'],
                        $narration,
                        $amount,
                        $requestId,
                        $account['data']['responseData']['hashvalue'],
                        $bankdebitinfo['data']['debitbankname'],
                        $bankdebitinfo['data']['debitbanknumber']
                    );

                    if ($status['responseCode'] == '00' && $status['requestSuccessful'] == true) {
                        // Update pre-balance
                        $this->bankDbConnection->balanceCheck($amount + $chargesEFT['CostPrice'], 'VINO');

                        if ($saveBeneficiary) {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM tblMobileBeneficiaries WHERE AccountNo = ?");
                            $stmt->execute([$beneficiaryAccountNo]);
                            $exists = $stmt->fetchColumn();

                            if (!$exists) {
                                $stmt = $db->prepare("INSERT INTO tblMobileBeneficiaries (Name, AccountNo, BankCode, Username) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username]);
                                return [
                                    'code' => 200,
                                    'message' => 'Save Beneficery',
                                    'data' => true,
                                ];
                            }
                        }

                        $description = 'External Fund Transfer has been done Successfully, on account ' . $beneficiaryAccountNo . '.';
                        $this->localDbConnection->debitDataInsert($requestId, $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Success', $narration);

                        return [
                            'code' => 200,
                            'message' => $description,
                            'data' => [],
                            // 'customer_name' => $customer['Customername'],
                        ];
                    } else {
                        $description = 'Switch Not Responding: External Fund Transfer Issue, on account ' . $beneficiaryAccountNo . '.';
                        $this->localDbConnection->debitDataInsert($requestId, $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

                        return [
                            'data' => $status,
                            'code' => 403,
                            'message' => $description,
                            'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                        ];
                    }
                } else {
                    $description = 'Bank TSS: Debit Error, on account ' . $beneficiaryAccountNo . '.';
                    $this->localDbConnection->debitDataInsert($debit['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

                    return [
                        'data' => $debit,
                        'code' => 403,
                        'message' => $description,
                        'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                    ];
                }
            } else {
                return [
                    'data' => $account['message'],
                    'code' => 403,
                    'message' => ErrorCodes::$FAIL_TRANSACTION[1] . ', Account NOT FOUND! ',
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0]
                ];
            }
        }
    }
}
