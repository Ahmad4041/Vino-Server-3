<?php

// require __DIR__ . '/../models/UtilityDemo.php';
require 'LocalDbController.php';
// require 'BankDbController.php';
use Firebase\JWT\JWT;
// use Firebase\JWT\Key;
use Rakit\Validation\Validator;

class AuthController
{

    private function checkUserRegisterUpdatedLogic($data, $bankId)
    {
        $username = $data['Username'];
        $accountId = $data['AccountID'];
        $password = password_hash($username . ':' . $bankId, PASSWORD_BCRYPT);

        $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $LocalDbConnection->checkAppUserExistUpdatedLogic($username, $accountId, $bankId, $password);
    }

    private function generateToken($username, $bankId, $accountId)
    {
        $key = '00112233445566778899';
        $payload = [
            'iss' => 'vino.viralcomputers.com:9000',
            'iat' => time(),
            'exp' => time() + (50 * 60),
            'username' => $username,
            'bankId' => $bankId,
            'accountId' => $accountId,
        ];

        return JWT::encode($payload, $key, 'HS256');
    }

    private function generateRequestID()
    {
        return bin2hex(random_bytes(12));  // Generates a random request ID
    }


    public function mobileLoginNewLogic($bankId, $request)
    {
        try {
            $data = [
                'username' => $request['username'] ?? null,
                'password' => $request['password'] ?? null,
            ];

            $rules = [
                'username' => 'required',
                'password' => 'required'
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

            $BankDbConnection = new BankDbController(Database::getConnection($bankId));
            $loginCheck = $BankDbConnection->authUser($request);
            if ($loginCheck['code'] == 200) {
                $this->checkUserRegisterUpdatedLogic($loginCheck['data'], $bankId);
                $request['bankId'] = $bankId;

                $credentials = [
                    'username' => $request['username'],
                    'bankId' => $bankId
                ];

                $combinedString = $loginCheck['data']['Username'] . ':' . $credentials['bankId'];
                $credentials['password'] = $combinedString;

                $token = $this->generateToken($loginCheck['data']['Username'], $bankId, $loginCheck['data']['AccountID']);
                if (!$token) {
                    return sendCustomResponse('Login failed', null, 401, 404);
                }

                $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
                $LocalDbConnection->insertToken($loginCheck['data'], $bankId, $token);

                $userData = [
                    'Username' => $loginCheck['data']['Username'],
                    'AType' => $loginCheck['data']['AType'],
                    'PIN' => $loginCheck['data']['PIN']
                ];
                $requestId = $this->generateRequestID();

                // $mobileLogData = [
                //     'ClientID' => $bankId,
                //     'PhoneID' => $request['deviceId'],
                //     'Username' => $userData['Username'],
                // ];
                // $mobileLogDbConnection = new MobileLogs(Database::getConnection('log'));
                // $mobileLogDbConnection->logMobileLogin($mobileLogData);
                // $BankDbConnection->logDbLogin($mobileLogData, $loginCheck['data']['AccountID']);

                $data = [
                    'username' => $userData['Username'],
                    'token' => $token,
                    'type' => $userData['AType'],
                    'requestId' => $requestId,
                    'pin' => $userData['PIN']
                ];
                return sendCustomResponse('Login Successful', $data, ErrorCodes::$SUCCESS_LOGIN[0], 200);
            } else {
                $data = [
                    'username' => 'None',
                    'token' => 'None',
                    'type' => 'None',
                    'pin' => 'none'
                ];
                return sendCustomResponse('Invalid Username or Password', $data, ErrorCodes::$FAIL_LOGIN[0], 200);
            }
        } catch (Exception $e) {
            $data = [
                'username' => 'None',
                'token' => 'None',
                'type' => 'None',
                'pin' => 'none'
            ];
            return sendCustomResponse('Database Connection Unreachable', $data, ErrorCodes::$FAIL_LOGIN[0], 200);
        }
    }
}
