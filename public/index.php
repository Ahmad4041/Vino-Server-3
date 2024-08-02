<?php
// index.php

require_once '../src/config/database.php';
require_once '../src/helpers/returnResponse.php';
require_once '../src/helpers/errorCodes.php';
require_once '../src/controllers/AppApiController.php';

require '../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;
use Rakit\Validation\Validator;

$app = AppFactory::create();

?>

<?php

// $app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
//     $name = $args['name'];
//     $response->getBody()->write("Hello, $name");
//     return $response;
// });

// // Route to fetch data from external API
// $app->get('/fetch-data', function (Request $request, Response $response, array $args) {
//     $client = new Client();
//     $res = $client->request('GET', 'https://api.example.com/data');
//     $data = json_decode($res->getBody(), true);

//     $response->getBody()->write(json_encode($data));
//     return $response->withHeader('Content-Type', 'application/json');
// });

// $app->get('/hello', function (Request $request, Response $response) {

//     return sendCustomResponse('Hello World', null, 1002, 200);
// });


$app->get('/{bankId}/hello', function (Request $request, Response $response, array $args) {
    $bankId = $args['bankId'];
    $queryParams = $request->getQueryParams();

    // Combine path and query parameters for validation
    $data = array_merge(['bankId' => $bankId], $queryParams);

    $validator = new Validator();
    $validation = $validator->make($data, [
        'bankId' => 'required',
        'name' => 'required|alpha_spaces',
        'number' => 'required|numeric'
    ]);

    // Perform validation
    $validation->validate();

    // Check if validation fails
    if ($validation->fails()) {
        $errors = $validation->errors()->all();
        return sendCustomResponse("Validation Error", $errors, 400, 400);
    }

    // If validation passes, proceed with the main logic
    $responseData = [
        'bankId' => $bankId,
        'name' => $queryParams['name'] ?? null,
        'number' => $queryParams['number'] ?? null
    ];

    return sendCustomResponse('Hello World', $responseData, 1002, 200);
});


$app->post('/{bankId}/auth/register-user-customer-new', function (Request $request, Response $response, array $args) {
    $bankId = $args['bankId'];
    $queryParams = $request->getQueryParams();

    // Combine path and query parameters for validation
    // $data = array_merge(['bankId' => $bankId], $queryParams);

    $appApiController = new AppApiController();
    $appApiController->registerNewCustomer($bankId, $queryParams);
});



// // Define a route
// $app->get('/hello', function (Request $request, Response $response, array $args) {

//     return sendCustomResponse("Hello World", null, 1002, 200);
// });





// $bankId = $_GET['bankid'] ?? null;

// $validator = new Validator();
// $validation = $validator->make($_GET, [
//     'bankid' => 'required', // Define validation rules for bankid
// ]);

// // Perform validation
// $validation->validate();

// // Check if validation fails
// if ($validation->fails()) {
//     $errors = $validation->errors()->all();
//     sendCustomResponse("Error", $errors, 400, 400);
// }
// try {
//     // Attempt to establish a connection
//     clearstatcache();
//     $pdo = Database::getConnection($bankId);

//     // If connection is successful
//     sendCustomResponse(
//         ErrorCodes::$SUCCESS_FETCH[1],
//         "Connection established successfully for bank ID: $bankId",
//         ErrorCodes::$SUCCESS_FETCH[0],
//         200
//     );
// } catch (Exception $e) {
//     // If there's an error
//     sendCustomResponse(
//         ErrorCodes::$FAIL_MESSAGES_FETCH[1],
//         $e->getMessage(),
//         ErrorCodes::$FAIL_MESSAGES_FETCH[0],
//         500
//     );
// }
// $app->addErrorMiddleware(true, true, true);

$app->run();
?>