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

    private function getUserByAccountID($accountid)
    {
        $sql = "SELECT * FROM tblMobileUsers WHERE AccountID = :accountid";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountid', $accountid, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCustomerByAccountNo2($accountno)
    {
        $fields = [
            'Accountid', 'Surname', 'Othernames', 'customerAddress as customerAddress1', 'customerAddress2', 'customerAddress3',
            'Nationality', 'telephone as telephone1', 'telephone2', 'dob', 'sex as gender', 'email', 'bvn', 'customername as customerName', 'customername as title',
            'idNo', 'nin', 'AcctOfficer as accountOfficer', 'Passport as passportImageUrl', 'Signature as signatureImageUrl', 'Signature2 as ninImageUrl', 'Signature2 as userImageUrl'
        ];
        $sql = "SELECT " . implode(',', $fields) . " FROM tblcustomers WHERE Accountid = :accountno";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountno', $accountno, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    private function getAccountByUsername($userName, $fields)
    {
        $fieldList = implode(', ', $fields);
        $sql = "SELECT $fieldList FROM tblMobileUsers WHERE Username = :username ORDER BY AType ASC";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':username', $userName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getUserAccountInfo($accountId, $Atype)
    {
        $fields = [
            'AccountID as accountNo', 'AccountType as accountType', 'Customername as accountName',
            'BalC1 as accountBalance', 'LastD as lastDeposit', 'LastW as lastWithdrawal',
            'BalC2 as unclearBalance', 'BalL1 as loanBalance'
        ];

        $fieldList = implode(', ', $fields);
        $sql = "SELECT $fieldList FROM tblcustomers WHERE Accountid = :accountid";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountid', $accountId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'accountNo' => $result['accountNo'],
            'AType' => $Atype,
            'accountType' => $result['accountType'],
            'accountName' => $result['accountName'],
            'accountBalance' => (float) $result['accountBalance'],
            'lastDeposit' => (float) $result['lastDeposit'],
            'lastWithdrawal' => (float) $result['lastWithdrawal'],
            'unclearBalance' => (float) $result['unclearBalance'],
            'loanBalance' => (float) $result['loanBalance']
        ];
    }


    //******************************************** */
    // ******************************************* BANK DB PUBLIC FUNCTIONS ***********************************************
    //******************************************** */



    public function loanDataDetails($accountids)
    {
        $accountids = is_array($accountids) ? $accountids : [$accountids];
        $placeholders = implode(',', array_fill(0, count($accountids), '?'));
        $sql = "SELECT 
                    Loan, Lbalance, DeductDate, Deduct, Period, RefNo 
                FROM tblLoanStandingNEW 
                WHERE AccountID IN ($placeholders)";

        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($accountids);

        $loans = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $loans[] = [
                'loanAmount' => (float) $row['Loan'],
                'leftToPayAmount' => (float) $row['Lbalance'],
                'nextPaymentDate' => date('Y-m-d', strtotime($row['DeductDate'])),
                'paidInstallment' => (float) $row['Deduct'],
                'totalInstallment' => (float) $row['Period'],
                'referenceNo' => $row['RefNo'],
                'paidAmount' => (float) $row['Loan'] - (float) $row['Lbalance'],
            ];
        }

        return [
            'code' => 200,
            'message' => 'Data Fetched Successfully',
            'data' => $loans,
        ];
    }


    public function statement_summary($accountids)
    {
        $accountids = is_array($accountids) ? $accountids : [$accountids];
        $placeholders = implode(',', array_fill(0, count($accountids), '?'));

        $sql = "SELECT TOP 6
                MONTH(trnDate) AS month, 
                YEAR(trnDate) AS year, 
                SUM(debit) AS withdraw, 
                SUM(credit) AS deposit 
            FROM tblcustomerledger 
            WHERE AcctNo IN ($placeholders) 
            GROUP BY YEAR(trnDate), MONTH(trnDate) 
            ORDER BY YEAR(trnDate) DESC, MONTH(trnDate) DESC";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute($accountids);

        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'month' => (int) $row['month'],
                'year' => (int) $row['year'],
                'withdraw' => (float) $row['withdraw'],
                'deposit' => (float) $row['deposit']
            ];
        }

        if (empty($data)) {
            return [
                'code' => 403,
                'message' => 'No Data Found',
            ];
        } else {
            return [
                'code' => 200,
                'message' => 'Statement Summary',
                'data' => array_reverse($data),
            ];
        }
    }



    public function getAllcustomerAccounts($userName, $fields, $balance = false)
    {
        $accounts = $this->getAccountByUsername($userName, ['AccountID', 'AType']);
        $data = [];
        $act = [];

        foreach ($accounts as $acct) {
            $customerdata = $this->getUserAccountInfo($acct['AccountID'], $acct['AType']);
            array_push($act, $acct['AccountID']);
            array_push($data, $customerdata);
        }

        return [
            'code' => 200,
            'accounts' => $act,
            'accountdata' => $data
        ];
    }

    public function accountEnquiry($accountid)
    {
        $userdetail = $this->getUserByAccountID($accountid);
        $customerdetail = $this->getCustomerByAccountNo2($accountid);

        if (!is_null($customerdetail)) {
            $response = [
                'id' => $userdetail['ID'],
                'pinCode' => $userdetail['PIN'],
                'customer' => $customerdetail,
            ];
            return [
                'code' => 200,
                'message' => 'Customer Info',
                'data' => $response,
            ];
        } else {
            $response = ['message' => 'Invalid Customer Account Number'];
            return [
                'code' => 401,
                'message' => 'Error',
                'data' => $response,
            ];
        }
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