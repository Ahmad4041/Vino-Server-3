<?php

class LocalDbController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function checkAppUserExistUpdatedLogic($username, $accountId, $bankId, $password, $deviceId)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM appUsers WHERE username = ? AND bankId = ? AND accountId = ?");
        $stmt->execute([$username, $bankId, $accountId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $stmt = $this->dbConnection->prepare("INSERT INTO appUsers (username, bankId, accountId, password, created_at, updated_at, deviceId) VALUES (?, ?, ?, ?, NOW(), NOW(), ?)");
            $stmt->execute([$username, $bankId, $accountId, $password, $deviceId]);
        }
    }

    public function insertTokenOld($data, $bankId, string $token, $deviceId)
    {
        $username = $data['Username'] . '-NewApp';
        $accountId = $data['AccountID'];


        try {
            // Check if the user already has a record
            $sql = "SELECT * FROM appUsers WHERE username = :username AND bankId = :bankId AND accountId = :accountId";
            $stmt = $this->dbConnection->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':bankId', $bankId);
            $stmt->bindParam(':accountId', $accountId);
            $stmt->execute();
            $checkUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($checkUser) {
                // Update token if user exists
                $sql = "UPDATE appUsers SET token = :token WHERE username = :username AND bankId = :bankId AND accountId = :accountId AND deviceId = :deviceId";
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':bankId', $bankId);
                $stmt->bindParam(':accountId', $accountId);
                $stmt->bindParam(':deviceId', $deviceId);
                $stmt->execute();
            } else {
                // Insert new record if user does not exist
                $sql = "INSERT INTO appUsers (username, bankId, accountId, token, deviceId) VALUES (:username, :bankId, :accountId, :token, :deviceId)";
                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':bankId', $bankId);
                $stmt->bindParam(':accountId', $accountId);
                $stmt->bindParam(':token', $token);
                $stmt->bindParam(':deviceId', $deviceId);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }



    function authenticateUserDevice($username, $bankId, $deviceId)
    {
        $username = $username . '-NewApp';

        // Check if the username exists for the client
        $stmt = $this->dbConnection->prepare("SELECT * FROM users_device_verify WHERE username = ? AND bankid = ?");
        $stmt->execute([$username, $bankId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Username doesn't exist, insert new record
            $stmt = $this->dbConnection->prepare("INSERT INTO users_device_verify (username, bankid, deviceid) VALUES (?, ?, ?)");
            $stmt->execute([$username, $bankId, $deviceId]);
            return ["code" => 200, "message" => "New device registered successfully."];
        } else {
            // Username exists, check phone ID
            if ($user['deviceid'] == "-") {
                // Phone ID has been reset, update with new phone ID
                $stmt = $this->dbConnection->prepare("UPDATE users_device_verify SET deviceid = ? WHERE username = ? AND bankid = ?");
                $stmt->execute([$deviceId, $username, $bankId]);
                return ["code" => 200, "message" => "Device registered successfully."];
            } else {
                // Check if phone ID matches
                if ($user['deviceid'] == $deviceId) {
                    return ["code" => 200, "message" => "Login successful."];
                } else {
                    return ["code" => 203, "message" => "This Device Has Not Been Registered."];
                }
            }
        }
    }




    public function insertToken($username, $bankId, string $token, $token_exp, $accountId, $deviceId)
    {
        $username = $username . '-NewApp';

        try {
            // Start a transaction
            $this->dbConnection->beginTransaction();

            // Check if the user already exists
            $checkSql = "SELECT * FROM appUsers 
                         WHERE username = :username 
                         AND bankId = :bankId 
                         AND accountId = :accountId
                         LIMIT 1";

            $checkStmt = $this->dbConnection->prepare($checkSql);
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':bankId', $bankId);
            $checkStmt->bindParam(':accountId', $accountId);
            $checkStmt->execute();

            $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                // Update existing record
                $updateSql = "UPDATE appUsers 
                              SET token = :token, 
                                  token_exp = :token_exp,
                                  deviceId = :deviceId,
                                  is_active = 1, 
                                  updated_at = NOW()
                              WHERE username = :username 
                              AND bankId = :bankId 
                              AND accountId = :accountId";

                $updateStmt = $this->dbConnection->prepare($updateSql);
                $updateStmt->bindParam(':token', $token);
                $updateStmt->bindParam(':token_exp', $token_exp);
                $updateStmt->bindParam(':deviceId', $deviceId);
                $updateStmt->bindParam(':username', $username);
                $updateStmt->bindParam(':bankId', $bankId);
                $updateStmt->bindParam(':accountId', $accountId);
                $updateStmt->execute();
            } else {
                // Insert new record
                $insertSql = "INSERT INTO appUsers (username, bankId, accountId, token, token_exp, deviceId, is_active, created_at, updated_at)
                              VALUES (:username, :bankId, :accountId, :token, :token_exp, :deviceId, 1, NOW(), NOW())";

                $insertStmt = $this->dbConnection->prepare($insertSql);
                $insertStmt->bindParam(':username', $username);
                $insertStmt->bindParam(':bankId', $bankId);
                $insertStmt->bindParam(':accountId', $accountId);
                $insertStmt->bindParam(':token', $token);
                $insertStmt->bindParam(':token_exp', $token_exp);
                $insertStmt->bindParam(':deviceId', $deviceId);
                $insertStmt->execute();
            }

            // Revoke other active tokens for the same user on different devices
            $revokeSql = "UPDATE appUsers 
                          SET is_active = 0, updated_at = NOW()
                          WHERE username = :username 
                          AND bankId = :bankId 
                          AND accountId = :accountId 
                          AND deviceId != :deviceId 
                          AND is_active = 1";

            $revokeStmt = $this->dbConnection->prepare($revokeSql);
            $revokeStmt->bindParam(':username', $username);
            $revokeStmt->bindParam(':bankId', $bankId);
            $revokeStmt->bindParam(':accountId', $accountId);
            $revokeStmt->bindParam(':deviceId', $deviceId);
            $revokeStmt->execute();

            // Commit the transaction
            $this->dbConnection->commit();

            return true;
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $this->dbConnection->rollBack();
            error_log("Error in insertToken: " . $e->getMessage());
            // var_dump($e->getMessage());  // This is for debugging, consider removing in production
            return false;
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
        $sql = "SELECT bankname FROM banks WHERE bankcode = :bankid LIMIT 1";
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

    // public function isTokenValid($username, $bankId, $jwtToken, $accountId)
    // {
    //     $query = "SELECT * FROM appUsers WHERE username = :username AND bankId = :bankId AND token = :token AND accountId = :accountId";
    //     $stmt = $this->dbConnection->prepare($query);
    //     $stmt->execute([
    //         'username' => $username,
    //         'bankId' => $bankId,
    //         'token' => $jwtToken,
    //         'accountId' => $accountId,
    //     ]);

    //     return $stmt->fetch() ? true : false;
    // }
    public function isTokenValid($username, $jwtToken, $accountId, $bankid)
    {
        // Optimized query using EXISTS to quickly check for token validity
        $query = "
        SELECT EXISTS (
            SELECT 1 
            FROM appUsers 
            WHERE username = :username
              AND token = :token
              AND accountId = :accountId
              AND bankId = :bankId
        );
    ";

        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([
            'username' => $username,
            'token' => $jwtToken,
            'accountId' => $accountId,
            'bankId' => $bankid,
        ]);

        // Directly return the existence check result as boolean
        return (bool) $stmt->fetchColumn();
    }

    public function updateAppFeatures($dataRequest)
    {
        $module = 'AppFeatures';
        $key = 'features';
        $value = json_encode($dataRequest);
        $currentTimestamp = date('Y-m-d H:i:s');

        try {
            $this->dbConnection->beginTransaction();

            $query = "
                INSERT INTO banksettings (module, `key`, value, status, created_at, updated_at)
                VALUES (:module, :key, :value, 1, :timestamp, :timestamp)
                ON DUPLICATE KEY UPDATE 
                    value = VALUES(value),
                    updated_at = VALUES(updated_at)
            ";

            $stmt = $this->dbConnection->prepare($query);
            $stmt->execute([
                ':module' => $module,
                ':key' => $key,
                ':value' => $value,
                ':timestamp' => $currentTimestamp
            ]);

            $this->dbConnection->commit();
            return [
                'code' => 200,
                'message' => "Success List Update",
            ];
        } catch (PDOException $e) {
            $this->dbConnection->rollBack();
            error_log("Error updating AppFeatures: " . $e->getMessage());
            return [
                'code' => 500,
                'message' => "Error updating AppFeatures: " . $e->getMessage(),
            ];
        }
    }
}
