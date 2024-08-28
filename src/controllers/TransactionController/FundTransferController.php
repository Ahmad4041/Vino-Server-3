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

    public function fundTransferLogic($bankid, $user, $request)
    {
        try {
            $transactionLogData = $this->prepareTransactionLogData($bankid, $user, $request);
            $transfer = $this->mobileTransferNew($request, $user['username'], $bankid);
            return $this->handleTransferResponse($transfer, $request, $user, $transactionLogData);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function mobileTransferNew($request, $username, $bankId)
    {
        $customer = $this->getCustomerByAccount($request['sourceAccount']);
        if (!$customer) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Customer Not Found!', 403);
        }

        if ($customer['BalC1'] < $request['amount']) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH, 'Balance Not Enough', 403);
        }

        $narration = "Transfer for {$request['beneficiaryName']} ({$request['beneficiaryAccountNo']}), {$request['note']}";

        return ($bankId == $request['beneficiaryBankCode']) 
            ? $this->handleInternalTransfer($request, $username, $customer, $narration)
            : $this->handleExternalTransfer($request, $username, $customer, $narration);
    }

    private function handleInternalTransfer($request, $username, $customer, $narration)
    {
        $chargesIFT = $this->getFees('FEE_IFT');
        if (!$chargesIFT) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Transaction Charges Not Found', 403);
        }

        $fee = $chargesIFT['SellPrice'];
        $res = $this->coreBankConnection->fundsTransferInternal(
            $request['sourceAccount'],
            $request['bankId'],
            $request['beneficiaryAccountNo'],
            $request['beneficiaryBankCode'],
            number_format((float)$request['amount'], 2, '.', ''),
            number_format((float)$fee, 2, '.', ''),
            $narration
        );

        if ($res['status'] == 200) {
            if ($request['saveBeneficiary']) {
                $this->saveBeneficiary($request['beneficiaryName'], $request['beneficiaryAccountNo'], $request['beneficiaryBankCode'], $username);
            }

            $note = "Internal Fund Transfer successful to account {$request['beneficiaryAccountNo']}.";
            $this->localDbConnection->debitDataInsert($res['requestId'], $request['sourceAccount'], $request['bankId'], $request['amount'], number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $note, 'Success', $narration);
            $this->logTransaction($res['requestId'], $request['sourceAccount'], $username, 'Internal Transfer', $request, $res, $request['amount'], 'Success', $customer['Customername'], $narration, $note);

            return [
                'code' => 200,
                'message' => 'Transaction Successful',
                'data' => $res,
                'customer_name' => $customer['Customername'],
                'note' => $note
            ];
        } else {
            $note = "Internal Fund Transfer failed for account {$request['beneficiaryAccountNo']}.";
            $this->localDbConnection->debitDataInsert($res['requestId'], $request['sourceAccount'], $request['bankId'], $request['amount'], number_format((float)$chargesIFT['CostPrice'], 2, '.', ''), $note, 'Error', $narration);
            $this->logTransaction($res['requestId'], $request['sourceAccount'], $username, 'Internal Transfer', $request, $res, $request['amount'], 'Error', $customer['Customername'], $narration, $note);

            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Issuer or Switch Inoperative', 403);
        }
    }

    private function handleExternalTransfer($request, $username, $customer, $narration)
    {
        $chargesEFT = $this->getFees('FEE_EFT');
        if (!$chargesEFT) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Transaction Charges Not Found', 403);
        }

        $fee = $chargesEFT['SellPrice'];
        $preBalance = $this->getPreBalance();
        if (is_null($preBalance) || $preBalance['ValBal'] < ($fee + $request['amount'])) {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS', 403);
        }

        $account = $this->charmsApi->findAccount($request['beneficiaryAccountNo'], $request['beneficiaryBankCode']);

        if ($account['responseCode'] == 200) {
            $debit = $this->coreBankConnection->debitNew2(
                $request['sourceAccount'],
                $request['bankId'],
                number_format((float)$request['amount'], 2, '.', ''),
                number_format((float)$fee, 2, '.', ''),
                $narration
            );

            if ($debit['status'] == 200) {
                $requestId = generateRequestId();
                $bankdebitinfo = $this->localDbConnection->bankCodeCheck($request['bankId']);
                $status = $this->charmsApi->doFundsTransfer(
                    $account['data']['responseData']['destinationinstitutioncode'],
                    $account['data']['responseData']['accountnumber'],
                    $account['data']['responseData']['accountname'],
                    $narration,
                    $request['amount'],
                    $requestId,
                    $account['data']['responseData']['hashvalue'],
                    $bankdebitinfo['data']['debitbankname'],
                    $bankdebitinfo['data']['debitbanknumber']
                );

                if ($status['responseCode'] == '00' && $status['requestSuccessful'] == true) {
                    $this->updatePreBalance($request['amount'] + $chargesEFT['CostPrice']);
                    if ($request['saveBeneficiary']) {
                        $this->saveBeneficiary($request['beneficiaryName'], $request['beneficiaryAccountNo'], $request['beneficiaryBankCode'], $username);
                    }

                    $note = "External Fund Transfer successful to account {$request['beneficiaryAccountNo']}.";
                    $this->localDbConnection->debitDataInsert($requestId, $request['sourceAccount'], $request['bankId'], $request['amount'], number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $note, 'Success', $narration);
                    $this->logTransaction($requestId, $request['sourceAccount'], $username, 'External Transfer', $request, $status, $request['amount'], 'Success', $customer['Customername'], $narration, $note);

                    return [
                        'code' => 200,
                        'message' => 'Transaction Successful',
                        'data' => [],
                        'customer_name' => $customer['Customername'],
                        'note' => $note
                    ];
                } else {
                    $note = "External Fund Transfer failed for account {$request['beneficiaryAccountNo']}.";
                    $this->localDbConnection->debitDataInsert($requestId, $request['sourceAccount'], $request['bankId'], $request['amount'], number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $note, 'Error', $narration);
                    $this->logTransaction($requestId, $request['sourceAccount'], $username, 'External Transfer', $request, $status, $request['amount'], 'Error', $customer['Customername'], $narration, $note);

                    return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS Not Responding', 403);
                }
            } else {
                $note = "External Fund Transfer debit failed for account {$request['beneficiaryAccountNo']}.";
                $this->localDbConnection->debitDataInsert($debit['requestId'], $request['sourceAccount'], $request['bankId'], $request['amount'], number_format((float)$chargesEFT['CostPrice'], 2, '.', ''), $note, 'Error', $narration);
                $this->logTransaction($debit['requestId'], $request['sourceAccount'], $username, 'External Transfer', $request, $debit, $request['amount'], 'Error', $customer['Customername'], $narration, $note);
                return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Bank TSS Not Responding', 403);
            }
        } else {
            return $this->createErrorResponse(ErrorCodes::$FAIL_TRANSACTION, 'Invalid Destination Account', 403);
        }
    }

    private function prepareTransactionLogData($bankid, $user, $request)
    {
        return [
            'bank_code' => $request['beneficiaryBankCode'],
            'username' => $user['username'],
            'account_holder' => $request['beneficiaryName'],
            'account_no' => $request['beneficiaryAccountNo'],
            'amount' => $request['amount'],
            'note' => $request['note'] ?? null,
            'status' => '',
            'timestamp' => date('Y-m-d H:i:s'),
            'transaction_type' => 'Fund Transfer',
            'request' => json_encode($request),
            'response' => ''
        ];
    }

    private function handleTransferResponse($transfer, $request, $user, $transactionLogData)
    {
        $transactionLogData['response'] = json_encode($transfer);
        $transactionLogData['status'] = ($transfer['code'] == 200) ? 'Success' : 'Failed';
        $transactionLogData['note'] = $this->getNoteFromTransfer($transfer);

        $this->logDbConnection->transactionLogDb($transactionLogData);

        return ($transfer['code'] == 200) 
            ? $this->handleSuccessResponse($transfer) 
            : $this->handleErrorResponse($transfer);
    }

    private function getNoteFromTransfer($transfer)
    {
        if (isset($transfer['note'])) {
            return $transfer['note'];
        } elseif (isset($transfer['message'])) {
            return $transfer['message'];
        } else {
            return 'Transaction processed';
        }
    }

    private function handleSuccessResponse($transfer)
    {
        return [
            'code' => 200,
            'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
            'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
            'data' => ['path' => null, 'error' => null],
            'note' => $this->getNoteFromTransfer($transfer)
        ];
    }

    private function handleErrorResponse($transfer)
    {
        return [
            'code' => $transfer['code'] ?? 500,
            'dcode' => $transfer['dcode'] ?? '',
            'message' => $transfer['message'] ?? 'An error occurred',
            'data' => $transfer['data'] ?? null,
            'note' => $this->getNoteFromTransfer($transfer)
        ];
    }

    private function handleException($e)
    {
        return [
            'code' => 500,
            'message' => 'Internal Server Error',
            'data' => $e->getMessage(),
            'note' => 'An unexpected error occurred during the transaction'
        ];
    }

    private function getCustomerByAccount($accountId)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblcustomers WHERE Accountid = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function createErrorResponse($errorCode, $message, $httpCode)
    {
        return [
            'data' => $message,
            'code' => $httpCode,
            'message' => $errorCode[1] . ', ' . $message,
            'dcode' => $errorCode[0],
            'note' => $message
        ];
    }

    private function getFees($feeCode)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblMobileFees WHERE Code = ?");
        $stmt->execute([$feeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getPreBalance()
    {
        $stmt = $this->dbConnection->query("SELECT ValBal FROM tblPrebalance");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function updatePreBalance($amount)
    {
        $stmt = $this->dbConnection->prepare("UPDATE tblPrebalance SET ValBal = ValBal - ?");
        return $stmt->execute([$amount]);
    }

    private function saveBeneficiary($beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username)
    {
        $stmt = $this->dbConnection->prepare("INSERT INTO tblMobileBeneficiaries (Name, AccountNo, BankCode, Username) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$beneficiaryName, $beneficiaryAccountNo, $beneficiaryBankCode, $username]);
    }

    private function logTransaction($requestId, $srcAccount, $username, $action, $request, $response, $amount, $status, $accountHolder, $narration, $note)
    {
        $logData = [
            'bank_code' => $requestId,
            'account_no' => $srcAccount,
            'username' => $username,
            'action' => $action,
            'request' => json_encode($request),
            'response' => json_encode($response),
            'amount' => $amount,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'account_holder' => $accountHolder,
            'note' => $note,
            'transaction_type' => 'Fund Transfer',
            'narration' => $narration
        ];

        $this->logDbConnection->transactionLogDb($logData);
    }
}