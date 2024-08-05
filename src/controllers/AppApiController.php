<?php

require 'BankDbController.php';
// require '../models/UtilityDemo.php';
require __DIR__ . '/../models/UtilityDemo.php';



use Rakit\Validation\Validator;

class AppApiController
{
    public function registerNewCustomer($bankid, $request)
    {
        // var_dump($bankid, $request);
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

}
