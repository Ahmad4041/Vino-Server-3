<?php

class FundTransferController
{
    private $localDbConnection;
    private $logDbConnection;
    private $bankDbConnection;
    private $coreBankConnection;
    private $charmsApi;

    public function __construct($bankid)
    {
        $this->localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $this->logDbConnection = new MobileLogController(Database::getConnection('log'));
        $this->bankDbConnection = new BankDbController(Database::getConnection($bankid));
        $this->coreBankConnection = new CoreBankController();
        $this->charmsApi = new CharmsAPI();
    }

    function fundTransferLogic($bankid, $user, $request)
    {
        try {
            $transactionLogData = $this->prepareTransactionLogData($bankid, $user, $request);

            $transfer = $this->mobileTransferNew($request, $user['username'], $bankid);

            return $this->handleTransferResponse($transfer, $request, $user, $transactionLogData);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    function mobileTransferNew($request, $username, $bankId)
    {
        $sourceAccount = $request['sourceAccount'];
        $beneficiaryAccountNo = $request['beneficiaryAccountNo'];
        $beneficiaryName = $request['beneficiaryName'];
        $note = $request['note'];
        $saveBeneficiary = $request['saveBeneficiary'];
        $amount = $request['amount'];
        $beneficiaryBankCode = $request['beneficiaryBankCode'];

        // Get Customer by sourceAccount
        $customer = $this->getCustomerByAccount($this->bankDbConnection, $sourceAccount);
        if (!$customer) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Customer Not Found!', 403);
        }

        // Check Customer Balance is sufficient
        if ($customer['BalC1'] < $amount) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH, 'Balance Not Enough', 403);
        }

        $narration = "Transfer for $beneficiaryName ($beneficiaryAccountNo), $note";

        if ($bankId == $beneficiaryBankCode) {
            return $this->handleInternalTransfer($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, $amount, $narration, $saveBeneficiary, $username, $customer, $beneficiaryName);
        } else {
            return $this->handleExternalTransfer($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, $amount, $narration, $saveBeneficiary, $username, $customer, $beneficiaryName);
        }
    }

    function getCustomerByAccount($dbConnection, $accountId)
    {
        $stmt = $dbConnection->prepare("SELECT * FROM tblcustomer WHERE Accountid = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function createErrorResponse($errorCode, $message, $httpCode)
    {
        return [
            'data' => $message,
            'code' => $httpCode,
            'message' => $errorCode[1] . ', ' . $message,
            'dcode' => $errorCode[0]
        ];
    }

    function handleInternalTransfer($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, $amount, $narration, $saveBeneficiary, $username, $customer, $beneficiaryName)
    {
        $chargesIFT = $this->getFees($this->bankDbConnection, 'FEE_IFT');
        if (!$chargesIFT) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Transaction Charges Not Found', 403);
        }

        $fee = $chargesIFT['SellPrice'];
        $res = $this->coreBankConnection->fundsTransferInternal($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, number_format((float)$amount, 2, '.', ''), number_format((float)$fee, 2, '.', ''), $narration);

        if ($res['status'] == 200) {
            if ($saveBeneficiary) {
                $this->saveBeneficiary($this->bankDbConnection, $beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username);
            }

            $description = "Internal Fund Transfer has been done Successfully, on account $beneficiaryAccountNo.";
            $this->localDbConnection->debitDataInsert($res['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $description, 'Success', $narration);

            $this->logTransaction($res['requestId'], $sourceAccount, $username, 'Internal Transfer', $res, $amount, 'Success', $customer['Customername'], $narration);

            return [
                'code' => 200,
                'message' => 'Transaction Successful',
                'data' => $res,
                'customer_name' => $customer['Customername'],
            ];
        } else {
            $description = "Internal Fund Transfer Issue, on account $beneficiaryAccountNo.";
            $this->localDbConnection->debitDataInsert($res['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

            $this->logTransaction($res['requestId'], $sourceAccount, $username, 'Internal Transfer', $res, $amount, 'Error', $customer['Customername'], $narration);

            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Issuer or Switch Inoperative', 403);
        }
    }

    function handleExternalTransfer($sourceAccount, $bankId, $beneficiaryAccountNo, $beneficiaryBankCode, $amount, $narration, $saveBeneficiary, $username, $customer, $beneficiaryName)
    {
        $chargesEFT = $this->getFees($this->bankDbConnection, 'FEE_EFT');
        if (!$chargesEFT) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Transaction Charges Not Found', 403);
        }

        $fee = $chargesEFT['SellPrice'];
        $preBalance = $this->getPreBalance($this->bankDbConnection);
        if (is_null($preBalance) || $preBalance['ValBal'] < ($fee + $amount)) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS', 403);
        }

        $account = $this->charmsApi->findAccount($beneficiaryAccountNo, $beneficiaryBankCode);

        if ($account['responseCode'] == 200) {
            $debit = $this->coreBankConnection->debitNew2($sourceAccount, $bankId, number_format((float)$amount, 2, '.', ''), number_format((float)$fee, 2, '.', ''), $narration);

            if ($debit['status'] == 200) {
                $requestId = generateRequestId();
                $bankdebitinfo = $this->localDbConnection->bankCodeCheck($bankId);
                $status = $this->charmsApi->doFundsTransfer(
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
                    $this->updatePreBalance($this->bankDbConnection, $amount + $chargesEFT['CostPrice']);
                    if ($saveBeneficiary) {
                        $this->saveBeneficiary($this->bankDbConnection, $beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username);
                    }

                    $description = "External Fund Transfer has been done Successfully, on account $beneficiaryAccountNo.";
                    $this->localDbConnection->debitDataInsert($requestId, $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Success', $narration);

                    $this->logTransaction($requestId, $sourceAccount, $username, 'External Transfer', $status, $amount, 'Success', $customer['Customername'], $narration);

                    return [
                        'code' => 200,
                        'message' => 'Transaction Successful',
                        'data' => [],
                        'customer_name' => $customer['Customername'],
                    ];
                } else {
                    $description = "External Fund Transfer Issue, on account $beneficiaryAccountNo.";
                    $this->localDbConnection->debitDataInsert($requestId, $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

                    $this->logTransaction($requestId, $sourceAccount, $username, 'External Transfer', $status, $amount, 'Error', $customer['Customername'], $narration);

                    return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS Not Responding', 403);
                }
            } else {
                $description = "External Fund Transfer Issue, on account $beneficiaryAccountNo.";
                $this->localDbConnection->debitDataInsert($debit['requestId'], $sourceAccount, $bankId, $amount, number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $description, 'Error', $narration);

                return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS Not Responding', 403);
            }
        } else {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Invalid Destination Account', 403);
        }
    }

    function prepareTransactionLogData($bankid, $user, $request)
    {
        return [
            'bankId' => $bankid,
            'username' => $user['username'],
            'user_id' => $user['user_id'],
            'amount' => $request['amount'],
            'srcAccount' => $request['sourceAccount'],
            'beneficiaryAccountNo' => $request['beneficiaryAccountNo'],
            'beneficiaryBankCode' => $request['beneficiaryBankCode'],
            'beneficiaryName' => $request['beneficiaryName'],
            'note' => $request['note'],
            'action' => 'Fund Transfer',
            'status' => '',
            'timestamp' => date('Y-m-d H:i:s'),
            'request' => $request,
            'response' => ''
        ];
    }

    function handleTransferResponse($transfer, $request, $user, $transactionLogData)
    {
        $transactionLogData['response'] = json_encode($transfer);
        $transactionLogData['status'] = ($transfer['code'] == 200) ? 'Success' : 'Failed';

        $logResult = $this->logDbConnection->transactionLogDb($transactionLogData);

        return $transfer;
    }

    function handleException($e)
    {
        return [
            'code' => 500,
            'message' => 'Internal Server Error',
            'data' => $e->getMessage(),
        ];
    }

    function getFees($dbConnection, $feeCode)
    {
        $stmt = $dbConnection->prepare("SELECT * FROM tblMobileFees WHERE Code = ?");
        $stmt->execute([$feeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function getPreBalance($dbConnection)
    {
        $stmt = $dbConnection->query("SELECT ValBal FROM tblPrebalance");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function updatePreBalance($dbConnection, $amount)
    {
        $stmt = $dbConnection->prepare("UPDATE tblPrebalance SET ValBal = ValBal - ?");
        return $stmt->execute([$amount]);
    }

    function saveBeneficiary($dbConnection, $beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username)
    {
        $stmt = $dbConnection->prepare("INSERT INTO tblMobileBeneficiaries (Name, AccountNo, BankCode, Username) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username]);
    }


    function logTransaction($requestId, $srcAccount, $username, $action, $response, $amount, $status, $accountHolder, $note)
    {
        $logData = [
            'bankId' => $requestId,
            'srcAccount' => $srcAccount,
            'username' => $username,
            'action' => $action,
            'request' => [],
            'response' => $response,
            'amount' => $amount,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'account_holder' => $accountHolder,
            'note' => $note,
        ];

        $this->logDbConnection->transactionLogDb($logData);
    }
}
