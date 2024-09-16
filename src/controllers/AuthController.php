<?php

require 'LocalDbController.php';

use Firebase\JWT\JWT;
use Rakit\Validation\Validator;

class AuthController
{
    private $key = '00112233445566778899';
    private $debug = true;

    // private function checkUserRegisterUpdatedLogic($data, $bankId, $deviceId)
    // {
    //     $username = $data['Username'] . '-NewApp';
    //     $accountId = $data['AccountID'];
    //     $password = password_hash($username . ':' . $bankId, PASSWORD_BCRYPT);

    //     $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
    //     $LocalDbConnection->checkAppUserExistUpdatedLogic($username, $accountId, $bankId, $password, $deviceId);
    // }

    private function generateToken($username, $bankId, $accountId, $deviceId)
    {
        $tokenId = bin2hex(random_bytes(16));
        $payload = [
            'iss' => 'vino.viralcomputers.com:9000',
            'iat' => time(),
            'exp' => time() + (50 * 60),
            'jti' => $tokenId,
            'username' => $username,
            'bankId' => $bankId,
            'accountId' => $accountId,
        ];

        $token = JWT::encode($payload, $this->key, 'HS256');

        $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $LocalDbConnection->insertToken($username, $bankId, $token, $payload['exp'], $accountId, $deviceId);

        return $token;
    }


    private function generateRequestID()
    {
        return bin2hex(random_bytes(12));
    }

    public function mobileLoginNewLogic($bankId, $request)
    {
        try {
            $data = [
                'username' => $request['username'] ?? null,
                'password' => $request['password'] ?? null,
            ];

            $validator = new Validator();
            $validation = $validator->make($data, [
                'username' => 'required',
                'password' => 'required'
            ]);

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


                if (!$this->debug) {
                    $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
                    $verifyDevice = $LocalDbConnection->authenticateUserDevice($loginCheck['data']['Username'], $bankId,  $request['deviceId']);

                    $data = [
                        'username' => 'None',
                        'token' => 'None',
                        'type' => 'None',
                        'pin' => 'none'
                    ];

                    if ($verifyDevice['code'] != 200) {
                        return sendCustomResponse($verifyDevice['message'], $data, ErrorCodes::$FAIL_LOGIN[0], 200);
                    }
                }


                $token = $this->generateToken($loginCheck['data']['Username'], $bankId, $loginCheck['data']['AccountID'], $request['deviceId'] ?? null);

                if (!$token) {
                    return sendCustomResponse('Login failed', null, 401, 404);
                }

                $userData = [
                    'Username' => $loginCheck['data']['Username'],
                    'AType' => $loginCheck['data']['AType'],
                    'PIN' => $loginCheck['data']['PIN']
                ];
                $requestId = $this->generateRequestID();

                $mobileLogData = [
                    'ClientID' => $bankId,
                    'PhoneID' => $request['deviceId'] ?? null,
                    'Username' => $userData['Username'],
                ];
                $mobileLogDbConnection = new MobileLogController(Database::getConnection('log'));
                $mobileLogDbConnection->logMobileLogin($mobileLogData);
                $BankDbConnection->logDbLogin($mobileLogData, $loginCheck['data']['AccountID']);

                $data = [
                    'username' => $userData['Username'],
                    'token' => $token,
                    'type' => $userData['AType'],
                    'requestId' => $requestId,
                    'pin' => $userData['PIN']
                ];
                return sendCustomResponse('Login Successful', $data, ErrorCodes::$SUCCESS_LOGIN[0], 200);
            } else {
                // $data = [
                //     'username' => 'None',
                //     'token' => 'None',
                //     'type' => 'None',
                //     'pin' => 'none'
                // ];
                return sendCustomResponse('Invalid Username or Password', $data, ErrorCodes::$FAIL_LOGIN[0], 200);
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
            // $data = [
            //     'username' => 'None',
            //     'token' => 'None',
            //     'type' => 'None',
            //     'pin' => 'none'
            // ];
            return sendCustomResponse('Database Connection Unreachable', $data, ErrorCodes::$FAIL_LOGIN[0], 200);
        }
    }
}
