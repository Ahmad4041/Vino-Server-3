<?php

function requestParse($request)
{
    $rawBody = $request->getBody()->__toString();
    $contentType = $request->getHeaderLine('Content-Type');

    if (strpos($contentType, 'application/json') !== false) {
        return json_decode($rawBody, true);
    } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str($rawBody, $data);
        return $data;
    } elseif (strpos($contentType, 'multipart/form-data') !== false) {
        return $request->getParsedBody();
    } else {
        return $rawBody;
    }
}
