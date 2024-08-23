<?php

class TopUpMobileController
{
    private $localDbConnection;
    private $logDbConnection;
    private $bankDbConnection;
    private $coreBankConnection;

    public function __construct($bankid)
    {
        $this->localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $this->logDbConnection = new MobileLogController(Database::getConnection('log'));
        $this->bankDbConnection = new BankDbController(Database::getConnection($bankid));
        $this->coreBankConnection = new CoreBankController();
    }

    public function topUpMobile($bankid, $user, $request)
    {
        try {
            $transactionLogData = $this->initializeTransactionLog($bankid, $request, $user);

            $mainLogic = $this->coreLogic($request, $user, $bankid, "Topup Request for {$request['phoneNo']}.");
            if ($mainLogic['code'] != 200) {
                return $this->handleFailedTransaction($mainLogic, $transactionLogData);
            }

            $vtpass = new VTPassController();
            $res = $vtpass->buyAirtimeLive($request['networkCode'], $request['phoneNo'], $request['amount']);

            if ($res['code'] !== "000") {
                return $this->handleReversalProcess($mainLogic, $request, $bankid, $transactionLogData);
            }

            return $this->handleSuccessfulTransaction($res, $request, $bankid, $mainLogic, $transactionLogData);
        } catch (Exception $e) {
            return [
                'message' => $e->getMessage(),
                'data' => null,
                'dcode' => 500,
                'code' => 500
            ];
        }
    }

    private function initializeTransactionLog($bankid, $request, $user)
    {
        return [
            'bankId' => $bankid,
            'srcAccount' => $request['srcAccount'],
            'username' => $user['username'],
            'action' => 'TopUp',
            'request' => $request,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    private function handleFailedTransaction($mainLogic, $transactionLogData)
    {
        $response = $this->customResponse($mainLogic['message'], null, 400, 400);
        $this->logTransaction($transactionLogData, $response, 'Error');
        return $response;
    }

    private function handleReversalProcess($mainLogic, $request, $bankid, $transactionLogData)
    {
        $coreBankConnection = new CoreBankController(UtilityDemo::getDatabaseConnection($bankid));
        $reversal = $coreBankConnection->doReversal2($mainLogic['requestId']);

        $status = $reversal['status'] == 202 ? 'Success' : 'Error';
        $description = $status == 'Success' ? "Reversal has been done Successfully" : "Reversal Error. Please Check";
        $note = "Reversal {$status} on Phone No: {$request['phoneNo']} of amount {$request['amount']} from {$request['srcAccount']}";

        $this->logDebitData($reversal['requestId'], $request, $bankid, $mainLogic['fee'], $description, $status, $note);

        $response = $this->customResponse("Switch Not Responding", $note, $status == 'Success' ? 200 : 203, 400);
        $this->logTransaction($transactionLogData, $response, 'Error', $note);

        return $response;
    }

    private function handleSuccessfulTransaction($res, $request, $bankid, $mainLogic, $transactionLogData)
    {
        $note = "Topup request Successful for {$request['phoneNo']}.";
        $description = "Top up has been done Successfully.";

        $this->logDebitData($res['requestId'], $request, $bankid, $mainLogic['fee'], $description, 'Success', $note);

        $response = $this->customResponse(ErrorCodes::$SUCCESS_TRANSACTION[1], $note, ErrorCodes::$SUCCESS_TRANSACTION[0], 200);
        $this->logTransaction($transactionLogData, $response, 'Success', $note, $mainLogic['totalAmount']);

        return $response;
    }

    private function logDebitData($requestId, $request, $bankid, $fee, $description, $status, $note)
    {
        $this->localDbConnection->debitDataInsert(
            $requestId,
            $request['srcAccount'],
            $bankid,
            $request['amount'],
            number_format((float)$fee, 2, '.', ''),
            $description,
            $status,
            $note
        );
    }

    private function logTransaction($transactionLogData, $response, $status, $note = '', $amount = null)
    {
        $transactionLogData['response'] = json_encode($response);
        $transactionLogData['amount'] = $amount ?? $transactionLogData['request']['amount'];
        $transactionLogData['status'] = $status;
        $transactionLogData['note'] = $note;

        // $this->localDbConnection->transactionLog($transactionLogData);
        $this->logDbConnection->transactionLogDb($transactionLogData);
    }

    private function coreLogic($request, $user, $bankid, $note)
    {
        $srcAcct = $request['srcAccount'];
        $amount = $request['amount'];

        $validateCustomer = $this->bankDbConnection->customerValidate($srcAcct, $user['username']);
        if ($validateCustomer['code'] !== 200) {
            return $this->customResponse(ErrorCodes::$FAIL_TRANSACTION[1] . ', ' . $validateCustomer['message'], null, 403, 403);
        }

        $getCharges = $this->bankDbConnection->getMobileFees('FEE_TM');
        if ($getCharges['code'] !== 200) {
            return $this->customResponse(ErrorCodes::$FAIL_TRANSACTION[1] . ', ' . $getCharges['message'], null, 403, 403);
        }

        $fee = (float) $getCharges['SellPrice'];
        $totalAmount = $amount + $fee;

        if ($validateCustomer['balance'] < $totalAmount) {
            return $this->customResponse(ErrorCodes::$FAIL_TRANSACTION[1] . ', ' . ErrorCodes::$FAIL_ACCOUNT_BALANCE_NOT_ENOUGH[1], null, 403, 403);
        }

        try {
            $debitRequest = $this->coreBankConnection->debitNew2(
                $srcAcct,
                $bankid,
                number_format((float)$amount, 2, '.', ''),
                number_format((float)$fee, 2, '.', ''),
                $note
            );

            if ($debitRequest['status'] !== 200) {
                return $this->customResponse("Issuer or Switch Inoperative", $debitRequest['requestId'], 403, 403);
            }
        } catch (Exception $e) {
            return $this->customResponse("An error occurred during the debit process: " . $e->getMessage(), null, 500, 500);
        }

        $costPrice = $amount + $getCharges['CostPrice'];
        $balance = $this->bankDbConnection->balanceCheck($costPrice, 'VINO');
        if ($balance['code'] !== 200) {
            return $this->customResponse($balance['message'], $debitRequest['requestId'], 403, 201);
        }

        return [
            'code' => 200,
            'message' => 'Success',
            'totalAmount' => $totalAmount,
            'fee' => $fee,
            'requestId' => $debitRequest['requestId'],
            'debit_message' => $debitRequest['message'],
            'status' => $debitRequest['status']
        ];
    }

    private function customResponse($message, $data = null, $dcode, $code)
    {
        return [
            'message' => $message,
            'data' => $data,
            'dcode' => $dcode,
            'code' => $code
        ];
    }
}
