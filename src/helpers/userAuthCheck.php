<?php

function userAuthVerify()
{
    session_start();

    $jwtToken = getBearerToken();
    $user = authenticateUser($jwtToken);

    if (!$user) {
        sendCustomResponse('Unauthorized', null, 401, 401);
    }
    return $user;
}
