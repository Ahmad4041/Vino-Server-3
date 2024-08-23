<?php

function generateRequestId()
{
    $requestId = '';
    for ($i = 0; $i < 20; $i++) {
        $requestId .= mt_rand(0, 9);
    }

    return $requestId;
}
