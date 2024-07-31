<?php
// index.php

require_once 'database.php';

// Get the bank ID from the query parameter
$bankId = $_GET['bankid'] ?? null;

if (!$bankId) {
    die("Bank ID is required");
}

try {
    // Attempt to establish a connection
    $pdo = Database::getConnection($bankId);
    echo "Connection established successfully for bank ID: $bankId";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
