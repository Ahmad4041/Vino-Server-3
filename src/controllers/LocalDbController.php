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


    function bankCodeCheck($request)
    {
        $bankCode = $request['bankCode'];

        $sql = "SELECT * FROM banks WHERE bankcode = :bankCode";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bind_param(':bankCode', $bankCode, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->get_result();
        $existCode = $result->fetch_assoc(PDO::FETCH_ASSOC);

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
}
