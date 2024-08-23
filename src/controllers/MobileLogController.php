<?php

class MobileLogController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function logMobileLogin($logData)
    {
        $phoneId = isset($logData['PhoneID']) ? $logData['PhoneID'] : '';

        $query = "INSERT INTO mobile_logs (ClientID, PhoneID, Username) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->dbConnection, $query);
        mysqli_stmt_bind_param($stmt, "sss", $logData['ClientID'], $phoneId, $logData['Username']);

        $insertData = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

        if ($insertData) {
            return [
                'code' => 200,
                'message' => 'Data Inserted Successfully'
            ];
        }
        return [
            'code' => 403,
            'message' => 'Transaction Log Data Insertion Error'
        ];
    }

    public function transactionLogDb($logData)
    {
        $query = "INSERT INTO trans_full (bank_code, account_no, username, transaction_type, request, response, amount, status, timestamp, account_holder, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->dbConnection, $query);
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssdssss",
            $logData['bankId'],
            $logData['srcAccount'],
            $logData['username'],
            $logData['action'],
            $logData['request'],
            $logData['response'],
            $logData['amount'],
            $logData['status'],
            $logData['timestamp'],
            $logData['account_holder'],
            $logData['note']
        );

        $insertData = mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

        if ($insertData) {
            return [
                'code' => 200,
                'message' => 'Data Inserted Successfully'
            ];
        }
        return [
            'code' => 403,
            'message' => 'Transaction Log Data Insertion Error'
        ];
    }
}
