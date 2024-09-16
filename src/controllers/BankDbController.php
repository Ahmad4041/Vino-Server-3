<?php

class BankDbController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
        // $this->dbname = $dbConnection->getDatabaseName();
    }

    private function round_to_2dp($number)
    {
        return number_format((float)$number, 2, '.', '');
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
            'AccountID as accountNo',
            'AccountType as accountType',
            'Customername as accountName',
            'BalC1 as accountBalance',
            'LastD as lastDeposit',
            'LastW as lastWithdrawal',
            'BalC2 as unclearBalance',
            'BalL1 as loanBalance'
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


    public function getCustomerByAccountNo2($accountno)
    {
        $fields = [
            'Accountid',
            'Surname',
            'Othernames',
            'customerAddress as customerAddress1',
            'customerAddress2',
            'customerAddress3',
            'Nationality',
            'telephone as telephone1',
            'telephone2',
            'dob',
            'sex as gender',
            'email',
            'bvn',
            'customername as customerName',
            'customername as title',
            'idNo',
            'nin',
            'AcctOfficer as accountOfficer',
            'Passport as passportImageUrl',
            'Signature as signatureImageUrl',
            'Signature2 as ninImageUrl',
            'Signature2 as userImageUrl'
        ];
        $sql = "SELECT " . implode(',', $fields) . " FROM tblcustomers WHERE Accountid = :accountno";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountno', $accountno, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    private function checkPinAlreadySet($username)
    {
        $sql = "SELECT COUNT(*) FROM tblMobileUsers WHERE Username = '$username' AND PIN IS NOT NULL";
        $stmt = $this->dbConnection->query($sql);
        $stmt->bindParam(1, $username);
        $stmt->execute();

        $count = $stmt->fetchColumn();

        return $count > 0;
    }

    private function setPinForUser($userPin, $username)
    {
        // Set the PIN for the user
        $sql = "UPDATE tblMobileUsers SET PIN = ? WHERE Username = ?";
        $stmt = $this->dbConnection->prepare($sql);
        return $stmt->execute([$userPin, $username]);
    }


    private function updatePin($username, $oldPin, $newPin)
    {
        $sql = "UPDATE tblMobileUsers SET PIN = :newPin WHERE Username = :username AND PIN = :oldPin";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':newPin', $newPin);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':oldPin', $oldPin);
        $stmt->execute();
        $rowCount = $stmt->rowCount();

        if ($rowCount > 0) {
            return true;
        } else {
            return false;
        }
    }


    private function updatePassword($username, $existingPassword, $newPassword)
    {
        $sql = "SELECT * FROM tblMobileUsers WHERE Username = ?";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return [
                'code' => ErrorCodes::$FAIL_INVALID_USER[0],
                'message' => ErrorCodes::$FAIL_INVALID_USER[1]
            ];
        }

        if ($result['Password'] !== $existingPassword) {
            return [
                'code' => ErrorCodes::$FAIL_PASSWORD_MATCHED[0],
                'message' => ErrorCodes::$FAIL_PASSWORD_MATCHED[1]
            ];
        }

        $sql = "UPDATE tblMobileUsers SET Password = ? WHERE Username = ?";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$newPassword, $username]);
        $rowCount = $stmt->rowCount();

        if ($rowCount > 0) {
            return [
                'code' => 200,
                'message' => 'Success'
            ];
        } else {
            return [
                'code' => 404,
                'message' => 'Error Updating Password'
            ];
        }
    }

    private function verifyPin($username, $pin)
    {
        $sql = "SELECT TOP 1 1 FROM tblMobileUsers WHERE Username = ? AND PIN = ?";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$username, $pin]);
        return $stmt->fetchColumn() !== false;
    }


    private function getCharges()
    {
        $stmt = $this->dbConnection->prepare("SELECT Code as type, Service as service, CostPrice as cost, SellPrice as amount FROM tblMobileFees");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //******************************************** */
    // ******************************************* BANK DB PUBLIC FUNCTIONS ***********************************************
    //******************************************** */


    public function customerValidate($srcAcct, $username)
    {
        $stmt = $this->dbConnection->prepare("
        SELECT TOP 1 mu.AccountID, cu.BalC1 
        FROM tblMobileUsers mu
        LEFT JOIN tblcustomers cu ON mu.AccountID = cu.Accountid
        WHERE mu.AccountID = :srcAcct AND mu.Username = :username
    ");
        $stmt->bindParam(':srcAcct', $srcAcct);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $customerData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customerData) {
            return [
                'code' => 200,
                'message' => 'Customer has been Found',
                'balance' => (float)$customerData['BalC1'],
            ];
        }

        return [
            'code' => 404,
            'message' => 'Customer Not Found',
        ];
    }


    public function getMobileFees($Code)
    {
        // Use SQL Server's TOP clause for performance optimization
        $stmt = $this->dbConnection->prepare("
        SELECT TOP 1 CostPrice, SellPrice 
        FROM tblMobileFees 
        WHERE Code = :code");

        $stmt->bindParam(':code', $Code, PDO::PARAM_STR); // Explicitly define parameter type
        $stmt->execute();
        $getMobileFees = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if data exists and return accordingly
        if ($getMobileFees) {
            return [
                'code' => 200,
                'CostPrice' => (float)$getMobileFees['CostPrice'],
                'SellPrice' => (float)$getMobileFees['SellPrice'],
            ];
        } else {
            return [
                'code' => 404,
                'message' => 'Mobile fee not found',
            ];
        }
    }

    public function balanceCheck($totalAmt)
    {
        // Prepare and execute query to fetch the balance
        $stmt = $this->dbConnection->prepare("SELECT TOP 1 Cuser, ValBal FROM tblPrebalance");
        $stmt->execute();
        $balanceCheck = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceCheck) {
            return [
                'code' => 404,
                'message' => 'Prebalance: Record not found',
                'old' => null,
                'new' => null
            ];
        }

        $newBalance = $balanceCheck['ValBal'] - $totalAmt;

        if ($newBalance > 0) {
            // Prepare and execute query to update the balance
            $updateStmt = $this->dbConnection->prepare("UPDATE tblPrebalance SET ValBal = :newBalance WHERE Cuser = :cuser");
            $updateStmt->bindParam(':newBalance', $newBalance, PDO::PARAM_INT);
            $updateStmt->bindParam(':cuser', $balanceCheck['Cuser'], PDO::PARAM_STR);
            $balanceUpdate = $updateStmt->execute();

            return $balanceUpdate ? [
                'code' => 200,
                'message' => 'Success',
                'old' => $balanceCheck['ValBal'],
                'new' => $newBalance
            ] : [
                'code' => 403,
                'message' => 'Prebalance: Balance update Error',
                'old' => $balanceCheck['ValBal'],
                'new' => $newBalance
            ];
        } else {
            return [
                'code' => 404,
                'message' => 'Prebalance: Insufficient Balance update Error',
                'old' => $balanceCheck['ValBal'],
                'new' => $newBalance
            ];
        }
    }




    function debitcards($user)
    {
        $username = $user['username'];
        $AccountId = $user['accountId'];

        $query = "SELECT TOP 1 1 FROM tblMobileDebitCard WHERE AccountName = :username AND AccountID = :accountId";
        $stmt = $this->dbConnection->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':accountId', $AccountId);
        $stmt->execute();

        $debitcard = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($debitcard) {
            $mappedData = [
                'accountid' => $debitcard['AccountID'],
                'accountname' => $debitcard['AccountName'],
                'cardno' => (int) $debitcard['CardNo'],
                'cardtype' => $debitcard['CardType'],
                'issueddate' => $debitcard['IssuedDate'],
                'expdate' => $debitcard['ExpDate'],
                'active' => $debitcard['Active'],
            ];

            return [
                'code' => ErrorCodes::$SUCCESS_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABLE[0],
                'message' => ErrorCodes::$SUCCESS_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABLE[1],
                'data' =>  $mappedData,
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABILITY[0],
                'message' => ErrorCodes::$FAIL_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABILITY[1],
                'data' => [],
            ];
        }
    }



    function accountInfo($request)
    {
        $accountId = $request['accountNo'];

        $sql = "SELECT * FROM tblcustomers WHERE Accountid = :Accountid";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bind_param(':Accountid', $accountId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->get_result();

        $accountData = $result->fetch_assoc(PDO::FETCH_ASSOC);

        if ($accountData) {
            return [
                'code' => 200,
                'message' => 'User found',
                'data' => $accountData,
            ];
        } else {
            return [
                'code' => 404,
                'message' => 'User Data not found',
                'data' => [],
            ];
        }
    }


    function getAllbanks($bankid)
    {
        $charges = $this->getCharges();
        $localDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $banks = $localDbConnection->localBanks($bankid, $charges);

        return [
            'code' => 200,
            'message' => 'Get all the Available banks',
            'data' => $banks,
        ];
    }


    function logDbLogin($logData, $accountId)
    {
        $phoneId = $logData['PhoneID'] ?? '';

        // Prepare the SQL statement
        $sql = "INSERT INTO tblmobilelog (AccountID, PhoneID, Username) VALUES (:accountId, :phoneId, :username)";

        // Prepare the statement
        $stmt = $this->dbConnection->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':accountId', $accountId);
        $stmt->bindParam(':phoneId', $phoneId);
        $stmt->bindParam(':username', $logData['Username']);

        // Execute the statement and check if insertion was successful
        if ($stmt->execute()) {
            return [
                'code' => 200,
                'message' => 'Data Inserted Successfully'
            ];
        } else {
            return [
                'code' => 403,
                'message' => 'Transaction Log Data Insertion Error'
            ];
        }
    }





    public function requestGetTransaction($accountId, $page)
    {
        $limit = 20;
        $offset = $page * $limit;

        // Fetch transactions
        $sql = "
        WITH NumberedRows AS (
            SELECT Sno, gjsource, AcctNo, particulars, Debit, Credit, trnDate, TrnTime,
                   ROW_NUMBER() OVER (ORDER BY Sno DESC) AS RowNum
            FROM tblCustomerLedger
            WHERE AcctNo = :accountId
        )
        SELECT Sno, gjsource, AcctNo, particulars, Debit, Credit, trnDate, TrnTime
        FROM NumberedRows
        WHERE RowNum > :offset AND RowNum <= :upperLimit
        ORDER BY Sno DESC";

        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountId', $accountId, PDO::PARAM_STR);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $upperLimit = $offset + $limit;
        $stmt->bindParam(':upperLimit', $upperLimit, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total rows
        $sqlCount = "SELECT COUNT(*) FROM tblCustomerLedger WHERE AcctNo = :accountId";
        $stmtCount = $this->dbConnection->prepare($sqlCount);
        $stmtCount->bindParam(':accountId', $accountId, PDO::PARAM_STR);
        $stmtCount->execute();
        $totalRow = $stmtCount->fetchColumn();

        if (empty($transactions)) {
            return [
                'code' => ErrorCodes::$FAIL_ACCOUNT_TRANSACTION_FOUND[0],
                'data' => ErrorCodes::$FAIL_ACCOUNT_TRANSACTION_FOUND[1],
            ];
        }


        $transactionHistory = array_map(function ($row) {
            $trnTime = new DateTime($row['TrnTime']);
            return [
                'id' => (int) $row['Sno'],
                'reference' => $row['gjsource'],
                'accountNo' => $row['AcctNo'],
                'narration' => $row['particulars'],
                'withdraw' => (float) number_format((float) $row['Debit'], 2, '.', ''),
                'withdraw2' => floatval($row['Credit']),
                'deposit' => (float) number_format((float) $row['Credit'], 2, '.', ''),
                'date' => date('Y-m-d', strtotime($row['trnDate'])),
                'time' => $trnTime->format('H:i:s'),
            ];
        }, $transactions);

        $totalPages = ceil($totalRow / $limit);

        $res = [
            'content' => $transactionHistory,
            'pageable' => [
                'sort' => [
                    'empty' => true,
                    'unsorted' => true,
                    'sorted' => false
                ],
                'offset' => $offset,
                'pageNumber' => (int)$page,
                'pageSize' => $limit,
                'paged' => true,
                'unpaged' => false
            ],
            'totalPages' => $totalPages,
            'totalElements' => (int)$totalRow,
            'last' => ($page >= $totalPages - 1),
            'size' => $limit,
            'number' => (int)$page,
            'sort' => [
                'empty' => true,
                'unsorted' => true,
                'sorted' => false
            ],
            'numberOfElements' => count($transactionHistory),
            'first' => ($page == 0),
            'empty' => empty($transactionHistory)
        ];

        return [
            'code' => 200,
            'data' => $res,
        ];
    }

    public function resetUserPassword($contactNo)
    {
        // Query to find the user
        $userSql = "SELECT TOP 1 AccountID, Username, InternetID, ContactNo 
            FROM tblMobileUsers 
            WHERE ContactNo = ? AND Active = 'Y' AND AType = 'Default'";
        $userStmt = $this->dbConnection->prepare($userSql);

        $userStmt->execute([$contactNo]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'code' => ErrorCodes::$FAIL_CONTACT_NUMBER_REGISTERED[0],
                'message' => ErrorCodes::$FAIL_CONTACT_NUMBER_REGISTERED[1],
            ];
        }

        $userId = $user['AccountID'];

        // Query to find the customer
        $customerSql = "SELECT TOP 1 Customername FROM tblcustomers WHERE Accountid = ?";
        $customerStmt = $this->dbConnection->prepare($customerSql);
        $customerStmt->execute([$userId]);
        $findCustomer = $customerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$findCustomer) {
            return [
                'code' => ErrorCodes::$FAIL_USER_NOT_FOUND[0],
                'message' => ErrorCodes::$FAIL_USER_NOT_FOUND[1],
            ];
        }

        $currentDateTime = date('Y-m-d H:i:s');

        $insertData = [
            'AccountID' => $userId,
            'Username' => $user['Username'],
            'AccountName' => $findCustomer['Customername'],
            'InternetID' => $user['InternetID'],
            'TelNo' => $user['ContactNo'],
            'RType' => 'Password',
            'Ddate' => $currentDateTime,
            'Dtime' => $currentDateTime,
        ];

        // Insert query
        $insertSql = "INSERT INTO tblMobileReset (AccountID, Username, AccountName, InternetID, TelNo, RType, Ddate, Dtime) 
                  VALUES (:AccountID, :Username, :AccountName, :InternetID, :TelNo, :RType, :Ddate, :Dtime)";
        $insertStmt = $this->dbConnection->prepare($insertSql);
        $insertSuccess = $insertStmt->execute($insertData);

        if ($insertSuccess) {
            return [
                'code' => 200,
                'message' => 'Data inserted',
            ];
        }

        return [
            'code' => 403,
            'message' => 'Data insertion Error',
        ];
    }



    public function accountType()
    {
        $sql = "SELECT AcctCode, Acct FROM tblaccount WHERE Mobile = 'Yes'";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($row) {
            return [
                'accountCode' => $row['AcctCode'],
                'accountType' => $row['Acct'],
            ];
        }, $result);

        if ($data) {
            return [
                'code' => 200,
                'message' => 'Customer Info',
                'data' => $data,
            ];
        } else {
            return [
                'code' => 401,
                'message' => 'Error',
                'data' => $data,
            ];
        }
    }

    public function userVerifyPin($pin, $username)
    {
        if ($this->verifyPin($username, $pin)) {
            return [
                'code' => 200,
                'message' => 'Pin Verify Successfully',
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_PIN_VALIDATION[0],
                'message' => ErrorCodes::$FAIL_PIN_VALIDATION[1],
            ];
        }
    }



    public function updateUserPassword($request)
    {
        $result = $this->updatePassword($request['username'], $request['existingPassword'], $request['newPassword']);
        if ($result['code'] == 200) {
            return [
                'code' => 200,
                'message' => 'Password Updated',
            ];
        } else {
            return [
                'code' => $result['code'],
                'message' => $result['message'],
            ];
        }
    }




    public function updateUserPin($request, $username)
    {
        if ($this->updatePin($username, $request['oldPin'], $request['newPin'])) {
            return [
                'code' => 200,
                'message' => 'Pin Updated Successfully',
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_PIN_CODE_INVALID[0],
                'message' => ErrorCodes::$FAIL_PIN_CODE_INVALID[1],
            ];
        }
    }



    public function createUserPin($userPin, $username)
    {
        if ($this->checkPinAlreadySet($username)) {
            return [
                'code' => ErrorCodes::$FAIL_PIN_ALREADY_SET[0],
                'message' => ErrorCodes::$FAIL_PIN_ALREADY_SET[1],
            ];
        } else {
            if ($this->setPinForUser($userPin, $username)) {
                return [
                    'code' => 200,
                    'message' => 'Success, PIN created',
                ];
            } else {
                return [
                    'code' => 500,
                    'message' => 'Error setting PIN',
                ];
            }
        }
    }


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
    public function getCustomerAccounts($username)
    {
        $accounts = $this->getAccountByUsername($username, ['AccountID', 'AType']);
        $data = [];
        $act = [];

        foreach ($accounts as $acct) {
            // $customerdata = $this->getUserAccountInfo($acct['AccountID'], $acct['AType']);
            array_push($act, $acct['AccountID']);
            // array_push($data, $customerdata);
        }

        return $act;
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
                'code' => $dcode,
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


    public function beneficiaries($username)
    {
        $query = "SELECT Id, Name, AccountNo, BankCode, Username
                  FROM tblMobileBeneficiaries
                  WHERE Username = ?
                  ORDER BY Name ASC";

        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([$username]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'code' => 200,
            'message' => 'Data fetched successfully.',
            'data' => empty($data) ? null : $data,
        ];
    }


    function delBeneficiaries($username, $id)
    {

        $stmt = $this->dbConnection->prepare("DELETE FROM tblMobileBeneficiaries WHERE Username = :username AND id = :id");

        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return [
                'code' => 200,
                'message' => 'Beneficiary deleted',
                'data' => ['id' => $id, 'username' => $username]
            ];
        } else {
            return [
                'code' => 403,
                'message' => 'Beneficiary not found!',
                'data' => []
            ];
        }
    }

    function validateAccount($accountID, $username)
    {
        // Prepare the SQL query to fetch all required data
        $sql = "
    SELECT 
        mu.AccountID as mu_AccountID,
        mu.Username as mu_Username,
        c.Accountid as c_Accountid,
        c.BalC1,
        c.Telephone,
        (SELECT COUNT(*) FROM tblPrebalance) as preBalanceExists
    FROM 
        tblMobileUsers mu
    LEFT JOIN 
        tblcustomers c ON mu.AccountID = c.Accountid
    WHERE 
        mu.AccountID = :accountID
        AND mu.Username = :username
    ";

        // Prepare and execute the query
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountID', $accountID, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the account exists
        if (!$result) {
            return [
                'code' => 403,
                'message' => 'User not found',
                'data' => 'User not found',
            ];
        }

        // Check if the username matches the account
        if ($result['mu_Username'] != $username || $result['mu_AccountID'] != $accountID) {
            return [
                'code' => 403,
                'message' => 'Invalid user',
                'data' => 'Invalid user',
                'stage' => "invalid user"
            ];
        }

        // Check if prebalance exists
        if ($result['preBalanceExists'] == 0) {
            return [
                'code' => 403,
                'data' => 'FAIL_TRANSACTION',
                'message' => 'Bank TSS',
                'stage' => "getprebalance"
            ];
        }

        // Check if customer exists
        if (!$result['c_Accountid']) {
            return [
                'code' => 403,
                'message' => 'Customer not found',
                'stage' => "getcustomer",
                'data' => 'Customer not found',
            ];
        }

        // If all checks pass, return the customer data
        return [
            'code' => 200,
            'message' => 'Success',
            'data' => [
                'AccountID' => $result['mu_AccountID'],
                'Username' => $result['mu_Username'],
                'BalC1' => $result['BalC1'],
                'Telephone' => $result['Telephone']
            ]
        ];
    }
    function getServiceFee($categoryCode)
    {
        $feeValues = ['priceSell' => null, 'priceCost' => null];

        $categoryCodeMap = [
            'internet' => 'FEE_UI',
            'electricity' => 'FEE_UE',
            'cable' => 'FEE_UCTV'
        ];

        $lowercaseCategoryCode = strtolower($categoryCode);

        if (isset($categoryCodeMap[$lowercaseCategoryCode])) {
            $feeCode = $categoryCodeMap[$lowercaseCategoryCode];

            $stmt = $this->dbConnection->prepare("SELECT TOP 1 SellPrice, CostPrice FROM tblMobileFees WHERE Code = :code");
            $stmt->bindParam(':code', $feeCode, PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $feeValues['priceSell'] = $result['SellPrice'];
                $feeValues['priceCost'] = $result['CostPrice'];
            }
        }

        return $feeValues;
    }

    function customerBlockDebitCards($user, $accountno)
    {
        $query = "
            SELECT TOP 1 c.Accountid, c.customerName 
            FROM tblcustomers c
            JOIN tblMobileDebitCard d ON c.Accountid = d.AccountID
            WHERE c.Accountid = ? AND d.Active = 'Yes';
        ";

        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([$accountno]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            try {
                $this->dbConnection->beginTransaction();

                $insertQuery = "
                    INSERT INTO tblMobileATMZero (AcctNo, AcctName, Note, Cuser, Ddate)
                    VALUES (?, ?, ?, ?, GETDATE());
                ";
                $insertStmt = $this->dbConnection->prepare($insertQuery);
                $insertStmt->execute([
                    $customer['Accountid'],
                    $customer['customerName'],
                    $accountno,
                    $user['username']
                ]);

                $updateQuery = "
                    UPDATE tblMobileDebitCard 
                    SET Active = 'No' 
                    WHERE AccountID = ?;
                ";
                $updateStmt = $this->dbConnection->prepare($updateQuery);
                $updateStmt->execute([$accountno]);

                $this->dbConnection->commit();

                return [
                    'code' => 2025,
                    'message' => 'Debit card blocked successfully',
                    'data' => null,
                ];
            } catch (Exception $e) {
                $this->dbConnection->rollBack();

                return [
                    'code' => 1035,
                    'message' => 'Failed to block the debit card',
                    'data' => '',
                ];
            }
        }

        return [
            'code' => ErrorCodes::$FAIL_BLOCK_DEBIT_CARD_REQUEST[0],
            'message' => ErrorCodes::$FAIL_BLOCK_DEBIT_CARD_REQUEST[1],
            'data' => '',
        ];
    }

    function requestChequeBook($username, $numberOfCheques)
    {

        $query = "
            SELECT TOP 1 c.Accountid, c.Customername, m.Cuser 
            FROM tblcustomers c
            LEFT JOIN tblmobilecheque m ON c.Accountid = m.AccountID AND m.Cuser = ?
            WHERE c.Surname = ?;
        ";

        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([$username, $username]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer || !$customer['Accountid']) {
            return [
                'code' => 403,
                'message' => 'Customer not found',
                'data' => [],
            ];
        }

        if ($customer['Cuser']) {
            return [
                'code' => ErrorCodes::$FAIL_CHEQUE_BOOK_REQUEST_ALREADY_EXIST[0],
                'message' => ErrorCodes::$FAIL_CHEQUE_BOOK_REQUEST_ALREADY_EXIST[1],
                'data' => '',
            ];
        }

        try {
            $insertQuery = "
                INSERT INTO tblmobilecheque (AccountID, AccountName, ChequeLeaf, Ddate, Cuser)
                VALUES (?, ?, ?, GETDATE(), ?);
            ";
            $insertStmt = $this->dbConnection->prepare($insertQuery);
            $insertStmt->execute([
                $customer['Accountid'],
                $customer['Customername'],
                $numberOfCheques,
                $username
            ]);

            return [
                'code' => 2023,
                'message' => 'The cheque book request was successful',
                'data' => null,
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => 'Failed to request cheque book',
                'data' => '',
            ];
        }
    }


    function verifyCheque($username, $chequeNo)
    {
        $query = "
            SELECT TOP 1 c.Accountid, c.Customername, s.ChequeNo 
            FROM tblcustomers c
            LEFT JOIN tblmobilestopcheque s 
                ON c.Accountid = s.AccountID 
                AND s.Cuser = ? 
                AND s.ChequeNo = ?
            WHERE c.Surname = ?;
        ";

        $stmt = $this->dbConnection->prepare($query);
        $stmt->execute([$username, $chequeNo, $username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['Accountid']) {
            return [
                'code' => ErrorCodes::$FAIL_CUSTOMER_FOUND[0],
                'message' => ErrorCodes::$FAIL_CUSTOMER_FOUND[1],
                'data' => '',
            ];
        }

        if ($result['ChequeNo']) {
            return [
                'code' => ErrorCodes::$FAIL_CHEQUENO_STOP_PAYMENT_REQUEST_ALREADY_EXIST[0],
                'message' => ErrorCodes::$FAIL_CHEQUENO_STOP_PAYMENT_REQUEST_ALREADY_EXIST[1],
                'data' => [],
            ];
        }

        try {
            $insertQuery = "
                INSERT INTO tblmobilestopcheque (AccountID, AccountName, ChequeNo, Ddate, Cuser)
                VALUES (?, ?, ?, GETDATE(), ?);
            ";

            $insertStmt = $this->dbConnection->prepare($insertQuery);
            $insertStmt->execute([
                $result['Accountid'],
                $result['Customername'],
                $chequeNo,
                $username
            ]);

            return [
                'code' => 2022,
                'message' => 'The cheque stop payment request succeeded',
                'data' => [
                    'AccountID' => $result['Accountid'],
                    'AccountName' => $result['Customername'],
                    'ChequeNo' => (int) $chequeNo,
                    'Ddate' => date('Y-m-d H:i:s'),
                    'Cuser' => $username,
                ],
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => '',
            ];
        }
    }

    function dataCardWallet($username)
    {
        $query = "
            SELECT 
                Sno,
                AuthCode AS authorization_code,
                CardNo AS card_no,
                CardName AS account_name,
                CardBank AS bank,
                CardType AS card_type,
                CardChannel AS channel,
                CountryCode AS country_code,
                CardExpMonth AS exp_month,
                CardExpYear AS exp_year,
                (CASE WHEN CountryCode = 'Active' THEN 1 ELSE 0 END) AS reusable,
                CardSignature AS signature,
                CardCVV AS cvv,
                TransID AS reference
            FROM tblMobileCardVault
            WHERE Username = ?;
        ";

        try {
            $stmt = $this->dbConnection->prepare($query);
            $stmt->execute([$username]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($data) {
                $res = array_map(function ($row) {
                    return [
                        'id' => (int) $row['Sno'],
                        'authorization_code' => $row['authorization_code'],
                        'card_no' => $row['card_no'],
                        'account_name' => $row['account_name'],
                        'bank' => $row['bank'],
                        'card_type' => $row['card_type'],
                        'channel' => $row['channel'],
                        'country_code' => $row['country_code'],
                        'exp_month' => $row['exp_month'],
                        'exp_year' => $row['exp_year'],
                        'reusable' => (bool) $row['reusable'],
                        'signature' => $row['signature'],
                        'cvv' => $row['cvv'],
                        'reference' => $row['reference']
                    ];
                }, $data);

                return [
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_FETCH[1],
                    'data' => $res,
                ];
            } else {
                return [
                    'code' => 404,
                    'message' => 'Empty Card Wallet',
                    'data' => [],
                ];
            }
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => 'An error occurred while fetching the card wallet: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }
    function deleteCardWallet($username, $cardId)
    {
        $this->dbConnection->beginTransaction();
    
        try {
            $selectQuery = "
                SELECT *
                FROM tblMobileCardVault
                WHERE Username = ? AND Sno = ?
            ";
            $selectStmt = $this->dbConnection->prepare($selectQuery);
            $selectStmt->execute([$username, $cardId]);
            $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$row) {
                $this->dbConnection->rollBack();
                return [
                    'code' => 404,
                    'message' => 'Card wallet not found',
                    'data' => '',
                ];
            }
    
            $rowData = [
                'Username' => (string)$row['Username'],
                'CardNo' => (string)$row['CardNo'],
                'CardName' => (string)$row['CardName'],
                'CardExpMonth' => (string)$row['CardExpMonth'],
                'CardExpYear' => (int)$row['CardExpYear'],
                'CardCVV' => (int)$row['CardCVV'],
                'AuthCode' => (int)$row['AuthCode'],
                'CardType' => (string)$row['CardType'],
                'CardBank' => (string)$row['CardBank'],
                'CardChannel' => (string)$row['CardChannel'],
                'CardSignature' => (string)$row['CardSignature'],
                'CountryCode' => (string)$row['CountryCode'],
                'TransID' => (int)$row['TransID'],
                'Ddate' => $row['Ddate'] ,
                'Active' => (bool)$row['Active'],
                'Sno' => (int)$row['Sno']
            ];
    
            $deleteQuery = "
                DELETE FROM tblMobileCardVault
                WHERE Username = ? AND Sno = ?
            ";
            $deleteStmt = $this->dbConnection->prepare($deleteQuery);
            $deleteStmt->execute([$username, $cardId]);
    
            if ($deleteStmt->rowCount() > 0) {
                $this->dbConnection->commit();
                return [
                    'code' => 200,
                    'message' => 'Deleted Successfully',
                    'data' => $rowData,
                ];
            } else {
                $this->dbConnection->rollBack();
                return [
                    'code' => 404,
                    'message' => 'Card wallet not deleted',
                    'data' => '',
                ];
            }
        } catch (Exception $e) {
            $this->dbConnection->rollBack();
            return [
                'code' => 500,
                'message' => 'An error occurred while deleting the card wallet: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }


    function createCardWallet($username, $request)
    {
        $authcode = $request['authorizationCode'];
        $cardno = $request['last4'];

        $query = "
            SELECT TOP 1 c.Accountid, c.Customername, v.Sno 
            FROM tblcustomers c
            LEFT JOIN tblMobileCardVault v 
                ON c.Surname = v.Username 
                AND v.AuthCode = ? 
                AND v.CardNo = ?
            WHERE c.Surname = ?;
        ";

        try {
            $stmt = $this->dbConnection->prepare($query);
            $stmt->execute([$authcode, $cardno, $username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || !$result['Accountid']) {
                return [
                    'code' => 1038,
                    'message' => 'Customer not found',
                    'data' => '',
                ];
            }

            if ($result['Sno']) {
                return [
                    'code' => 1038,
                    'message' => 'The Card is already Added!',
                    'data' => '',
                ];
            }

            $insertQuery = "
                INSERT INTO tblMobileCardVault 
                    (Username, CardNo, CardExpMonth, CardExpYear, CardCVV, CardBank, CardChannel, CardSignature, CountryCode, CardName, TransID, Ddate, Active, AuthCode, CardType) 
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), 'ACTIVE', ?, ?);
            ";

            $insertStmt = $this->dbConnection->prepare($insertQuery);
            $insertStmt->execute([
                $username,
                $cardno,
                $request['expMonth'],
                $request['expYear'],
                $request['cvv'],
                $request['bank'],
                $request['channel'],
                $request['signature'],
                $request['countryCode'],
                $result['Customername'],
                $request['reference'],
                $request['authorizationCode'],
                $request['cardType'],
            ]);

            return [
                'code' => 200,
                'message' => 'The Card successfully Added!',
                'data' => [
                    'Username' => $username,
                    'CardNo' => $cardno,
                    'CardExpMonth' => $request['expMonth'],
                    'CardExpYear' => $request['expYear'],
                    'CardCVV' => $request['cvv'],
                    'CardBank' => $request['bank'],
                    'CardChannel' => $request['channel'],
                    'CardSignature' => $request['signature'],
                    'CountryCode' => $request['countryCode'],
                    'CardName' => $result['Customername'],
                    'TransID' => $request['reference'],
                    'Ddate' => date('Y-m-d H:i:s'),
                    'Active' => 'ACTIVE',
                ],
            ];
        } catch (Exception $e) {
            return [
                'code' => 500,
                'message' => 'An error occurred while adding the card: ' . $e->getMessage(),
                'data' => '',
            ];
        }
    }

    public function getCustomerDetails($username, $accountNo)
    {
        $stmt = $this->dbConnection->prepare("
            SELECT c.*, m.AccountID as userAcctId 
            FROM tblcustomers c
            JOIN tblMobileUsers m ON m.Username = :username
            WHERE c.Accountid = :accountNo
        ");
        $stmt->execute([':username' => $username, ':accountNo' => $accountNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCardVault($cardNo, $username)
    {
        $stmt = $this->dbConnection->prepare("SELECT * FROM tblMobileCardVault WHERE CardNo = :cardNo AND Username = :username");
        $stmt->execute([':cardNo' => $cardNo, ':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAddMoneyRecord($customerDetails, $request, $chargeResult, $bankId, $username)
    {
        $stmt = $this->dbConnection->prepare("
            INSERT INTO tblAddMoney (AcctName, CardNo, VinoTransCode, Amt, BankID, Charges, MerchantFee, AppFee, ClientResponse, Status, TransDate, AcctNo, Username)
            VALUES (:acctName, :cardNo, :vinoTransCode, :amt, :bankId, :charges, :merchantFee, :appFee, :clientResponse, :status, :transDate, :acctNo, :username)
        ");

        return $stmt->execute([
            ':acctName' => $customerDetails['Surname'],
            ':cardNo' => $request['cardNo'],
            ':vinoTransCode' => json_encode($chargeResult),
            ':amt' => $chargeResult['totalAmount'],
            ':bankId' => $bankId,
            ':charges' => $chargeResult['appFee'] + $chargeResult['preGeneratedFee'],
            ':merchantFee' => $chargeResult['merchantFee'],
            ':appFee' => $chargeResult['appFee'],
            ':clientResponse' => json_encode($chargeResult),
            ':status' => $chargeResult['status'],
            ':transDate' => date('Y-m-d H:i:s'),
            ':acctNo' => $request['accountNo'],
            ':username' => $username
        ]);
    }


    function gettingCustomerFAQ($username, $question, $accountNo)
    {
        $sql = "INSERT INTO tblMobileQuest (Username, AccountID, AccountName, Question, Ddate, Adate, Auser)
                SELECT :username, c.Accountid, c.customername, :question, GETDATE(), GETDATE(), GETDATE()
                FROM tblcustomers c
                WHERE c.Accountid = :accountNo";

        // Prepare the statement
        $stmt = $this->dbConnection->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':question', $question);
        $stmt->bindParam(':accountNo', $accountNo);

        // Execute the query
        $stmt->execute();

        // Get the last inserted ID
        $insertedId = $this->dbConnection->lastInsertId();
        $affectedRows = $stmt->rowCount();

        // Check if rows were affected
        if ($affectedRows > 0) {
            return [
                'code' => 200,
                'message' => 'Customer FAQ inserted successfully',
                'data' => [
                    'Sno' => $insertedId,
                    'Username' => $username,
                    'AccountID' => $accountNo,
                    'Question' => $question,
                    'Ddate' => date('Y-m-d H:i:s'),
                ],
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[0],
                'message' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[1],
                'data' => '',
            ];
        }
    }

    function customerQueryMessage($username, $request)
    {
        $accountno = $request['accountNo'];
        $message = $request['message'];
        $type = $request['type'];

        $sql = "INSERT INTO tblMobileMSG (Username, AccountID, AccountName, Message, Seen, Ddate, MType)
                SELECT :username, c.Accountid, c.customername, :message, 'UNSEEN', GETDATE(), :type
                FROM tblcustomers c
                WHERE c.Accountid = :accountno";

        // Prepare the statement
        $stmt = $this->dbConnection->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':accountno', $accountno);

        // Execute the query
        $stmt->execute();

        // Get the last inserted ID
        $insertedId = $this->dbConnection->lastInsertId();
        $affectedRows = $stmt->rowCount();

        // Check if rows were affected
        if ($affectedRows > 0) {
            $insertedData = [
                'id' => $insertedId,
                'Username' => $username,
                'AccountID' => $accountno,
                'Message' => $message,
                'Seen' => 'UNSEEN',
                'Ddate' => date('Y-m-d H:i:s'),
                'MType' => $type,
            ];

            return [
                'code' => 200,
                'message' => 'Customer Query saved successfully',
                'data' => $insertedData,
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[0],
                'message' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[1],
                'data' => '',
            ];
        }
    }



    function getBroadcastMessages()
    {
        $sql = "SELECT Sno, MsgNo, Msg FROM tblMobileBroadcast ORDER BY MsgNo DESC";

        // Prepare the statement
        $stmt = $this->dbConnection->prepare($sql);

        // Execute the query
        $stmt->execute();

        // Fetch all results as an associative array
        $data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'Sno' => (int) $row['Sno'],
                'MsgNo' => (string) $row['MsgNo'],
                'Msg' => $row['Msg'],
            ];
        }

        // Check if data is empty and return appropriate response
        if (empty($data)) {
            return [
                'code' => ErrorCodes::$FAIL_BROADCAST_MESSAGE_FOUND[0],
                'message' => ErrorCodes::$FAIL_BROADCAST_MESSAGE_FOUND[1],
                'data' => '',
            ];
        } else {
            return [
                'code' => 200,
                'message' => ErrorCodes::$SUCCESS_BROADCAST_MESSAGE_FOUND[1],
                'data' => $data,
            ];
        }
    }


    function requestLoan($request)
    {
        $sql = "SELECT TOP 1 c.Customername, c.Telephone 
        FROM tblcustomers AS c 
        WHERE c.Accountid = :accountid";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountid', $request['accountNo']);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $loanData = [
                'AcctNo' => $request['accountNo'],
                'AcctName' => $customer['Customername'],
                'Telephone' => $customer['Telephone'],
                'LoanAmt' => $request['loanAmount'],
                'LoanPurpose' => $request['purpose'],
                'LoanDurationType' => $request['durationType'],
                'LoanDuration' => $request['duration'],
                'PhotoImage' => $request['userPhoto'],
                // 'nicPhoto' => $request['nicPhoto'],
                'Guarantor1Name' => $request['Guarantor1Name'],
                'Guarantor1Add' => $request['Guarantor1Add'],
                'Guarantor1TelNo' => $request['Guarantor1TelNo'],
                'Guarantor2Name' => $request['Guarantor2Name'],
                'Guarantor2Add' => $request['Guarantor2Add'],
                'Guarantor2TelNo' => $request['Guarantor2TelNo'],
                'Cola1File' => $request['Cola1File'],
                'Cola2File' => $request['Cola2File'],
                'Cola3File' => $request['Cola3File'],
                'IDFile' => $request['nicPhoto'],
                'ReqDate' => date('Y-m-d H:i:s'),
                'TransID' => time(),
                'Cuser' => 'VINO',
                'Ddate' => date('Y-m-d H:i:s')
            ];

            $insertSql = "INSERT INTO tblMobileLoanNew 
                      (AcctNo, AcctName, Telephone, LoanAmt, LoanPurpose, LoanDurationType, LoanDuration, PhotoImage, 
                        Guarantor1Name, Guarantor1Add, Guarantor1TelNo, Guarantor2Name, Guarantor2Add, 
                       Guarantor2TelNo, Cola1File, Cola2File, Cola3File, IDFile, ReqDate, TransID, Cuser, Ddate)
                      VALUES 
                      (:AcctNo, :AcctName, :Telephone, :LoanAmt, :LoanPurpose, :LoanDurationType, :LoanDuration, 
                       :PhotoImage, :Guarantor1Name, :Guarantor1Add, :Guarantor1TelNo, :Guarantor2Name, 
                       :Guarantor2Add, :Guarantor2TelNo, :Cola1File, :Cola2File, :Cola3File, :IDFile, :ReqDate, 
                       :TransID, :Cuser, :Ddate)";

            $insertStmt = $this->dbConnection->prepare($insertSql);
            $insertStmt->execute($loanData);

            return [
                'code' => 200,
                'message' => 'Loan request created successfully',
                'data' => [
                    'AcctNo' => $loanData['AcctNo'],
                    'AcctName' => $loanData['AcctName'],
                    'LoanAmt' => $loanData['LoanAmt'],
                    'LoanPurpose' => $loanData['LoanPurpose'],
                    'ReqDate' => $loanData['ReqDate'],
                    'TransID' => (string) $loanData['TransID'],
                ],
            ];
        } else {
            return [
                'code' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[0],
                'message' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[1],
                'data' => '',
            ];
        }
    }


    function fetchPiggyAccounts($accountno)
    {
        // $acc=implode(',',$accountno);
        // $acc = '4041000016';
        // Query Should be run on Account Numbers not on Username optimize Query by Updating where in and accounts array
        $sql = "SELECT *
            FROM tblMobilePiggySavingsMaster 
            WHERE AccountNo = :accountno";

        $stmt = $this->dbConnection->prepare($sql);
        $stmt->bindParam(':accountno', $accountno);
        $stmt->execute();
        $piggyAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($piggyAccounts)) {
            $totalSavings = 0;
            $list = [];
            foreach ($piggyAccounts as $account) {
                $totalSavings += $account['CurrentBalance'];
                $list[] = [
                    'id' => (int)$account['Id'],
                    'funding_source' => $account['FundingSource'],
                    'source_account' => $account['AccountNo'],
                    'total_amount' => $this->round_to_2dp($account['TotalAmount']),
                    'current_balance' => $this->round_to_2dp($account['CurrentBalance']),
                    'cycle' => (int)$account['Cycle'],
                    'executed_cycle' => (int)$account['ExecutedCycle'],
                    'charges_early_withdrawal' => $this->round_to_2dp($account['ChargesEarlyWithdrawal']),
                    'terms' => $account['Terms'],
                    'maturity_date' => $account['MaturityDate'],
                    'title' => $account['Title'],
                    'created_at' => $account['CreatedAt'],
                    'amount_per_cycle' => $account['AmountPerCycle'],
                    'withdrawal_date' => $account['WithdrawalDate'],
                    'withdrawal_amount' => $this->round_to_2dp($account['WithdrawalAmount']),
                    'withdrawal_status' => (int)$account['WithdrawalStatus'],
                ];
            }

            return [
                'code' => 200,
                'message' => 'Piggy Account Fetch Successfully',
                'data' => [
                    'total_savings' => $totalSavings,
                    // 'query'=>$piggyAccounts,
                    'savings' => $list,
                ],
            ];
        } else {
            return [
                'code' => 404,
                'message' => 'No Piggy Account Found!!',
                'data' => [
                    'total_savings' => 0.0,
                    // 'query'=>$sql,
                    'savings' => null,
                ],
            ];
        }
    }



    function createPiggyEntity($user, $request)
    {
        $username = $user['username'];
        $accountId = $user['accountId'];
        $title = $request['title'];

        try {
            $query = "SELECT c.Accountid, c.Surname, p.id as piggy_id 
                  FROM tblcustomers c
                  LEFT JOIN tblMobilePiggySavingsMaster p 
                  ON p.Username = :username AND p.Title = :title
                  WHERE c.Accountid = :accountid";

            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':accountid', $accountId);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && is_null($result['piggy_id'])) {
                $now = date('Y-m-d H:i:s');
                $params = [
                    ':accountNo' => $result['Accountid'],
                    ':fundingSource' => $request['funding_source'],
                    ':totalAmount' => $request['amount'],
                    ':currentBalance' => $request['amount'],
                    ':cycle' => $request['cycle'],
                    ':executedCycle' => $request['cycle'],
                    ':chargesEarlyWithdrawal' => $result['Accountid'],
                    ':terms' => $request['terms'],
                    ':maturityDate' => $request['maturity_date'],
                    ':title' => $request['title'],
                    ':amountPerCycle' => $request['amount'],
                    ':withdrawalDate' => $now,
                    ':withdrawalAmount' => $request['amount'],
                    ':withdrawalStatus' => 0,
                    ':createdAt' => date('Y-m-d H:i:s'),
                    ':username' => $result['Surname'],
                    ':nextCycleDate' => $now,
                ];

                $insertQuery = "INSERT INTO tblMobilePiggySavingsMaster 
                            (AccountNo, FundingSource, TotalAmount, CurrentBalance, Cycle, ExecutedCycle, 
                             ChargesEarlyWithdrawal, Terms, MaturityDate, Title, AmountPerCycle, 
                             WithdrawalDate, WithdrawalAmount, WithdrawalStatus, CreatedAt, Username, NextCycleDate) 
                            VALUES 
                            (:accountNo, :fundingSource, :totalAmount, :currentBalance, :cycle, :executedCycle, 
                             :chargesEarlyWithdrawal, :terms, :maturityDate, :title, :amountPerCycle, 
                             :withdrawalDate, :withdrawalAmount, :withdrawalStatus, :createdAt, :username, :nextCycleDate)";

                $insertStmt = $this->dbConnection->prepare($insertQuery);
                $insertStmt->execute($params);

                return [
                    'code' => 200,
                    'message' => 'Piggy created successfully',
                    'data' => [
                        'AccountNo' => $result['Accountid'],
                        'FundingSource' => $request['funding_source'],
                        'TotalAmount' => $request['amount'],
                        'CurrentBalance' => $request['amount'],
                        'Cycle' => $request['cycle'],
                        'AmountPerCycle' => $request['amount'],
                        'ExecutedCycle' => $request['cycle'],
                        'Terms' => $request['terms'],
                        'MaturityDate' => $request['maturity_date'],
                        'Title' => $request['title'],
                        'CreatedAt' => $now,
                    ],
                ];
            } else {
                return [
                    'code' => 403,
                    'message' => 'Use a different name to create piggy savings.',
                    'data' => '',
                ];
            }
        } catch (PDOException $e) {
            return [
                'code' => 500,
                'message' => 'An error occurred while creating piggy savings: ' . $e->getMessage(),
                'data' => '',
            ];
        }
    }
// Incorrect Function
    function withdrawPiggy($accountId)
    {
        try {
            $currentYear = date('Y');

            $query = "SELECT TOP 180 
             MONTH(trnDate) as month, 
             YEAR(trnDate) as year,
             SUM(tblCustomerLedger.debit) as withdraw, 
             SUM(tblCustomerLedger.credit) as deposit
            FROM tblCustomerLedger
            WHERE Acctno = :accountId
            GROUP BY YEAR(trnDate), MONTH(trnDate)
            ORDER BY year DESC, month DESC";

            $stmt = $this->dbConnection->prepare($query);
            $stmt->bindParam(':accountId', $accountId);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($results as $row) {
                if ((int)$row['year'] === (int)$currentYear) {
                    $data[] = [
                        'month' => (int)$row['month'],
                        'withdraw' => (float)$row['withdraw'],
                        'deposit' => (float)$row['deposit'],
                    ];
                }
            }
            return [
                'code' => 200,
                'message' => 'Statement Summary',
                'data' => $data,
            ];
        } catch (PDOException $e) {
            return [
                'code' => 500,
                'message' => 'An error occurred while fetching statement summary: ' . $e->getMessage(),
                'data' => [],
            ];
        }
    }


    function fetchMessagesList($username, $page)
    {
        $dbConnection = $this->dbConnection; // Use your actual PDO connection instance
        $limit = 20;
        $offset = $page * $limit;

        // Fetch messages from tblMobileMSG with pagination using ROW_NUMBER()
        $query1 = "
            SELECT Sno, MType, Message, Ddate
            FROM (
                SELECT *, ROW_NUMBER() OVER (ORDER BY Sno DESC) AS RowNum
                FROM tblMobileMSG
                WHERE Username = :username
            ) AS Sub
            WHERE Sub.RowNum BETWEEN :startRow AND :endRow";
        $stmt1 = $dbConnection->prepare($query1);
        $stmt1->bindValue(':username', $username);
        $stmt1->bindValue(':startRow', $offset + 1, PDO::PARAM_INT);
        $stmt1->bindValue(':endRow', $offset + $limit, PDO::PARAM_INT);
        $stmt1->execute();
        $messageQuestions = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        // Fetch messages from tblMobileQuest with pagination using ROW_NUMBER()
        $query2 = "
            SELECT Sno, Question, Answer, Ddate
            FROM (
                SELECT *, ROW_NUMBER() OVER (ORDER BY Sno DESC) AS RowNum
                FROM tblMobileQuest
                WHERE Username = :username
            ) AS Sub
            WHERE Sub.RowNum BETWEEN :startRow AND :endRow";
        $stmt2 = $dbConnection->prepare($query2);
        $stmt2->bindValue(':username', $username);
        $stmt2->bindValue(':startRow', $offset + 1, PDO::PARAM_INT);
        $stmt2->bindValue(':endRow', $offset + $limit, PDO::PARAM_INT);
        $stmt2->execute();
        $messageQuestions2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Count total rows in tblMobileMSG
        $countQuery1 = "SELECT COUNT(*) as total FROM tblMobileMSG WHERE Username = :username";
        $countStmt1 = $dbConnection->prepare($countQuery1);
        $countStmt1->bindValue(':username', $username);
        $countStmt1->execute();
        $totalRow = $countStmt1->fetch(PDO::FETCH_ASSOC)['total'];

        // Count total rows in tblMobileQuest
        $countQuery2 = "SELECT COUNT(*) as total FROM tblMobileQuest WHERE Username = :username";
        $countStmt2 = $dbConnection->prepare($countQuery2);
        $countStmt2->bindValue(':username', $username);
        $countStmt2->execute();
        $totalRow2 = $countStmt2->fetch(PDO::FETCH_ASSOC)['total'];

        // Process messages from tblMobileMSG
        $transactionHistory = array_map(function ($row) {
            return [
                'reference' => (int)$row['Sno'],
                'type' => !empty($row['MType']) ? $row['MType'] : "Suggestion",
                'title' => $row['Message'],
                'content' => $row['Message'],
                'date' => date('Y-m-d', strtotime($row['Ddate'])),
                'time' => date('H:i:s', strtotime($row['Ddate'])),
            ];
        }, $messageQuestions);

        // Process messages from tblMobileQuest (without MType)
        $transactionHistory2 = array_map(function ($row) use ($totalRow) {
            return [
                'reference' => (int)$row['Sno'] + $totalRow, // Offset the reference by the total rows in tblMobileMSG
                'type' => "Suggestion", // Default to "Suggestion" since MType does not exist
                'title' => $row['Question'],
                'content' => $row['Answer'],
                'date' => date('Y-m-d', strtotime($row['Ddate'])),
                'time' => date('H:i:s', strtotime($row['Ddate'])),
            ];
        }, $messageQuestions2);

        // Merge both transaction histories
        $mergedTransactionHistory = array_merge($transactionHistory, $transactionHistory2);
        usort($mergedTransactionHistory, function ($a, $b) {
            $dateTimeA = strtotime($a['date'] . ' ' . $a['time']);
            $dateTimeB = strtotime($b['date'] . ' ' . $b['time']);
            return $dateTimeB - $dateTimeA;
        });

        if (empty($mergedTransactionHistory)) {
            return [
                'code' => ErrorCodes::$FAIL_ACCOUNT_TRANSACTION_FOUND[0],
                'data' => ErrorCodes::$FAIL_ACCOUNT_TRANSACTION_FOUND[1],
            ];
        }

        $data = [
            'transactionHistory' => $mergedTransactionHistory,
            'totalRow' => $totalRow + $totalRow2,
        ];

        return [
            'code' => 200,
            'data' => $data,
        ];
    }
}
