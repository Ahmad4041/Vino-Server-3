<?php

require 'BankDbController.php';
require '../models/UtilityDemo.php';


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
                'username' => 'required|string|min:4',
                'password' => 'required|string|min:5',
                'surname' => 'required|string',
                'otherName' => 'string|nullable',
                'gender' => 'required|string',
                'dob' => ['required', 'date', 'before:18 years ago'],
                'nationality' => 'required|string',
                'residentialAddress' => 'required|string',
                'contact' => 'required|string',
                'email' => 'required|email',
                'bvn' => 'string',
                'nin' => 'required|string',
                'occupation' => 'string',
                'accountType' => 'required|string',
                'userFileId' => 'string|nullable',
                'signatureFileId' => 'string|nullable',
                'nicFileId' => 'string|nullable',
            ];

            $validator = new Validator();
            $validation = $validator->make($data, $rules);

            if (!$validation->validate()) {
                $message = ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1];
                $data = $validation->errors();
                $dcode = ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0];
                return $this->sendCustomResponse($message, $data, $dcode, 422);
            }

            $bankDbConnection = new BankDbController(UtilityDemo::getDatabaseConnection($bankid));
            $newCustomer = $bankDbConnection->registerNewCustomer($request);

            if ($newCustomer['code'] == 200) {
                $message = ErrorCodes::$SUCCESS_USER_CREATED[1];
                $data = null;
                $dcode = ErrorCodes::$SUCCESS_USER_CREATED[0];
                $code = 200;
                return $this->sendCustomResponse($message, $data, $dcode, $code);
            } else {
                $errorRes = $newCustomer;
                $message = $errorRes['message'];
                $data = $errorRes['message'];
                $dcode = $errorRes['code'];
                $code = 404;
                return $this->sendCustomResponse($message, $data, $dcode, $code);
            }
        } catch (Exception $e) {
            $r = $this->handleCatch($e);
            return $this->sendError($r, $r['code']);
        }
    }

    private function sendCustomResponse($message, $data, $dcode, $code)
    {
        return [
            'message' => $message,
            'data' => $data,
            'dcode' => $dcode,
            'code' => $code
        ];
    }

    private function handleCatch($e)
    {
        // Log the error
        error_log($e->getMessage());
        return [
            'message' => 'An unexpected error occurred',
            'code' => 500
        ];
    }

    private function sendError($error, $code)
    {
        return [
            'message' => $error['message'],
            'code' => $code
        ];
    }
}
