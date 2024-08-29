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

        $query = "SELECT `Id`, `Name`, `AccountNo`, `BankCode`, `Username`
                  FROM `tblMobileBeneficiaries`
                  WHERE `Username` = ?
                  ORDER BY `Id` DESC";

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
}
