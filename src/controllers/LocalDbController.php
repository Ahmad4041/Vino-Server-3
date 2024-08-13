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
        // Call the API to get all banks
        $charmsConnection = new CharmsAPI();
        $banks = $charmsConnection->getAllBanks()['data'];

        $stmt = $this->dbConnection->prepare("SELECT bankid as code, bankname as name FROM banks WHERE bankid = :bankid");
        $stmt->bindParam(':bankid', $bankid, PDO::PARAM_INT);
        $stmt->execute();
        $currentBank = $stmt->fetch(PDO::FETCH_ASSOC);

        // Prepend the current bank to the list
        array_unshift($banks, [
            'code' => $currentBank['code'],
            'name' => $currentBank['name'] . ' (Internal)'
        ]);

        // Map bank data with charges
        $bankData = array_map(function ($bank) use ($charges) {
            return [
                'code' => $bank['code'],
                'name' => $bank['name'],
                'charges' => $charges,
            ];
        }, $banks);

        return $bankData;
    }
}
