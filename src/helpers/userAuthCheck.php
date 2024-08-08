<?php

function userAuthVerify()
{
    session_start();

    $jwtToken = getBearerToken();
    $user = authenticateUser($jwtToken);

    if (!$user) {
        sendCustomResponse('Token Expire, Please login again', null, 401, 401);
    }
    return $user;
}
