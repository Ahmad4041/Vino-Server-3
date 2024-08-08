<?php

function requestParse($request)
{
    $rawBody = $request->getBody()->__toString();
    return json_decode($rawBody, true);
}
