<?php

function generateRequestID()
{
    $currentTime = new DateTime("now", new DateTimeZone('Africa/Lagos'));
    $formattedTime = $currentTime->format('YmdHi');
    $randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 12 - strlen($formattedTime));
    return $formattedTime . $randomString;
}
