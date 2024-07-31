<?php

/**
 * Handling database connection for MS SQL Server with Windows Authentication
 */
// class DatabaseConnect
// {
//     private $conn;

//     function __construct()
//     {
//     }

//     /**
//      * Establishing database connection
//      * @return database connection handler
//      */
//     function connect($serverName, $databaseName)
//     {
//         // Connection string for SQL Server using Windows Authentication
//         $connectionInfo = array(
//             "Database" => $databaseName,
//             "CharacterSet" => "UTF-8"
//         );

//         try {
//             // Attempt to connect to SQL Server
//             $this->conn = sqlsrv_connect($serverName, $connectionInfo);

//             if ($this->conn === false) {
//                 $errors = sqlsrv_errors();
//                 throw new Exception("Connection failed: " . $errors[0]['message']);
//             }
//         } catch (Exception $e) {
//             echo "Connection failed: " . $e->getMessage();
//             exit; // Exit if connection fails
//         }

//         // Returning connection resource
//         return $this->conn;
//     }

//     public function getConnection()
//     {
//         return $this->conn;
//     }
// }
