<?php

class BankDbController
{
    private $dbConnection;
    private $dbname;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        $this->dbname = $dbConnection->getDatabaseName();
    }



    private function checkUserExists($username)
    {
        $sql = "SELECT * FROM tblmobileuser WHERE username = ? AND active = 'N' AND AType = 'Default'";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch();
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
        $sql = "INSERT INTO tblMobileAcctSaving ($columns) VALUES ($placeholders)";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    private function insertIntoMobileUser($data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO tblmobileuser ($columns) VALUES ($placeholders)";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function registerNewCustomer($request)
    {
        $checkuserExist = $this->checkUserExists($request['username']);

        if (!is_null($checkuserExist)) {
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
            'Surname' => $request['surname'],
            'Othername' => $request['otherName'],
            'Gender' => $request['gender'],
            'DOB' => $request['dob'],
            'Occupation' => $request['occupation'],
            'Email' => $request['email'],
            'Contact_Add' => $request['residentialAddress'],
            'Office_Add' => $request['Office_Add'],
            'Telephone1' => $request['contact'],
            'Telephone2' => $request['Telephone2'],
            'Nationality' => $request['nationality'],
            'Account_Class' => $getAccountClass,
            'Account_Type' => $request['accountType'],
            'BVN' => $request['bvn'],
            'Next_Of_Kin' => $request['Next_Of_Kin'],
            'Next_Of_Kin_Add' => $request['Next_Of_Kin_Add'],
            'Mandate' => $request['Mandate'],
            'Domain' => $request['Domain'],
            'Acct_Officer' => $request['Acct_Officer'],
            'ATMNo' => $request['ATMNo'],
            'Ddate' => $request['Ddate'],
            'Passportimage' => $request['userFileId'],
            'Signatureimage' => $request['signatureFileId'],
            'Nicimage' => $request['nicFileId'],
            'Nin' => $request['nin'],
        ];

        $successInsert = $this->insertIntoMobileAcctSaving($dataToInsert);

        if ($successInsert) {
            $dataToInsertMblTbl = [
                'Username' => $request['username'],
                'Password' => $request['password'],
                'ContactNo' => $request['telephone'],
                'Active' => 'N',
                'AType' => 'Default',
                'Ddate' => date('Y-m-d H:i:s'),
                'InternetID' => $request['InternetID'],
                'AccountID' => $request['AccountID'],
                'PIN' => $request['PIN'],
                'LoginCount' => $request['LoginCount'],
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
}
