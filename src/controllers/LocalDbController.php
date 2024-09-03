<?php

class LocalDbController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function checkAppUserExistUpdatedLogic($username, $accountId, $bankId, $password)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM appUsers WHERE username = ? AND bankId = ? AND accountId = ?");
        $stmt->execute([$username, $bankId, $accountId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = $this->dbConnection->prepare("INSERT INTO appUsers (username, bankId, accountId, password, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$username, $bankId, $accountId, $password]);
        }
    }

    public function insertToken($data, $bankId, string $token)
    {
        $username = $data['Username'];
        $accountId = $data['AccountID'];

        try {
            // Check if the user already has a record
            $sql = "SELECT * FROM appUser WHERE username = :username AND bankId = :bankId AND accountId = :accountId";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':bankId', $bankId);
            $stmt->bindParam(':accountId', $accountId);
            $stmt->execute();
            $checkUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($checkUser) {
                // Update token if user exists
                $sql = "UPDATE appUser SET token = :token WHERE username = :username AND bankId = :bankId AND accountId = :accountId";
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':bankId', $bankId);
                $stmt->bindParam(':accountId', $accountId);
                $stmt->execute();
            } else {
                // Insert new record if user does not exist
                $sql = "INSERT INTO appUser (username, bankId, accountId, token) VALUES (:username, :bankId, :accountId, :token)";
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':bankId', $bankId);
                $stmt->bindParam(':accountId', $accountId);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    function localBanks($bankid, $charges)
    {
        $charmsConnection = new CharmsAPI();
        $banks = $charmsConnection->getAllBanks()['data'];

        $stmt = $this->dbConnection->prepare("SELECT bankid as code, bankname as name FROM banks WHERE bankid = ?");
        $stmt->execute([$bankid]);
        $currentBank = $stmt->fetch(PDO::FETCH_ASSOC);

        array_unshift($banks, [
            'code' => $currentBank['code'],
            'name' => $currentBank['name'] . ' (Internal)'
        ]);

        return array_map(function ($bank) use ($charges) {
            return [
                'code' => $bank['code'],
                'name' => $bank['name'],
                'charges' => $charges,
            ];
        }, $banks);
    }

    function getResponse($serviceID)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM response WHERE name = :name");
        $stmt->bindParam(':name', $serviceID, PDO::PARAM_STR);
        $stmt->execute();

        $response = $stmt->fetch(PDO::FETCH_ASSOC);

        return $response;
    }

    function getResponseVtPass($serviceID, $data, $tvSubscription, $billSubscription)
    {
        $stmt = $this->dbConnection->prepare("
        SELECT name, response 
        FROM response 
        WHERE name IN (:serviceID, :data, :tvSubscription, :billSubscription)
    ");

        $stmt->bindParam(':serviceID', $serviceID, PDO::PARAM_STR);
        $stmt->bindParam(':data', $data, PDO::PARAM_STR);
        $stmt->bindParam(':tvSubscription', $tvSubscription, PDO::PARAM_STR);
        $stmt->bindParam(':billSubscription', $billSubscription, PDO::PARAM_STR);

        $stmt->execute();

        $responses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($responses as $key => $value) {
            $responses[$key] = unserialize($value);
        }

        // var_dump($responses);
        return $responses;
    }

    function getResponseVtPass2(array $serviceNames)
    {
        $placeholders = implode(',', array_fill(0, count($serviceNames), '?'));
        $stmt = $this->dbConnection->prepare("
        SELECT name, response 
        FROM response 
        WHERE name IN ($placeholders)
    ");

        $stmt->execute($serviceNames);

        $responses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('unserialize', $responses);
    }

    function bankCodeCheck($request)
    {
        $bankCode = $request['bankCode'];

        $stmt = $this->dbConnection->prepare("SELECT * FROM banks WHERE bankcode = ?");
        $stmt->execute([$bankCode]);
        $existCode = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existCode) {
            return [
                'code' => 404,
                'message' => 'Bank code not found in the database.',
                'data' => []
            ];
        }

        return [
            'code' => 200,
            'message' => 'Bank code found in the database.',
            'data' => $existCode
        ];
    }

    public function updateConfigLiveData($networks, $utils, $bankList)
    {

        $dataToUpdate = [
            'dataNetwork' => json_encode($networks),
            'dataUtility' => json_encode($utils),
            // 'dataBankList' => $bankList
            'dataBankList' => json_encode($bankList)
        ];

        $checkQuery = "SELECT name FROM response WHERE name = :name";
        $checkStmt = $this->dbConnection->prepare($checkQuery);

        $updateQuery = "UPDATE response SET response = :response WHERE name = :name";
        $updateStmt = $this->dbConnection->prepare($updateQuery);

        $insertQuery = "INSERT INTO response (name, response) VALUES (:name, :response)";
        $insertStmt = $this->dbConnection->prepare($insertQuery);


        foreach ($dataToUpdate as $name => $response) {
            $checkStmt->execute([':name' => $name]);
            $exists = $checkStmt->fetchColumn();

            if ($exists) {
                $updateStmt->execute([
                    ':name' => $name,
                    ':response' => $response
                ]);
            } else {
                $insertStmt->execute([
                    ':name' => $name,
                    ':response' => $response
                ]);
            }
        }

        return [
            'code' => 200,
            'message' => 'Config Data Update Success',
            'data' => true,
        ];
    }

    public function fetchResponseData()
    {
        $names = ['dataNetwork', 'dataUtility'];

        $placeholders = rtrim(str_repeat('?, ', count($names)), ', ');
        $query = "SELECT name, response FROM response WHERE name IN ($placeholders)";
        $stmt = $this->dbConnection->prepare($query);

        $stmt->execute($names);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $responseData = [
            'networks' => [],
            'utilities' => [],
        ];

        foreach ($results as $row) {
            if ($row['name'] === 'dataNetwork') {
                $responseData['networks'] = json_decode($row['response'], true);
            } elseif ($row['name'] === 'dataUtility') {
                $responseData['utilities'] = json_decode($row['response'], true);
            }
        }

        return $responseData;
    }

    public function fetchBankListData()
    {
        $name = 'dataBankList';
        $query = "SELECT response FROM response WHERE name = :name";
        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([':name' => $name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['response'])) {
            $bank_list = $this->tryParseJson($result['response']);
            if ($bank_list !== null) {
                return $bank_list;
            } else {
                // Log the error or handle it in some other way
                echo 'JSON Decoding Error: Unable to decode "dataBankList" response.';
                return [];
            }
        } else {
            // No "dataBankList" row found
            return [];
        }
    }

    private function tryParseJson($json_string)
    {

        $json_string = preg_replace('/^\xEF\xBB\xBF/', '', $json_string);

        $data = json_decode($json_string, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            echo "JSON Decoding Error: " . json_last_error_msg();

            return $this->fixMalformedJsonData($json_string);
        }
    }

    private function fixMalformedJsonData($json_string)
    {
        // Attempt to fix common issues with JSON data
        $fixed_json_string = preg_replace([
            '/"service":\s*undefined/', // Replace "undefined" values with empty strings
            '/,\s*]/', // Remove trailing commas before closing brackets
            '/,\s*}/'  // Remove trailing commas before closing braces
        ], [
            '"service":""',
            ']',
            '}'
        ], $json_string);

        $data = json_decode($fixed_json_string, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            echo "JSON Decoding Error after fix: " . json_last_error_msg();
            return null;
        }
    }


    public function debitDataInsert($requestId, $srcAcct, $srcBankCode, $amount, $fee, $description, $status, $note)
    {
        // Set the note based on the status
        $note = ($status === 'SUCCESSFUL') ? 'Client Top Up done, Successfully.' : $note;

        try {
            // Prepare the SQL query for inserting data
            $sql = "INSERT INTO LogDebitData (requestId, srcAccount, srcBankCode, amount, fee, description, status, note)
                VALUES (:requestId, :srcAccount, :srcBankCode, :amount, :fee, :description, :status, :note)";

            // Prepare and execute the statement
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':requestId', $requestId);
            $stmt->bindParam(':srcAccount', $srcAcct);
            $stmt->bindParam(':srcBankCode', $srcBankCode);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':fee', $fee);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':note', $note);

            // Execute the query
            $insertData = $stmt->execute();

            return [
                'code' => $insertData ? 200 : 403,
                'message' => $insertData ? 'Data Inserted Successfully' : 'Data Insertion Error'
            ];
        } catch (Exception $e) {
            return [
                'code' => 403,
                'message' => 'Data Insertion Error | ' . $e->getMessage()
            ];
        }
    }


    public function getBankName($bankid)
    {
        $sql = "SELECT Top 1 bankname FROM banks WHERE bankcode = :bankid";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':bankid', $bankid, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $getBankName = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($getBankName) {
                return [
                    'code' => 200,
                    'message' => 'Success',
                    'bankName' => $getBankName['bankname'],
                ];
            } else {
                return [
                    'code' => 404,
                    'message' => 'Bank Not Found',
                ];
            }
        } else {
            return [
                'code' => 500,
                'message' => 'Database Query Failed',
            ];
        }
    }
}
