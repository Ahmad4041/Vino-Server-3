<?php
// index.php

require_once '../src/config/database.php';
require_once '../src/helpers/returnResponse.php';
require_once '../src/helpers/errorCodes.php';
require_once '../src/helpers/auth.php';
require_once '../src/helpers/requestParse.php';
require_once '../src/helpers/userAuthCheck.php';
require_once '../src/helpers/generateRequestID.php';
require_once '../src/helpers/requireImageAuth.php';
require '../src/models/UtilityDemo.php';

require '../src/controllers/AppApiController.php';
require '../src/controllers/AuthController.php';

require '../vendor/autoload.php';
// require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;
use Rakit\Validation\Validator;
use Dotenv\Dotenv;

// Load the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
// $dotenv->load();


$app = AppFactory::create();

$appController = new AppApiController();
$authController = new AuthController();

?>

<?php

$app->get('/api/v2/{bankId}/hello', function (Request $request, Response $response, array $args) {
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

$app->get('/api/v2/{bankId}/validate-connection', function (Request $request, Response $response, array $args) {
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

// ******************************************** Auth endpoimts START ************************************************


// register new customer
$app->post('/api/v2/{bankId}/auth/register-user-customer-new', function (Request $request, Response $response, array $args) use ($appController) {

    $data = requestParse($request);


    $result = $appController->registerNewCustomer($args['bankId'], $data);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});



// register existing customer
$app->post('/api/v2/{bankId}/auth/register-user-customer-exist', function (Request $request, Response $response, array $args) use ($appController) {
    $data = requestParse($request);

    $result = $appController->registerExistCustomer($args['bankId'], $data);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

// generate user app token

$app->post('/api/v2/{bankId}/auth/generate-token', function (Request $request, Response $response, array $args) use ($appController) {
    $data = requestParse($request);

    $authController = new AuthController();
    $result = $authController->mobileLoginNewLogic($args['bankId'], $data);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

// ******************************************** Auth endpoimts END ************************************************

// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** User endpoimts START ************************************************

// get current user's information
$app->get('/api/v2/{bankId}/app/user/current', function (Request $request, Response $response, array $args) use ($appController) {

    $user = userAuthVerify();

    $result = $appController->currentUser($args['bankId'], $user);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


// get current user's data
$app->get('/api/v2/{bankId}/app/user/current/accounts', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();


    $result = $appController->currentUserAccountBalance($args['bankId'], $user);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->post('/api/v2/{bankId}/app/user/pin-create', function (Request $request, Response $response, array $args) use ($appController) {

    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->userPinCreate($args['bankId'], $requestData, $user);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->put('/api/v2/{bankId}/app/user/pin-change', function (Request $request, Response $response, array $args) use ($appController) {

    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->userPinUpdate($args['bankId'], $requestData, $user);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->put('/api/v2/{bankId}/app/user/password-change', function (Request $request, Response $response, array $args) use ($appController) {

    // $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->userPasswordUpdate($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});



$app->post('/api/v2/{bankId}/app/user/pin-verify', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->userPinVerify($args['bankId'], $requestData, $user);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** Common endpoimts START ************************************************



$app->get('/api/v2/{bankId}/app/common/account-type', function (Request $request, Response $response, array $args) use ($appController) {

    $result = $appController->getAccountType($args['bankId']);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->get('/api/v2/{bankId}/app/common/config', function (Request $request, Response $response, array $args) use ($appController) {
    $queryParams = $request->getQueryParams();

    $result = $appController->getConfig($args['bankId'], $queryParams); //Bankid should be string

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->get('/api/v2/{bankId}/app/common/update/config', function (Request $request, Response $response, array $args) use ($appController) {

    $result = $appController->fetchLiveConfigData($args['bankId']);
    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->post('/api/v2/{bankId}/app/common/update/config/features', function (Request $request, Response $response, array $args) use ($appController) {
    $requestData = requestParse($request);

    $result = $appController->updateLiveAppFeatures($args['bankId'], $requestData);
    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});



$app->post('/api/v2/{bankId}/app/common/password-reset', function (Request $request, Response $response, array $args) use ($appController) {
    $requestData = requestParse($request);
    $result = $appController->resetPassword($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->post('/api/v2/{bankId}/app/common/file-upload', function (Request $request, Response $response, array $args) use ($appController) {
    $requestData = requestParse($request);
    $result = $appController->uploadImage($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** TRANSACTION endpoimts START ************************************************

// $groupPrefixTrans = 'api/v2/{bankid}/app/transaction';

$app->get('/api/v2/{bankId}/app/transaction', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);
    $result = $appController->getTransaction($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/bank-list', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getBankList($args['bankId']);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/telco-networks', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getTelecoNetworks($args['bankId']);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/find-account-info', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->getAccountInfo($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/customer-verification', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->verifyMeterNo($args['bankId'], $requestData);

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/utilities', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getUtilities();

    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->get('/api/v2/{bankId}/app/transaction/customer-debit-cards', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getCustomerDebitCards($args['bankId'], $user);
    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->post('/api/v2/{bankId}/app/transaction/topup-mobile', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->requestTopUpMobile($args['bankId'], $user, $requestData);
    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});


$app->post('/api/v2/{bankId}/app/transaction/fund-transfer', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->requestFundTransfer($args['bankId'], $user, $requestData);
    if ($result['code'] == 200) {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    } else {
        return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
    }
});

$app->post('/api/v2/{bankId}/app/transaction/utilities', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->postUtilities($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});

$app->post('/api/v2/{bankId}/app/transaction/customer-debit-card-block', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->blockCustomerDebitCard($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});

$app->post('/api/v2/{bankId}/app/transaction/request-cheque-book', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->requestChequeBook($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});

$app->post('/api/v2/{bankId}/app/transaction/request-cheque-stop-payment', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->requestChequeStopPayment($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});




// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** CARD-WALLET endpoimts START ************************************************


$app->get('/api/v2/{bankId}/app/card-wallet', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getCardWallet($user, $args['bankId']);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->delete('/api/v2/{bankId}/app/card-wallet', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->deleteCardWallet($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});



$app->post('/api/v2/{bankId}/app/card-wallet', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);


    $result = $appController->postCardWallet($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->post('/api/v2/{bankId}/app/card-wallet/add-funds', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->addFundsToCardWallet($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** Extra endpoimts START ************************************************

$app->post('/api/v2/{bankId}/app/extra/customer-faq', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->customerFAQ($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->post('/api/v2/{bankId}/app/extra/customer-query', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->customerQuery($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->post('/api/v2/{bankId}/app/extra/broadcast-messages', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->broadcastMessages($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});



$app->post('/api/v2/{bankId}/app/extra/request-loan', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->requestLoan($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** Piggy endpoimts START ************************************************


$app->get('/api/v2/{bankId}/app/piggy', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getPiggyList($user, $args['bankId']);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->post('/api/v2/{bankId}/app/piggy', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->createPiggyAccount($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->put('/api/v2/{bankId}/app/piggy/withdrawal', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->piggyWithdraw($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** Others endpoimts START ************************************************
// http://vino.viralcomputers.com:9000/api/v2/101/app/messages?size=100&page=0

$app->get('/api/v2/{bankId}/app/messages', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->getMessagesList($user, $args['bankId'], $requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});


$app->get('/api/v2/{bankId}/app/beneficiaries', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();

    $result = $appController->getBeneficiariesList($user, $args['bankId']);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});

$app->delete('/api/v2/{bankId}/app/beneficiaries', function (Request $request, Response $response, array $args) use ($appController) {
    $user = userAuthVerify();
    $requestData = requestParse($request);

    $result = $appController->deleteBeneficiaries($user, $args['bankId'], $requestData);
    $result['data']['body'] = $appController->getBeneficiariesList($user, $args['bankId'])['data'];
    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});




// ********************************************************************************************
// **************************************************************************************
// ********************************************************************************
// ******************************************** Admin endpoimts START ************************************************



$app->post('/api/v2/admin/project-run', function (Request $request, Response $response, array $args) use ($appController) {
    $requestData = requestParse($request);

    $result = $appController->projectLiveStatus($requestData);

    return sendCustomResponse($result['message'], $result['data'], $result['dcode'], $result['code']);
});




try {
    $app->run();
} catch (Exception $e) {
    return sendCustomResponse($e->getMessage(), null, 404, 404);
}

?>