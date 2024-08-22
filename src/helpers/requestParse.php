<?php

function requestParse($request)
{
    $data = [];

    $queryParams = $request->getQueryParams();
    if (!empty($queryParams)) {
        $data['query'] = $queryParams;
    }

    $contentType = $request->getHeaderLine('Content-Type');
    $rawBody = $request->getBody()->__toString();

    if (strpos($contentType, 'application/json') !== false) {
        $data['body'] = json_decode($rawBody, true);
    } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str($rawBody, $parsedBody);
        $data['body'] = $parsedBody;
    } elseif (strpos($contentType, 'multipart/form-data') !== false) {
        $data['body'] = $request->getParsedBody();
    } else {
        $data['body'] = $rawBody;
    }

    return $data;
}
