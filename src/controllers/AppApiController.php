<?php

require 'BankDbController.php';
require 'ConfigController.php';
require 'MobileLogController.php';

// Thrid Party Controllers
require 'ThirdPartyControllers/CharmsApiController.php';
require 'ThirdPartyControllers/CoreBankController.php';
require 'ThirdPartyControllers/PayStackController.php';
require 'ThirdPartyControllers/VtPassController.php';

// Transaction Controller
require 'TransactionController/TopUpController.php';
require 'TransactionController/FundTransferController.php';
require 'TransactionController/UtilityController.php';
require 'TransactionController/AddFundsCardWalletController.php';


// require '../models/UtilityDemo.php';
require __DIR__ . '/../models/UtilityDemo.php';



use Rakit\Validation\Validator;

class AppApiController
{
    public function registerNewCustomer($bankid, $request)
    {
        try {
            $data = [
                'username' => $request['username'] ?? null,
                'password' => $request['password'] ?? null,
                'surname' => $request['surname'] ?? null,
                'otherName' => $request['otherName'] ?? null,
                'gender' => $request['gender'] ?? null,
                'dob' => $request['dob'] ?? null,
                'nationality' => $request['nationality'] ?? null,
                'residentialAddress' => $request['residentialAddress'] ?? null,
                'contact' => $request['contact'] ?? null,
                'email' => $request['email'] ?? null,
                'bvn' => $request['bvn'] ?? null,
                'nin' => $request['nin'] ?? null,
                'occupation' => $request['occupation'] ?? null,
                'accountType' => $request['accountType'] ?? null,
                'userFileId' => $request['userFileId'] ?? null,
                'signatureFileId' => $request['signatureFileId'] ?? null,
                'nicFileId' => $request['nicFileId'] ?? null,
            ];

            $rules = [
                'username' => 'required|min:4',
                'password' => 'required|min:5',
                'surname' => 'required',
                'otherName' => 'nullable',
                'gender' => 'required',
                'dob' => 'required|date:d/m/Y|before:18 years ago',
                'nationality' => 'required',
                'residentialAddress' => 'required',
                'contact' => 'required',
                'email' => 'required|email',
                'bvn' => '',
                'nin' => '',
                'occupation' => '',
                'accountType' => 'required',
                'userFileId' => 'nullable',
                'signatureFileId' => 'nullable',
                'nicFileId' => 'nullable',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));

            $newCustomer = $bankDbConnection->registerNewCustomer($data);

            if ($newCustomer['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_USER_CREATED[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_USER_CREATED[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $newCustomer['code'],
                    'code' => 203,
                    'message' => $newCustomer['message'],
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function registerExistCustomer($bankid, $request)
    {
        try {
            $data = [
                'username' => $request['username'] ?? null,
                'accountID' => $request['accountID'] ?? null,
                'internetID' => $request['internetID'] ?? null,
                'password' => $request['password'] ?? null,
            ];

            $rules = [
                'username' => 'required|min:4',
                'password' => 'required|min:5',
                'accountID' => 'required',
                'internetID' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $regExistCustomer = $bankDbConnection->registerExistCustomerBank($data);

            if ($regExistCustomer['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_USER_CREATED[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_USER_CREATED[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $regExistCustomer['code'],
                    'code' => 203,
                    'message' => $regExistCustomer['message'],
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }



    public function currentUser($bankid, $user)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $customerInfo = $bankDbConnection->accountEnquiry($user['accountId']);
            $accounts = $bankDbConnection->getAllcustomerAccounts($user['username'], ['AccountID', 'AType', 'AccountType', 'BalC1', 'LastD', 'LastW', 'BalC2', 'BalL1']);
            $statistics = $bankDbConnection->statement_summary($accounts['accounts']);
            $loanData = $bankDbConnection->loanDataDetails($accounts['accounts']);

            if ($customerInfo['code'] == 200 && $accounts['code'] == 200 && $statistics['code'] == 200 && $loanData['code'] == 200) {
                $response = [
                    'id' => (int) $customerInfo['data']['id'],
                    'pinCode' => $customerInfo['data']['pinCode'],
                    'customer' => $customerInfo['data']['customer'],
                    'accounts' => $accounts['accountdata'],
                    'statistics' => [
                        'lastTransactionForAllAccount' => $statistics['data']
                    ],
                    'loans' => $loanData['data'],
                    'notification' => 0,
                ];

                sendCustomResponse(ErrorCodes::$SUCCESS_USER_FOUND[1], $response, ErrorCodes::$SUCCESS_USER_FOUND[0], 200);
            } else {
                sendCustomResponse('Error in account enquiry or accounts retrieval', null, 500, 500);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function currentUserAccountBalance($bankid, $user)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $customerInfo = $bankDbConnection->getCustomerByAccountNo2($user['accountId']);
            $accounts = $bankDbConnection->getAllcustomerAccounts($user['username'], ['AccountID', 'AType', 'AccountType', 'BalC1', 'LastD', 'LastW', 'BalC2', 'BalL1'], $balance = false);  // Done Could be improve by Single Call in Sub function


            if (!is_null($customerInfo) && $accounts['code'] == 200) {
                $response = [
                    'customer' => $customerInfo,
                    'accounts' => $accounts['accountdata'],
                ];


                sendCustomResponse(ErrorCodes::$SUCCESS_USER_FOUND[1], $response, ErrorCodes::$SUCCESS_USER_FOUND[0], 200);
            } else {
                sendCustomResponse('Error in account enquiry or accounts retrieval', null, 500, 500);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function userPinCreate($bankid, $request, $user)
    {

        try {
            $data = [
                'pin' => $request['pin'] ?? null,
            ];

            $rules = [
                'pin' => 'required|integer|min:4',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $createUserPin = $bankDbConnection->createUserPin($request['pin'], $user['username']);

            if ($createUserPin['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_PIN_CREATED[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_PIN_CREATED[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $createUserPin['code'],
                    'code' => 201,
                    'message' => $createUserPin['message'],
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    public function userPinUpdate($bankid, $request, $user)
    {

        try {
            $data = [
                'oldPin' => $request['oldPin'] ?? null,
                'newPin' => $request['newPin'] ?? null,
            ];

            $rules = [
                'oldPin' => 'required|integer|min:4',
                'newPin' => 'required|integer|min:4|different:oldPin',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $createUserPin = $bankDbConnection->updateUserPin($request, $user['username']);

            if ($createUserPin['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION_PIN_CHANGE[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION_PIN_CHANGE[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $createUserPin['code'],
                    'code' => 201,
                    'message' => $createUserPin['message'],
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    public function userPasswordUpdate($bankid, $request)
    {

        try {
            $data = [
                'username' => $request['username'] ?? null,
                'existingPassword' => $request['existingPassword'] ?? null,
                'newPassword' => $request['newPassword'] ?? null,
                'confirmPassword' => $request['confirmPassword'] ?? null,
            ];

            $rules = [
                'username' => 'required',
                'existingPassword' => 'required',
                'newPassword' => 'required',
                'confirmPassword' => 'required|same:newPassword',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => 'VALIDATION_ERROR',
                    'data' => $validation->errors()->toArray(),
                    'dcode' => 403,
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $updateUserPassword = $bankDbConnection->updateUserPassword($request);

            if ($updateUserPassword['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_PASSWORD_CHANGE[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_PASSWORD_CHANGE[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $updateUserPassword['code'],
                    'code' => 201,
                    'message' => $updateUserPassword['message'],
                    'data' => null
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function userPinVerify($bankid, $request, $user)
    {

        try {
            $data = [
                'pin' => $request['pin'] ?? null,
            ];

            $rules = [
                'pin' => 'required|min:4',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $verifyUserPin = $bankDbConnection->userVerifyPin($request['pin'], $user['username']);

            if ($verifyUserPin['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_PIN_VALIDATION[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_PIN_VALIDATION[1],
                    'data' => ErrorCodes::$SUCCESS_PIN_VALIDATION[1]
                ];
            } else {
                return [
                    'dcode' => $verifyUserPin['code'],
                    'code' => 201,
                    'message' => $verifyUserPin['message'],
                    'data' => $verifyUserPin['message']
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function getAccountType($bankid)
    {
        try {
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $accountType = $bankDbConnection->accountType();

            if ($accountType['code'] == 200) {
                $data = [
                    'accountTypes' => $accountType['data']
                ];
                $message = ErrorCodes::$SUCCESS_BANK_ACCOUNT_FOUND[1];
                $dcode = ErrorCodes::$SUCCESS_BANK_ACCOUNT_FOUND[0];
                $code = 200;
                return sendCustomResponse($message, $data, $dcode, $code);
            } else {
                $errorRes = $accountType;
                $message = $errorRes['message'];
                $data =  $errorRes['message'];
                $dcode = $errorRes['code'];
                $code = 404;
                return sendCustomResponse($message, $data, $dcode, $code);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getConfig($bankid, $queryParams)
    {
        $configConnection = new ConfigController(Database::getConnection('mysql'));
        $localDbConnection = new LocalDbController(Database::getConnection('mysql'));

        $isAll = (isset($queryParams['all']) && $queryParams['all'] !== 'false');
        $config = $configConnection->getConfigKeyValueData($bankid, 'config_update');
        $data = [
            'title' => $configConnection->getConfigKeyValue($bankid, 'title'),
            'version' => $configConnection->getConfigKeyValue($bankid, 'version'),
            'name' => $configConnection->getConfigKeyValue($bankid, 'app_name'),
            'app_logo' => $configConnection->getConfigKeyValue($bankid, 'app_logo'),
            'app_url' => $configConnection->getConfigKeyValue($bankid, 'app_url'),
            'force_update' => $configConnection->getConfigKeyValue($bankid, 'force_update'),
            'config_update' => $config['value'],
            'config_timestamp' => $config['updated_at']
        ];

        if ($isAll) {
            // $dataVtPass = $configConnection->getVtPassData()['data'];
            // $data['networks'] = $dataVtPass['networks'];
            // $data['utilites'] = $dataVtPass['utilites'];
            // $data['networks'] = $configConnection->getTelcoNetworks()['data'];
            // $data['utilites'] = $configConnection->getUtilities($bankid, 'all')['data'];
            // $data['bank_list'] = $configConnection->getBankListWithoutAuth($bankid)['data'];

            $configData = $localDbConnection->fetchResponseData();
            $data['networks'] = $configData['networks'];
            $data['utilites'] = $configData['utilities'];

            $bankListData = $localDbConnection->fetchBankListData();
            $data['bank_list'] = $bankListData;
        }

        $message = ErrorCodes::$SUCCESS_FETCH[1];
        $dcode = ErrorCodes::$SUCCESS_FETCH[0];
        return sendCustomResponse($message, $data, $dcode, 200);
    }

    public function getTelecoNetworks($bankid)
    {
        $configConnection = new ConfigController(Database::getConnection('mysql'));
        $response = $configConnection->getTelcoNetworks();
        return [
            'message' => $response['message'],
            'data' => $response['data'],
            'dcode' => $response['code'],
            'code' => 200
        ];
    }



    public function resetPassword($bankid, $request)
    {
        try {
            $data = [
                'contactNumber' => $request['contactNumber'] ?? null,
            ];

            $rules = [
                'contactNumber' => 'required|regex:/^\d{11}$/'
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_CONTACT_NUMBER[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_CONTACT_NUMBER[0],
                    'code' => 422,
                ];
            }
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $passwordReset = $bankDbConnection->resetUserPassword($request['contactNumber']);

            if ($passwordReset['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_PASSWORD_RESET[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_PASSWORD_RESET[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $passwordReset['code'],
                    'code' => 404,
                    'message' => $passwordReset['message'],
                    'data' => $passwordReset['message']
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getAccountInfo($bankid, $request)
    {
        try {
            $data = [
                'bankCode' => $request['bankCode'] ?? null,
                'accountNo' => $request['accountNo'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'bankCode' => 'required'
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => 'VALIDATION_ERROR',
                    'data' => $validation->errors()->toArray(),
                    'dcode' => 403,
                    'code' => 422,
                ];
            }

            $localDbConnection = new LocalDbController(Database::getConnection('mysql'));
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));

            $bankCodeExist = $localDbConnection->bankCodeCheck($request);

            if ($bankid == $request['bankCode']) {
                if ($bankCodeExist['code'] == 200) {
                    $accountinfo = $bankDbConnection->getCustomerByAccountNo2($request['accountNo']);
                    $response = [
                        'destinationinstitutioncode' => $request['bankCode'],
                        'accountnumber' => $accountinfo['Accountid'],
                        'accountname' => $accountinfo['customerName'],
                        'bvn' => $accountinfo['bvn'],
                    ];
                    return [
                        'message' => ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[1],
                        'data' =>  $response,
                        'dcode' => ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[0],
                        'code' => 200
                    ];
                } else {
                    $accountinfo = $bankDbConnection->accountInfo($request);
                    if ($accountinfo['code'] == 200) {
                        $response = [
                            'destinationinstitutioncode' => $request['bankCode'],
                            'accountnumber' => $accountinfo['data']['Accountid'],
                            'accountname' => $accountinfo['data']['Customername'],
                            'bvn' => $accountinfo['data']['BVN'],
                        ];

                        return [
                            'message' => ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[1],
                            'data' =>  $response,
                            'dcode' => ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[0],
                            'code' => 200
                        ];
                    } else {
                        return [
                            'message' => $accountinfo['message'],
                            'data' =>  $accountinfo['message'],
                            'dcode' => $accountinfo['code'],
                            'code' => 404
                        ];
                    }
                }
            } else {
                $charms = new CharmsAPI();
                $accountinfo2 = $charms->findAccount($request['accountNo'], $request['bankCode']);
                if ($accountinfo2['data']['requestSuccessful']) {
                    return [
                        'message' => $accountinfo2['message'],
                        'data' =>  $accountinfo2['data']['responseData'],
                        'dcode' => ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[0],
                        'code' => 200
                    ];
                } else {
                    return [
                        'message' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[1],
                        'data' =>  $accountinfo2['data']['message'],
                        'dcode' => ErrorCodes::$FAIL_ACCOUNT_HOLDER_FOUND[0],
                        'code' => 400
                    ];
                }
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function uploadImage($bankid, $files)
    {
        try {
            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }

            $file = $_FILES['file'];

            // Basic file validation
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed with error code: ' . $file['error']);
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp'];

            error_log("Received file type: " . $file['type']);

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            error_log("Detected MIME type: " . $mimeType);

            if (!in_array($mimeType, $allowedMimes)) {
                throw new Exception('Invalid file type: ' . $mimeType);
            }

            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('File is not a valid image');
            }

            if ($file['size'] > 2048000) { // 2MB limit
                throw new Exception('File size exceeds limit');
            }

            // Generate unique filename
            $filename = time();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageName = $filename . '.' . $extension;

            // Define upload path using absolute path
            $uploadDir = __DIR__ . '/images/';
            $uploadPath = $uploadDir . $imageName;

            // Ensure the upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);  // Create the directory if it doesn't exist
            }

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $response = [
                    'fileId' => $imageName,
                    'type' => $mimeType,
                ];

                return sendCustomResponse(ErrorCodes::$SUCCESS_FILE_UPLOADED[1], $response, ErrorCodes::$SUCCESS_FILE_UPLOADED[0], 200);
            } else {
                return sendCustomResponse(ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[1], null, ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[0], 404);
            }
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function getTransaction($bankid, $request)
    {
        try {

            $data = [
                'accountNo' => $request['accountNo'] ?? null,
                'page' => $request['page'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'page' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            }

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $getTransactionList = $bankDbConnection->requestGetTransaction($request['accountNo'], $request['page']);

            if ($getTransactionList['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_ACCOUNT_TRANSACTION_FOUND[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_ACCOUNT_TRANSACTION_FOUND[1],
                    'data' => $getTransactionList['data']
                ];
            } else {
                return [
                    'dcode' => $getTransactionList['code'],
                    'code' => 403,
                    'message' => $getTransactionList['data'],
                    'data' => $getTransactionList['data']
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function getBankList($bankid)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $getBankList = $bankDbConnection->getAllbanks($bankid);

            if ($getBankList['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_AVAILABLE_BANK_LIST[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_AVAILABLE_BANK_LIST[1],
                    'data' => $getBankList['data']
                ];
            } else {
                return [
                    'dcode' => $getBankList['code'],
                    'code' => 403,
                    'message' => $getBankList['message'],
                    'data' => []
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function verifyMeterNo($bankid, $request)
    {
        try {

            $dataRequest = [
                'serviceID' => $request['serviceID'] ?? null,
                'billersCode' => $request['billersCode'] ?? null,
                'variation_code' => $request['variation_code'] ?? null,
            ];

            $rules = [
                'serviceID' => 'required',
                'billersCode' => 'required',
                'variation_code' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            }

            $vtPassConnection = new VTPassController();
            $getResult = $vtPassConnection->verifyMeterNumber($request);

            if ($getResult['code'] === '000') {
                if ($getResult['content']['WrongBillersCode'] === false) {
                    $message = ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[1];
                    $dcode = ErrorCodes::$SUCCESS_FETCH_ACCOUNT_INFO[0];
                    $code = 200;
                    return sendCustomResponse($message, $getResult['content'], $dcode, $code);
                } else {
                    $message = ErrorCodes::$FAIL_TRANSACTION[1];
                    $dcode = ErrorCodes::$FAIL_TRANSACTION[0];
                    $code = 401;
                    return sendCustomResponse($message, $getResult['content'], $dcode, $code);
                }
            } else {
                $message = ErrorCodes::$FAIL_API_ERROR[1];
                $dcode = ErrorCodes::$FAIL_API_ERROR[0];
                $code = 401;
                return sendCustomResponse($message, $getResult, $dcode, $code);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getCustomerDebitCards($bankid, $user)
    {
        try {
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $Debitcardinfo = $bankDbConnection->debitcards($user);
            if ($Debitcardinfo['code'] == 2024) {
                $data = $Debitcardinfo['data'];
                $message = ErrorCodes::$SUCCESS_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABLE[1];
                $dcode = ErrorCodes::$SUCCESS_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABLE[0];
                $code = 200;
                return sendCustomResponse($message, $data, $dcode, $code);
            } else {
                $message = ErrorCodes::$FAIL_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABILITY[1];
                $dcode = ErrorCodes::$FAIL_REQUEST_CUSTOMER_DEBIT_CARDS_AVAILABILITY[0];
                $code = 200;
                return sendCustomResponse($message, [], $dcode, $code);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function getUtilities()
    {
        try {
            $utilitydata = array();
            array_push($utilitydata, [
                "catgeoryName" => "Internet  Data Bundles",
                "categoryCode" => "Internet",
            ]);
            array_push($utilitydata, [
                "catgeoryName" => "Television Cable Subscription",
                "categoryCode" => "Cable",
            ]);
            array_push($utilitydata, [
                "catgeoryName" => "Electricity Bills",
                "categoryCode" => "Electricity",
            ]);

            $message = ErrorCodes::$SUCCESS_FETCH_UTILITIES[1];
            $dcode = ErrorCodes::$SUCCESS_FETCH_UTILITIES[0];
            $code = 200;
            return sendCustomResponse($message, $utilitydata, $dcode, $code);
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function requestTopUpMobile($bankid, $user, $request)
    {
        $dataRequest = [
            'amount' => $request['amount'] ?? null,
            'networkCode' => $request['networkCode'] ?? null,
            'phoneNo' => $request['phoneNo'] ?? null,
            'srcAccount' => $request['srcAccount'] ?? null,
        ];

        $rules = [
            'amount' => 'required',
            'networkCode' => 'required',
            'phoneNo' => 'required',
            'srcAccount' => 'required',
        ];

        $validator = new Validator();
        $validation = $validator->make($dataRequest, $rules);

        $validation->validate();

        if ($validation->fails()) {
            return [
                'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                'data' => $validation->errors()->toArray(),
                'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                'code' => 422,
            ];
        };

        $topUpController = new TopUpMobileController($bankid);
        return $topUpController->topUpMobile($bankid, $user, $request);
    }


    public function requestFundTransfer($bankid, $user, $request)
    {
        $dataRequest = [
            'amount' => $request['amount'] ?? null,
            'beneficiaryAccountNo' => $request['beneficiaryAccountNo'] ?? null,
            'beneficiaryBankCode' => $request['beneficiaryBankCode'] ?? null,
            'beneficiaryName' => $request['beneficiaryName'] ?? null,
            'sourceAccount' => $request['sourceAccount'] ?? null,
            'note' => $request['note'] ?? null,
        ];

        $rules = [
            'amount' => 'required|numeric|min:10',
            'beneficiaryAccountNo' => 'required',
            'beneficiaryBankCode' => 'required',
            'beneficiaryName' => 'required',
            'note' => 'nullable',
            'sourceAccount' => 'required',
        ];

        $validator = new Validator();
        $validation = $validator->make($dataRequest, $rules);

        $validation->validate();

        if ($validation->fails()) {
            return [
                'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                'data' => $validation->errors()->toArray(),
                'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                'code' => 422,
            ];
        };

        // $note = "Transfer fund request of " . $request['amount'] . ' on account number: ' . $request['beneficiaryAccountNo'];

        $logData = [
            'bankId' => $bankid,
            'username' => $user['username'],
            'account_holder' => $user['username'],
            'srcAccount' => $request['sourceAccount'],
            'amount' => $request['amount'],
            // 'note' => $note,
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => 'Fund Transfer',
            'request' => $request,
        ];

        $fundTransferController = new FundTransferController($bankid);
        return $fundTransferController->fundTransferLogic($bankid, $user, $request, $logData);
    }

    public function postUtilities($user, $bankid, $request)
    {
        try {
            $data = [
                'categoryCode' => $request['categoryCode'] ?? null,
                'customerId' => $request['customerId'] ?? null,
                'packageCode' => $request['packageCode'] ?? null,
                'price' => $request['price'] ?? null,
                'serviceProvider' => $request['serviceProvider'] ?? null,
                'srcAccount' => $request['srcAccount'] ?? null,
            ];

            $rules = [
                'categoryCode' => 'required',
                'customerId' => 'required',
                'packageCode' => 'required',
                'price' => 'required',
                'serviceProvider' => 'required',
                'srcAccount' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            }

            $logData = [
                'bankId' => $bankid,
                'username' => $user['username'],
                'account_holder' => $user['username'],
                'srcAccount' => $request['srcAccount'],
                'amount' => $request['price'],
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'Utility',
                'request' => $request,
            ];

            $utilityController = new UtilityController($bankid);
            return $regExistCustomer = $utilityController->purchaseUtilityServices($user, $bankid, $request, $logData);

            // if ($regExistCustomer['code'] == 200) {
            //     return [
            //         'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
            //         'code' => 200,
            //         'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
            //         'data' => $data['data']
            //     ];
            // } else {
            //     return [
            //         'dcode' => $regExistCustomer['code'],
            //         'code' => 200,
            //         'message' => $regExistCustomer['message'],
            //         'data' => $data['data']
            //     ];
            // }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }


    public function fetchLiveConfigData($bankid)
    {
        $configConnection = new ConfigController(Database::getConnection('mysql'));
        $localDbConnection = new LocalDbController(Database::getConnection('mysql'));

        $data['networks'] = $configConnection->getTelcoNetworks()['data'];
        $data['utilites'] = $configConnection->getUtilities($bankid, 'all')['data'];
        $data['bank_list'] = $configConnection->getBankListWithoutAuth($bankid)['data'];

        // return sendCustomResponse('', $data, 200,200);

        $liveConfigDataUpdate = $localDbConnection->updateConfigLiveData($data['networks'], $data['utilites'], $data['bank_list']);

        if ($liveConfigDataUpdate['code'] == 200) {
            $data = 'Success, Data insertion';
            $message = 'Live Data Update Success';
            $dcode = 200;
            $code = 200;
            return sendCustomResponse($message, $data, $dcode, $code);
        }
    }

    public function getBeneficiariesList($user, $bankid)
    {
        try {
            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $beneficiaries = $bankDbConnection->beneficiaries($user['username']);

            if ($beneficiaries['code'] == 200 && !empty($beneficiaries['data'])) {
                $benes = $beneficiaries['data'];
                $response = [];
                foreach ($benes as $bene) {
                    $response[] = [
                        'id' => (int) $bene['Id'],
                        'name' => $bene['Name'],
                        'accountNo' => $bene['AccountNo'],
                        'bankCode' => $bene['BankCode'],
                        'username' => $bene['Username'],
                    ];
                }
            } else {
                $response = [];
            }
            return [
                'message' => ErrorCodes::$SUCCESS_FETCH_BENEFICIARIES[1],
                'dcode' => ErrorCodes::$SUCCESS_FETCH_BENEFICIARIES[0],
                'data' => $response,
                'code' => 200,
            ];
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function deleteBeneficiaries($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'id' => $request['id'] ?? null,
            ];
            $rules = [
                'id' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $beneficiaries = $bankDbConnection->delBeneficiaries($user['username'], $request['id']);

            if ($beneficiaries['code'] == 200) {
                return [
                    'message' => 'Beneficiary Account Removed Successfully',
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => $beneficiaries['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => $beneficiaries['message'],
                    'dcode' => $beneficiaries['data'],
                    'data' => $beneficiaries['code'],
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function blockCustomerDebitCard($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'accountNo' => $request['accountNo'] ?? null,
            ];
            $rules = [
                'accountNo' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $Customerinfo = $bankDbConnection->CustomerBlockdebitcards($user['username'], $request['accountNo']);

            if ($Customerinfo['code'] == 2025) {
                return [
                    'message' => ErrorCodes::$SUCCESS_DEBIT_CARD_BLOCK_REQUEST[1],
                    'dcode' => ErrorCodes::$SUCCESS_DEBIT_CARD_BLOCK_REQUEST[0],
                    'data' => $Customerinfo['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_BLOCK_DEBIT_CARD_REQUEST[1],
                    'dcode' => ErrorCodes::$FAIL_BLOCK_DEBIT_CARD_REQUEST[0],
                    'data' => [],
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function requestChequeBook($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'numberOfCheques' => $request['numberOfCheques'] ?? null,
            ];
            $rules = [
                'numberOfCheques' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $requestChequeBook = $bankDbConnection->requestChequeBook($user['username'], $request['numberOfCheques']);

            if ($requestChequeBook['code'] == 2023) {
                return [
                    'message' => ErrorCodes::$SUCCESS_REQUESTING_CHEQUE_BOOK[1],
                    'dcode' => ErrorCodes::$SUCCESS_REQUESTING_CHEQUE_BOOK[0],
                    'data' => [],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => $requestChequeBook['message'],
                    'dcode' => $requestChequeBook['code'],
                    'data' => [],
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function requestChequeStopPayment($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'chequeNo' => $request['chequeNo'] ?? null,
            ];
            $rules = [
                'chequeNo' => 'required|regex:/^\d{6}$/'
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_CHEQUENO_VALIDATE[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $verifyCheque = $bankDbConnection->verifyCheque($user['username'], $request['chequeNo']);

            if ($verifyCheque['code'] == 2022) {
                return [
                    'message' => ErrorCodes::$SUCCESS_REQUESTING_CHEQUE_STOP_PAYMENT[1],
                    'dcode' => ErrorCodes::$SUCCESS_REQUESTING_CHEQUE_STOP_PAYMENT[0],
                    'data' => [],
                    // 'data' => $verifyCheque['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => $verifyCheque['message'],
                    'dcode' => $verifyCheque['code'],
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function getCardWallet($user, $bankid)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $data = $bankDbConnection->dataCardWallet($user['username']);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_FETCH[1],
                    'dcode' => ErrorCodes::$SUCCESS_FETCH[0],
                    'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => 'Empty Card Wallet',
                    'dcode' => '400',
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }
    // Delete might change to POST method 
    public function deleteCardWallet($user, $bankid, $request)
    {
        try {

            $dataRequest = [
                'Sno' => $request['id'] ?? null,
            ];
            $rules = [
                'Sno' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };


            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $data = $bankDbConnection->deleteCardWallet($user['username'], $request['Sno']);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_CARD_WALLET_DELETED[1],
                    'dcode' => ErrorCodes::$SUCCESS_CARD_WALLET_DELETED[0],
                    // 'data' => [],
                    'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_CARD_WALLET_DELETED[1],
                    'dcode' => ErrorCodes::$FAIL_CARD_WALLET_DELETED[0],
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function postCardWallet($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'authorizationCode' => $request['authorizationCode'] ?? null,
                'cardType' => $request['cardType'] ?? null,
                'last4' => $request['last4'] ?? null,
                'expMonth' => $request['expMonth'] ?? null,
                'expYear' => $request['expYear'] ?? null,
                'bin' => $request['bin'] ?? null,
                'bank' => $request['bank'] ?? null,
                'channel' => $request['channel'] ?? null,
                'signature' => $request['signature'] ?? null,
                'reusable' => $request['reusable'] ?? null,
                'countryCode' => $request['countryCode'] ?? null,
                'accountName' => $request['accountName'] ?? null,
                'cvv' => $request['cvv'] ?? null,
                'reference' => $request['reference'] ?? null,
            ];

            $rules = [
                'authorizationCode' => 'required',
                'cardType' => 'required',
                'last4' => 'required',
                'expMonth' => 'required|min:1|max:12',
                'expYear' => 'required|min:' . date('Y') . '|max:' . (date('Y') + 20),
                'bin' => 'required',
                'bank' => 'required',
                'channel' => 'required',
                'signature' => 'required',
                'reusable' => 'required',
                'countryCode' => 'required',
                'accountName' => 'required',
                'cvv' => 'required',
                'reference' => 'required',
            ];
            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };


            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $data = $bankDbConnection->createCardWallet($user['username'], $request);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_CARD_WALLET_CREATED[1],
                    'dcode' => ErrorCodes::$SUCCESS_CARD_WALLET_CREATED[0],
                    'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_CARD_WALLET_CREATED[1],
                    'dcode' => ErrorCodes::$FAIL_CARD_WALLET_CREATED[0],
                    'data' => $data['message'],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function addFundsToCardWallet($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'accountNo' => $request['accountNo'] ?? null,
                'amount' => $request['amount'] ?? null,
                'cardNo' => $request['cardNo'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'amount' => 'required',
                'cardNo' => 'required',
            ];
            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };


            $cardWalletConnection = new AddFundsCardWalletController($bankid);
            $data = $cardWalletConnection->cardWalletAddFunds($user['username'], $request, $bankid);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => [],
                    // 'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_TRANSACTION[1],
                    'dcode' => ErrorCodes::$FAIL_TRANSACTION[0],
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function customerFAQ($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'accountNo' => $request['accountNo'] ?? null,
                'question' => $request['question'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'question' => 'required',
            ];
            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $data = $bankDbConnection->gettingCustomerFAQ($user['username'], $request['question'], $request['accountNo']);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_CUSTOMER_FAQ_REQUEST[1],
                    'dcode' => ErrorCodes::$SUCCESS_CUSTOMER_FAQ_REQUEST[0],
                    'data' => null,
                    // 'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_CUSTOMER_FAQ_REQUEST[1],
                    'dcode' => ErrorCodes::$FAIL_CUSTOMER_FAQ_REQUEST[0],
                    'data' => null,
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function broadcastMessages($user, $bankid, $request)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $broadCastMsg = $bankDbConnection->getBroadcastMessages();

            if ($broadCastMsg['code'] == 200) {
                $messageData = $broadCastMsg['data'] ?? [];
                $data = array_map(function ($message) {
                    return [
                        'id' => $message['Sno'] ?? null,
                        'msgNo' => $message['MsgNo'] ?? null,
                        'message' => $message['Msg'] ?? null,
                    ];
                }, $messageData);

                return [
                    'message' => ErrorCodes::$SUCCESS_BROADCAST_MESSAGE_FOUND[1],
                    'dcode' => ErrorCodes::$SUCCESS_BROADCAST_MESSAGE_FOUND[0],
                    'data' => $data,
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_BROADCAST_MESSAGE_FOUND[1],
                    'dcode' => ErrorCodes::$FAIL_BROADCAST_MESSAGE_FOUND[0],
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function customerQuery($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'accountNo' => $request['accountNo'] ?? null,
                'message' => $request['message'] ?? null,
                'type' => $request['type'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'message' => 'required',
                'type' => 'required',
            ];
            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $customerInfo = $bankDbConnection->customerQueryMessage($user['username'], $request);

            if ($customerInfo['code'] == 200) {
                $data = [
                    'Username' => $customerInfo['data']['Username'],
                    'AccountId' => $customerInfo['data']['AccountID'],
                    // 'AccountName' => $customerInfo['data']['AccountName'],
                    'Message' => $customerInfo['data']['Message'],
                    'MType' => $customerInfo['data']['MType'],
                    'Seen' => $customerInfo['data']['Seen'],
                    'Ddate' => $customerInfo['data']['Ddate'],
                ];
                return [
                    'message' => ErrorCodes::$SUCCESS_CUSTOMER_QUERY_REQUEST[1],
                    'dcode' => ErrorCodes::$SUCCESS_CUSTOMER_QUERY_REQUEST[0],
                    'data' => null,
                    // 'data' => $data,
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_CUSTOMER_QUERY_REQUEST[1],
                    'dcode' => ErrorCodes::$FAIL_CUSTOMER_QUERY_REQUEST[0],
                    'data' => null,
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function requestLoan($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'accountNo' => $request['accountNo'] ?? null,
                'duration' => $request['duration'] ?? null,
                'loanAmount' => $request['loanAmount'] ?? null,
                'purpose' => $request['purpose'] ?? null,
                'durationType' => $request['durationType'] ?? null,
                'userPhoto' => $request['userPhoto'] ?? null,
                'nicPhoto' => $request['nicPhoto'] ?? null,
                'Guarantor1Name' => $request['Guarantor1Name'] ?? null,
                'Guarantor1Add' => $request['Guarantor1Add'] ?? null,
                'Guarantor1TelNo' => $request['Guarantor1TelNo'] ?? null,
                'Guarantor2Name' => $request['Guarantor2Name'] ?? null,
                'Guarantor2Add' => $request['Guarantor2Add'] ?? null,
                'Guarantor2TelNo' => $request['Guarantor2TelNo'] ?? null,
                'Cola1File' => $request['Cola1File'] ?? null,
                'Cola2File' => $request['Cola2File'] ?? null,
                'Cola3File' => $request['Cola3File'] ?? null,
            ];

            $rules = [
                'accountNo' => 'required',
                'duration' => 'required',
                'loanAmount' => 'required|integer|regex:/^\d+$/',
                'purpose' => 'required',
                'durationType' => 'required',
                'userPhoto' => 'required',
                'nicPhoto' => 'required',
                'Guarantor1Name' => 'required',
                'Guarantor1Add' => 'required',
                'Guarantor1TelNo' => 'required',
                'Guarantor2Name' => 'required',
                'Guarantor2Add' => 'required',
                'Guarantor2TelNo' => 'required',
                'Cola1File' => 'required',
                'Cola2File' => 'required',
                'Cola3File' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $data = $bankDbConnection->requestLoan($request);

            if ($data['code'] == 200) {
                return [
                    'message' => ErrorCodes::$SUCCESS_LOAN_REQUEST[1],
                    'dcode' => ErrorCodes::$SUCCESS_LOAN_REQUEST[0],
                    'data' => $data['data'],
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => $data['message'],
                    'dcode' => ErrorCodes::$FAIL_LOAN_REQUEST_CREATED[0],
                    'data' => [],
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function getPiggyList($user, $bankid)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $piggyData = $bankDbConnection->fetchPiggyAccounts($bankDbConnection->getCustomerAccounts($user['username'])[0]);

            if ($piggyData['code'] == 200) {
                $data = [
                    'total_savings' => $piggyData['data']['total_savings'],
                    'savings' => $piggyData['data']['savings']
                ];
                return [
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => $data,
                    'code' => 200,
                ];
            } else {
                $data = [
                    'total_savings' => $piggyData['data']['total_savings'],
                    'query' => $piggyData['data']['query'],
                    'savings' => []
                ];
                return [
                    'message' => ErrorCodes::$SUCCESS_TRANSACTION[1],
                    'dcode' => ErrorCodes::$SUCCESS_TRANSACTION[0],
                    'data' => $data,
                    'code' => 200,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function createPiggyAccount($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'funding_source' => $request['funding_source'] ?? null,
                'amount' => $request['amount'] ?? null,
                'cycle' => $request['cycle'] ?? null,
                'terms' => $request['terms'] ?? null,
                'maturity_date' => $request['maturity_date'] ?? null,
                'title' => $request['title'] ?? null,
            ];

            $rules = [
                'funding_source' => 'required',
                'amount' => 'required',
                'cycle' => 'required',
                'terms' => 'required',
                'maturity_date' => 'required',
                'title' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $createPiggy = $bankDbConnection->createPiggyEntity($user, $request);

            if ($createPiggy['code'] == 200) {
                $data = [
                    'AccountNo' => $createPiggy['data']['AccountNo'],
                    'totalAmount' => $createPiggy['data']['TotalAmount'],
                    'title' => $createPiggy['data']['Title'],
                    'Username' => $user['username'],
                    'terms' => $createPiggy['data']['Terms'],
                    'amountPerCycle' => $createPiggy['data']['AmountPerCycle'],
                    'maturityDate' => $createPiggy['data']['MaturityDate'],
                    'Ddate' => $createPiggy['data']['CreatedAt'],
                    'createdAt' => $createPiggy['data']['CreatedAt'],
                ];
                return [
                    'message' => ErrorCodes::$SUCCESS_PIGGY_ACCOUNT_CREATED[1],
                    'dcode' => ErrorCodes::$SUCCESS_PIGGY_ACCOUNT_CREATED[0],
                    'data' => $data,
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_PIGGY_ACCOUNT_CREATED[1],
                    'dcode' => ErrorCodes::$FAIL_PIGGY_ACCOUNT_CREATED[0],
                    'data' => $createPiggy['message'],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }



    public function piggyWithdraw($user, $bankid, $request)
    {
        try {
            $dataRequest = [
                'account_no' => $request['account_no'] ?? null,
            ];

            $rules = [
                'account_no' => 'required',
            ];

            $validator = new Validator();
            $validation = $validator->make($dataRequest, $rules);

            $validation->validate();

            if ($validation->fails()) {
                return [
                    'message' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0],
                    'code' => 422,
                ];
            };

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $withdraw = $bankDbConnection->withdrawPiggy($request['account_no']);

            if ($withdraw['code'] == 200) {
                // $data = [
                //     'total_savings' => '',
                //     'savings' => [
                //         'Id' => $withdraw['data']['Id'],
                //         'PiggyId' => $withdraw['data']['PiggyId'],
                //         'ExecutionAmount' => $withdraw['data']['ExecutionAmount'],
                //         'ExecutionDate' => $withdraw['data']['ExecutionDate'],
                //         'DeductAmountOnCard' => $withdraw['data']['DeductAmountOnCard'],
                //         'ExecutedCycle' => $withdraw['data']['ExecutedCycle'],
                //         'MerchantFee' => $withdraw['data']['MerchantFee'],
                //         'BankFee' => $withdraw['data']['BankFee'],
                //     ]
                // ];
                return [
                    'message' => ErrorCodes::$SUCCESS_PIGGY_ACCOUNT_WITHDRAWAL[1],
                    'dcode' => ErrorCodes::$SUCCESS_PIGGY_ACCOUNT_WITHDRAWAL[0],
                    'data' => $withdraw,
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_PIGGY_ACCOUNT_WITHDRAWAL[1],
                    'dcode' => ErrorCodes::$FAIL_PIGGY_ACCOUNT_WITHDRAWAL[0],
                    'data' => [],
                    'code' => 400,
                ];
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }


    public function getMessagesList($user, $bankid, $request)
    {
        try {

            $bankDbConnection = new BankDbController(Database::getConnection($bankid));
            $messageList = $bankDbConnection->fetchMessagesList($user['username'], $request['page']);

            if ($messageList['code'] == 200) {
                $transactionHistory = $messageList['data']['transactionHistory'];
                $totalRow = $messageList['data']['totalRow'];

                $limit = 20;
                $page = (int)$request['page'];
                $offset = $page * $limit;

                $content = $transactionHistory;
                $res = [
                    'content' => $content,
                    'pageable' => [
                        'sort' => [
                            'empty' => true,
                            'unsorted' => true,
                            'sorted' => false
                        ],
                        'offset' => $offset,
                        'pageNumber' => $page,
                        'pageSize' => $limit,
                        'paged' => true,
                        'unpaged' => false
                    ],
                    'totalPages' => floor($totalRow / $limit) + 1,
                    'totalElements' => (int)$totalRow,
                    'last' => (($limit * $page) > $totalRow) ? true : false,
                    'size' => $limit,
                    'number' => $page,
                    'sort' => [
                        'empty' => true,
                        'unsorted' => true,
                        'sorted' => false
                    ],
                    'numberOfElements' => $limit,
                    'first' => ($page == 0) ? true : false,
                    'empty' => (($limit * $page) >= $totalRow) ? true : false
                ];


                return [
                    'message' => ErrorCodes::$SUCCESS_MESSAGES_FETCH[1],
                    'dcode' => ErrorCodes::$SUCCESS_MESSAGES_FETCH[0],
                    'data' => $res,
                    'code' => 200,
                ];
            } else {
                return [
                    'message' => ErrorCodes::$FAIL_MESSAGES_FETCH[1],
                    'dcode' => ErrorCodes::$FAIL_MESSAGES_FETCH[0],
                    'data' => [],
                    'code' => 404,
                ];
            }
        } catch (Exception $e) {
            // var_dump($e);
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
