<?php
// index.php

require_once '../src/config/database.php';
require_once '../src/helpers/returnResponse.php';
require_once '../src/helpers/errorCodes.php';
require '../vendor/autoload.php';

use Rakit\Validation\Validator;


$bankId = $_GET['bankid'] ?? null;

$validator = new Validator();
$validation = $validator->make($_GET, [
    'bankid' => 'required', // Define validation rules for bankid
]);

// Perform validation
$validation->validate();

// Check if validation fails
if ($validation->fails()) {
    $errors = $validation->errors()->all();
    sendCustomResponse("Error", $errors, 400, 400);
}
try {
    // Attempt to establish a connection
    clearstatcache();
    $pdo = Database::getConnection($bankId);

    // If connection is successful
    sendCustomResponse(
        ErrorCodes::$SUCCESS_FETCH[1],
        "Connection established successfully for bank ID: $bankId",
        ErrorCodes::$SUCCESS_FETCH[0],
        200
    );
} catch (Exception $e) {
    // If there's an error
    sendCustomResponse(
        ErrorCodes::$FAIL_MESSAGES_FETCH[1],
        $e->getMessage(),
        ErrorCodes::$FAIL_MESSAGES_FETCH[0],
        500
    );
}
