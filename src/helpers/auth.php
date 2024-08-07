<?php

use Firebase\JWT\JWT;

function getAuthorizationHeader()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
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

function authenticateUser($jwtToken)
{
    $secretKey = '00112233445566778899'; // Ensure this matches the key used in generateToken
    try {
        $decoded = JWT::decode($jwtToken, $secretKey, ['HS256']);
        // Convert stdClass to array
        $decoded_array = json_decode(json_encode($decoded), true);
        return $decoded_array;
    } catch (Exception $e) {
        return null;
    }
}
