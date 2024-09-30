<?php

function requireAuthentication()
{
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Restricted Access"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required!';
        exit;
    } else {
        // Fetch username and password from environment variables
        $validUsername = $_ENV['AUTH_USERNAME'];
        $validPassword = $_ENV['AUTH_PASSWORD'];
        

        if ($_SERVER['PHP_AUTH_USER'] !== $validUsername || $_SERVER['PHP_AUTH_PW'] !== $validPassword) {
            header('WWW-Authenticate: Basic realm="Restricted Access"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Invalid credentials!';
            exit;
        }
    }
}
