<?php
// index.php

require_once '../src/config/database.php';
require_once '../src/helpers/returnResponse.php';
require_once '../src/helpers/errorCodes.php';

require '../src/controllers/AppApiController.php';
require '../vendor/autoload.php';
// require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;
use Rakit\Validation\Validator;


// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
// $dotenv->load();


$app = AppFactory::create();

$appController = new AppApiController();

?>

<?php

$app->get('/{bankid}/hello', function (Request $request, Response $response, array $args) {
    $bankid = $args['bankid'];
    $queryParams = $request->getQueryParams();
    $data = array_merge(['bankid' => $bankid], $queryParams);
    $validator = new Validator();
    $validation = $validator->make($data, [
        'bankid' => 'required',
        'testcode' => 'required|numeric'
    ]);

    // Perform validation
    $validation->validate();

    // Check if validation fails
    if ($validation->fails()) {
        $errors = $validation->errors()->all();
        return sendCustomResponse("Validation Error", $errors, 400, 400);
    }

    $responseData = [
        'bankId' => $bankid,
        'testcode' => $queryParams['testcode'] ?? null
    ];


    return sendCustomResponse('Hello world', $responseData, 1002, 200);
});

$app->get('/{bankId}/validate-connection', function (Request $request, Response $response, array $args) {
    $bankId = $args['bankId'];
    $queryParams = $request->getQueryParams();

    // Combine path and query parameters for validation
    $data = array_merge(['bankId' => $bankId], $queryParams);

    $validator = new Validator();
    $validation = $validator->make($data, [
        'bankId' => 'required',
    ]);

    // Perform validation
    $validation->validate();

    // Check if validation fails
    if ($validation->fails()) {
        $errors = $validation->errors()->all();
        return sendCustomResponse($response, "Validation Error", $errors, 400, 400);
    }

    try {
        // Attempt to establish a connection
        clearstatcache();
        $pdo = Database::getConnection($bankId);

        // If connection is successful
        return sendCustomResponse(
            "Connection established successfully for bank ID: $bankId",
            "Success",
            1002,
            200,
            200
        );
    } catch (Exception $e) {
        // If there's an error
        return sendCustomResponse(
            $response,
            "Error",
            $e->getMessage(),
            500,
            500
        );
    }
});

// ******************************************** Auth endpoimts ************************************************


// register new customer
$app->post('/{bankId}/auth/register-user-customer-new', function (Request $request, Response $response, array $args) use ($appController) {
    $bankId = (int)$args['bankId'];
    $rawBody = $request->getBody()->__toString();
    $data = json_decode($rawBody, true);


    $result = $appController->registerNewCustomer($bankId, $data);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});



// register existing customer
$app->post('/{bankId}/auth/register-user-customer-exist', function (Request $request, Response $response, array $args) use ($appController) {
    $bankId = (int)$args['bankId'];
    $rawBody = $request->getBody()->__toString();
    $data = json_decode($rawBody, true);


    $result = $appController->registerExistCustomer($bankId, $data);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});




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


// $app->get('/{bankId}/hello', function (Request $request, Response $response, array $args) {
//     $bankId = $args['bankId'];
//     $queryParams = $request->getQueryParams();

//     // Combine path and query parameters for validation
//     $data = array_merge(['bankId' => $bankId], $queryParams);

//     $validator = new Validator();
//     $validation = $validator->make($data, [
//         'bankId' => 'required',
//         'name' => 'required|alpha_spaces',
//         'number' => 'required|numeric'
//     ]);

//     // Perform validation
//     $validation->validate();

//     // Check if validation fails
//     if ($validation->fails()) {
//         $errors = $validation->errors()->all();
//         return sendCustomResponse("Validation Error", $errors, 400, 400);
//     }

//     // If validation passes, proceed with the main logic
//     $responseData = [
//         'bankId' => $bankId,
//         'name' => $queryParams['name'] ?? null,
//         'number' => $queryParams['number'] ?? null
//     ];

//     return sendCustomResponse('Hello World', $responseData, 1002, 200);
// });


// $app->post('/{bankId}/auth/register-user-customer-new', function (Request $request, Response $response, array $args) {
//     public function registerNewCustomer($bankid, $request)
//     {
//         try {
//             $data = [
//                 'username' => $request['username'] ?? null,
//                 'password' => $request['password'] ?? null,
//                 'surname' => $request['surname'] ?? null,
//                 'otherName' => $request['otherName'] ?? null,
//                 'gender' => $request['gender'] ?? null,
//                 'dob' => $request['dob'] ?? null,
//                 'nationality' => $request['nationality'] ?? null,
//                 'residentialAddress' => $request['residentialAddress'] ?? null,
//                 'contact' => $request['contact'] ?? null,
//                 'email' => $request['email'] ?? null,
//                 'bvn' => $request['bvn'] ?? null,
//                 'nin' => $request['nin'] ?? null,
//                 'occupation' => $request['occupation'] ?? null,
//                 'accountType' => $request['accountType'] ?? null,
//                 'userFileId' => $request['userFileId'] ?? null,
//                 'signatureFileId' => $request['signatureFileId'] ?? null,
//                 'nicFileId' => $request['nicFileId'] ?? null,
//             ];

//             $rules = [
//                 'username' => 'required|string|min:4',
//                 'password' => 'required|string|min:5',
//                 'surname' => 'required|string',
//                 'otherName' => 'string|nullable',
//                 'gender' => 'required|string',
//                 'dob' => ['required', 'date', 'before:18 years ago'],
//                 'nationality' => 'required|string',
//                 'residentialAddress' => 'required|string',
//                 'contact' => 'required|string',
//                 'email' => 'required|email',
//                 'bvn' => 'string',
//                 'nin' => 'required|string',
//                 'occupation' => 'string',
//                 'accountType' => 'required|string',
//                 'userFileId' => 'string|nullable',
//                 'signatureFileId' => 'string|nullable',
//                 'nicFileId' => 'string|nullable',
//             ];

//             $validator = new Validator();
//             $validation = $validator->make($data, $rules);

//             if (!$validation->validate()) {
//                 $message = ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[1];
//                 $data = $validation->errors();
//                 $dcode = ErrorCodes::$FAIL_REQUIRED_FIELDS_VALIDATION[0];
//                 return $this->sendCustomResponse($message, $data, $dcode, 422);
//             }

//             $bankDbConnection = new BankDbController(UtilityDemo::getDatabaseConnection($bankid));
//             $newCustomer = $bankDbConnection->registerNewCustomer($request);

//             if ($newCustomer['code'] == 200) {
//                 $message = ErrorCodes::$SUCCESS_USER_CREATED[1];
//                 $data = null;
//                 $dcode = ErrorCodes::$SUCCESS_USER_CREATED[0];
//                 $code = 200;
//                 return $this->sendCustomResponse($message, $data, $dcode, $code);
//             } else {
//                 $errorRes = $newCustomer;
//                 $message = $errorRes['message'];
//                 $data = $errorRes['message'];
//                 $dcode = $errorRes['code'];
//                 $code = 404;
//                 return $this->sendCustomResponse($message, $data, $dcode, $code);
//             }
//         } catch (Exception $e) {
//             $r = $this->handleCatch($e);
//             return $this->sendError($r, $r['code']);
//         }
// });



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