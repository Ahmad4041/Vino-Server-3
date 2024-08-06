<?php

class BankDbController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        // $this->dbname = $dbConnection->getDatabaseName();
    }



    private function checkUserExists($username)
    {
        $sql = "SELECT * FROM tblMobileUsers WHERE Username = :username AND Active = 'N' AND AType = 'Default'";

        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getAccountClass($accountType)
    {
        $sql = "SELECT Acct FROM tblaccount WHERE AcctCode = ?";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$accountType]);
        return $stmt->fetchColumn();
    }

    private function insertIntoMobileAcctSaving($data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO tblMobileAcctSavings ($columns) VALUES ($placeholders)";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    private function insertIntoMobileUser($data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO tblMobileUsers ($columns) VALUES ($placeholders)";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    private function checkCustomerExists($accountID, $internetID)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblMobileUsers WHERE AccountID = ? AND InternetID = ?");
        $stmt->execute([$accountID, $internetID]);
        return $stmt->fetch();
    }

    private function checkMobileRegistration($accountID, $internetID)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblMobileReg WHERE AccountID = ? AND InternetID = ?");
        $stmt->execute([$accountID, $internetID]);
        return $stmt->fetch();
    }
    private function checkUserAuthCreds($username, $password)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblMobileUsers WHERE Username = ? AND Password = ?");
        $stmt->execute([$username, $password]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function registerNewCustomer($request)
    {

        $checkuserExist = $this->checkUserExists($request['username']);

        if ($checkuserExist !== null && is_array($checkuserExist) && !empty($checkuserExist)) {
            $message = ErrorCodes::$FAIL_USER_ALREADY_EXIST[1];
            $data = null;
            $dcode = ErrorCodes::$FAIL_USER_ALREADY_EXIST[0];
            $code = 403;
            return [
                'code' => $code,
                'message' => $message . ', code:' . $dcode,
            ];
        }

        $getAccountClass = $this->getAccountClass($request['accountType']);

        $dataToInsert = [
            'Surname' => $request['surname'] ?? '',
            'Othername' => $request['otherName'] ?? '',
            'Gender' => $request['gender'] ?? '',
            'DOB' => $request['dob'] ?? '',
            'Occupation' => $request['occupation'] ?? '',
            'Email' => $request['email'] ?? '',
            'Contact_Add' => $request['residentialAddress'] ?? '',
            'Office_Add' => $request['Office_Add'] ?? '',
            'Telephone1' => $request['contact'] ?? '',
            'Telephone2' => $request['Telephone2'] ?? '',
            'Nationality' => $request['nationality'] ?? '',
            'Account_Class' => $getAccountClass ?? '',
            'Account_Type' => $request['accountType'] ?? '',
            'BVN' => $request['bvn'] ?? '',
            'Next_Of_Kin' => $request['Next_Of_Kin'] ?? '',
            'Next_Of_Kin_Add' => $request['Next_Of_Kin_Add'] ?? '',
            'Mandate' => $request['Mandate'] ?? '',
            'Domain' => $request['Domain'] ?? '',
            'Acct_Officer' => $request['Acct_Officer'] ?? '',
            'ATMNo' => $request['ATMNo'] ?? '',
            'Ddate' => $request['Ddate'] ?? '',
            'Passportimage' => $request['userFileId'] ?? '',
            'Signatureimage' => $request['signatureFileId'] ?? '',
            'Nicimage' => $request['nicFileId'] ?? '',
            'Nin' => $request['nin'] ?? '',
        ];

        $successInsert = $this->insertIntoMobileAcctSaving($dataToInsert);

        if ($successInsert) {
            $dataToInsertMblTbl = [
                'Username' => $request['username'] ?? '',
                'Password' => $request['password'] ?? '',
                'ContactNo' => $request['telephone'] ?? '',
                'Active' => 'N',
                'AType' => 'Default',
                'Ddate' => date('Y-m-d H:i:s'),
                'InternetID' => $request['InternetID'] ?? '',
                'AccountID' => $request['AccountID'] ?? '',
                'PIN' => $request['PIN'] ?? '',
                'LoginCount' => $request['LoginCount'] ?? 0,
            ];


            $success = $this->insertIntoMobileUser($dataToInsertMblTbl);

            if ($success) {
                return [
                    'code' => 200,
                    'message' => 'Data Inserted Successfully',
                ];
            }
            return [
                'code' => 403,
                'message' => 'Data insertion failed for Mobile User',
            ];
        }
        return [
            'code' => 402,
            'message' => 'Data insertion failed for Saving Account',
        ];
    }


    public function registerExistCustomerBank($requestData)
    {
        try {
            // Check if customer already exists
            if ($this->checkCustomerExists($requestData['accountID'], $requestData['internetID'])) {
                return [
                    'code' => ErrorCodes::$FAIL_CUSTOMER_EXIST[0],
                    'message' => ErrorCodes::$FAIL_CUSTOMER_EXIST[1],
                ];
            }

            // Check if user already exists
            if ($this->checkUserExists($requestData['username'])) {
                return [
                    'code' => ErrorCodes::$FAIL_USER_ALREADY_EXIST[0],
                    'message' => ErrorCodes::$FAIL_USER_ALREADY_EXIST[1],
                ];
            }

            // Check mobile registration eligibility
            if ($this->checkMobileRegistration($requestData['accountID'], $requestData['internetID'])) {
                return [
                    'code' => ErrorCodes::$FAIL_USER_MOBILE_REGISTRATION_ELIGIBILITY[0],
                    'message' => ErrorCodes::$FAIL_USER_MOBILE_REGISTRATION_ELIGIBILITY[1],
                ];
            }

            $newUser = [
                'Username' => $requestData['username'],
                'Password' => $requestData['password'],
                'AccountID' => $requestData['accountID'],
                'InternetID' => $requestData['internetID'],
                'Ddate' => date('Y-m-d H:i:s'),
                'Active' => 1,
                'AType' => 'Default',
            ];

            if ($this->insertIntoMobileUser($newUser)) {
                return [
                    'code' => 200,
                    'message' => 'Data Inserted',
                ];
            } else {
                return [
                    'code' => 201,
                    'message' => 'Data Insertion Fail',
                ];
            }
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function authUser($request)
    {
        $username = $request['username'];
        $password = $request['password'];

        $user = $this->checkUserAuthCreds($username, $password);
        if ($user) {
            return [
                'code' => 200,
                'message' => 'Login Successful',
                'data' => $user,
            ];
        } else {
            return [
                'code' => 403,
                'message' => 'Invalid Username or Password',
            ];
        }
    }
}
