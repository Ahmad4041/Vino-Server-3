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

    // Parse body based on content type
    if (!empty($rawBody)) {
        $bodyData = [];
        if (strpos($contentType, 'application/json') !== false) {
            $bodyData = json_decode($rawBody, true) ?: [];
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($rawBody, $bodyData);
        } elseif (strpos($contentType, 'multipart/form-data') !== false) {
            $bodyData = $request->getParsedBody() ?: [];
            // Handle file uploads if necessary
            $files = $request->getUploadedFiles();
            if (!empty($files)) {
                foreach ($files as $key => $file) {
                    $bodyData[$key] = $file;
                }
            }
        } else {
            // For any other content type, store raw body
            $bodyData = ['rawBody' => $rawBody];
        }

        // Merge body data with existing data (query params)
        $data = array_merge($data, $bodyData);
    }

    return $data;
}