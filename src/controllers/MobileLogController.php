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

        $query = "INSERT INTO tblMobileLog (ClientID, PhoneID, Username) VALUES (:ClientID, :PhoneID, :Username)";
        $stmt = $this->dbConnection->prepare($query);


        $stmt->bindParam(':ClientID', $logData['ClientID'], PDO::PARAM_STR);
        $stmt->bindParam(':PhoneID', $phoneId, PDO::PARAM_STR);
        $stmt->bindParam(':Username', $logData['Username'], PDO::PARAM_STR);

        $insertData = $stmt->execute();

        if ($insertData) {
            return [
                'code' => 200,
                'message' => 'Data Inserted Successfully'
            ];
        }
        return [
            'code' => 403,
            'message' => 'Transaction Log Data Insertion Error: ' . implode(", ", $stmt->errorInfo())
        ];
    }

    public function transactionLogDb($logData)
    {
        $encodedRequest = json_encode($logData['request']);

        $query = "INSERT INTO tblTransFULL (bank_code, account_no, username, transaction_type, request, response, amount, status, timestamp, account_holder, note) 
                  VALUES (:bankId, :srcAccount, :username, :action, :request, :response, :amount, :status, :timestamp, :account_holder, :note)";
        $stmt = $this->dbConnection->prepare($query);


        $stmt->bindParam(':bankId', $logData['bankId'], PDO::PARAM_STR);
        $stmt->bindParam(':srcAccount', $logData['srcAccount'], PDO::PARAM_STR);
        $stmt->bindParam(':username', $logData['username'], PDO::PARAM_STR);
        $stmt->bindParam(':action', $logData['action'], PDO::PARAM_STR);
        $stmt->bindParam(':request', $encodedRequest, PDO::PARAM_STR);
        $stmt->bindParam(':response', $logData['response'], PDO::PARAM_STR);
        $stmt->bindParam(':amount', $logData['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $logData['status'], PDO::PARAM_STR);
        $stmt->bindParam(':timestamp', $logData['timestamp'], PDO::PARAM_STR);
        $stmt->bindParam(':account_holder', $logData['account_holder'], PDO::PARAM_STR);
        $stmt->bindParam(':note', $logData['note'], PDO::PARAM_STR);

        $insertData = $stmt->execute();

        if ($insertData) {
            return [
                'code' => 200,
                'message' => 'Data Inserted Successfully'
            ];
        }
        return [
            'code' => 403,
            'message' => 'Transaction Log Data Insertion Error: ' . implode(", ", $stmt->errorInfo())
        ];
    }
}