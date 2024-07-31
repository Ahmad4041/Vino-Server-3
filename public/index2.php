<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/DbConnect.php';  // Adjust if necessary

$app = AppFactory::create();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->get('/{bankId}/testdbconnection', function (Request $request, Response $response, $args) {
    $bankId = $args['bankId'];
    $responseData = ['success' => false, 'message' => ''];

    $db = new DbConnect();

    if (!array_key_exists($bankId, $db->getConnections())) {
        $responseData['message'] = "Bank connection does not exist for bank ID: $bankId";
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    try {
        $conn = $db->connect($bankId);
        $responseData['success'] = true;
        $responseData['message'] = 'Connection successful';
        $responseData['bankId'] = $bankId;
    } catch (Exception $e) {
        $responseData['message'] = 'Connection failed: ' . $e->getMessage();
        error_log("Database connection error for bank ID $bankId: " . $e->getMessage());
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $response->getBody()->write(json_encode($responseData));
    return $response->withHeader('Content-Type', 'application/json');
});

// $app->get('/{bankid}/test', function () {
//     return "Hello world";
// });

$app->get('/{bankid}/test', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world");
    return $response;
});

$app->run();
