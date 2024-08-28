<?php

function requestParse($request)
{
    $data = [];

    // Parse query parameters
    $queryParams = $request->getQueryParams();
    if (!empty($queryParams)) {
        $data = $queryParams;
    }

    // Get content type and raw body
    $contentType = $request->getHeaderLine('Content-Type');
    $rawBody = $request->getBody()->__toString();

    // Try to parse the body intelligently
    $bodyData = [];

    // First, try to parse as JSON
    $jsonData = json_decode($rawBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $bodyData = $jsonData;
    } 
    // If not JSON, try to parse as URL-encoded
    elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false || isUrlEncoded($rawBody)) {
        parse_str($rawBody, $bodyData);
    } 
    // If not URL-encoded, check for multipart form data
    elseif (strpos($contentType, 'multipart/form-data') !== false) {
        $bodyData = $request->getParsedBody() ?: [];
        // Handle file uploads
        $files = $request->getUploadedFiles();
        if (!empty($files)) {
            foreach ($files as $key => $file) {
                $bodyData[$key] = $file;
            }
        }
    }
    // If none of the above, store raw body
    else {
        $bodyData = ['rawBody' => $rawBody];
    }

    // Merge body data with existing data
    $data = arrayMergeRecursiveDistinct($data, $bodyData);

    // Check for any additional parsed body data
    $parsedBody = $request->getParsedBody();
    if (!empty($parsedBody) && is_array($parsedBody)) {
        $data = arrayMergeRecursiveDistinct($data, $parsedBody);
    }

    return $data;
}

function isUrlEncoded($string) {
    return urldecode($string) !== $string;
}

function arrayMergeRecursiveDistinct(array $array1, array $array2)
{
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = arrayMergeRecursiveDistinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }
    return $merged;
}