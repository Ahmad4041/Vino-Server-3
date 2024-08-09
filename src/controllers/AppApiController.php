<?php

require 'BankDbController.php';
require 'ConfigController.php';
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
                'dob' => 'required|date|before:18 years ago',
                'nationality' => 'required',
                'residentialAddress' => 'required',
                'contact' => 'required',
                'email' => 'required|email',
                'bvn' => '',
                'nin' => 'required',
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
                    'dcode' => 203,
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
            $verifyUserPin = $bankDbConnection->userVerifyPin($request['pin'], $user['username']);

            if ($verifyUserPin['code'] == 200) {
                return [
                    'dcode' => ErrorCodes::$SUCCESS_PIN_CREATED[0],
                    'code' => 200,
                    'message' => ErrorCodes::$SUCCESS_PIN_CREATED[1],
                    'data' => null
                ];
            } else {
                return [
                    'dcode' => $verifyUserPin['code'],
                    'code' => 201,
                    'message' => $verifyUserPin['message'],
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

    public function getConfig(string $bankid, array $request)
    {
        $configConnection = new ConfigController(Database::getConnection($bankid));
        $isAll = isset($request['all']);
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

        // if ($isAll) {
        //     $data['networks'] = $configConnection->getTelcoNetworks($bankid, $request)['body'];
        //     $data['utilites'] = $configConnection->getUtilities($bankid, 'all')->body;
        //     $data['bank_list'] = $configConnection->getBankListWithoutAuth($bankid)->body;
        //     // Add other data fetching logic for 'all' type
        // }

        $message = ErrorCodes::$SUCCESS_FETCH[1];
        $dcode = ErrorCodes::$SUCCESS_FETCH[0];
        return sendCustomResponse($message, $data, $dcode, 200);
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
                    'message' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[1],
                    'data' => $validation->errors()->toArray(),
                    'dcode' => ErrorCodes::$FAIL_PIN_FORMAT_INVALID[0],
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
                    'code' => 201,
                    'message' => $passwordReset['message'],
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

    public function uploadImage($bankid, $files)
    {
        try {
            // Basic file validation
            if (!isset($files['file']) || $files['file']['error'] !== UPLOAD_ERR_OK) {
                return sendCustomResponse(
                    ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[1],
                    null,
                    ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[0],
                    404
                );
            }

            $file = $files['file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB

            // Validate file type and size
            if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxFileSize) {
                return sendCustomResponse(
                    'Invalid file type or size',
                    null,
                    ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[0],
                    400
                );
            }

            $filename = time();
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $imageName = $filename . '.' . $extension;

            $uploadDir = 'images'; // Adjust this path
            $uploadPath = $uploadDir . $imageName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $response = [
                    'fileId' => $imageName,
                    'type' => $file['type'],
                ];

                return sendCustomResponse(
                    ErrorCodes::$SUCCESS_FILE_UPLOADED[1],
                    $response,
                    ErrorCodes::$SUCCESS_FILE_UPLOADED[0],
                    200
                );
            } else {
                return sendCustomResponse(
                    'File upload failed',
                    null,
                    ErrorCodes::$FAIL_UPLOAD_FILE_NOT_FOUND[0],
                    500
                );
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
}
