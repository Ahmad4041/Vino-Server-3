<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getAuthorizationHeader()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}

function getBearerToken()
{
    $headers = getAuthorizationHeader();
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// function authenticateUser($jwtToken)
// {
//     $secretKey = '00112233445566778899';
//     try {
//         $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));
//         // Convert stdClass to array
//         $decoded_array = json_decode(json_encode($decoded), true);
//         return $decoded_array;
//     } catch (Exception $e) {
//         return null;
//     }
// }

function authenticateUser($jwtToken)
{
    $secretKey = '00112233445566778899';
    try {
        $decoded = JWT::decode($jwtToken, new Key($secretKey, 'HS256'));
        $decoded_array = json_decode(json_encode($decoded), true);

        $username = $decoded_array['username'] . '-NewApp' ?? null;
        $accountId = $decoded_array['accountId'] ?? null;
        $bankId = $decoded_array['bankId'] ?? null;

        if (!$username || !$accountId || !$bankId) {
            // return sendCustomResponse('Invalid token structure.', null, 401, 401);
            return null;
        }

        $LocalDbConnection = new LocalDbController(Database::getConnection('mysql'));
        $isValidToken = $LocalDbConnection->isTokenValid($username, $jwtToken, $accountId, $bankId);

        if (!$isValidToken) {
            // return sendCustomResponse('Invalid or expired token. Please log in again.', null, 401, 401);
            return null;
        }

        return $decoded_array;
    } catch (Exception $e) {
        return sendCustomResponse('Authentication failed. Invalid token.', null, 401, 401);
    }
}
