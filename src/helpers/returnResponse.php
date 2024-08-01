<?php

function sendCustomResponse($message, $data, $dcode, $code)
{
    $response = [
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => $dcode,
        'message' => $message,
        'body' => $data,
    ];

    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($response);
    exit;
}
