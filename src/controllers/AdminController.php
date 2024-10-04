<?php

function manageProject($ip, $port, $online, $adminKey)
{
    try {
        // Your predefined admin key for authorization
        $validAdminKey = $_ENV['ADMIN_API_SECRET_KEY'];

        // Path to the public directory using __DIR__
        $projectPath = __DIR__ . '../../public';

        // Validate admin key
        if ($adminKey !== $validAdminKey) {
            return json_encode([
                'code' => 403,
                'message' => 'Invalid admin key!'
            ]);
        }

        // Command to check if the server is already running on the specified IP and port
        $checkCommand = "lsof -i :$port";

        // Check if the port is in use
        exec($checkCommand, $output, $resultCode);

        // If project should be online
        if ($online === true) {
            // Check if the project is already running
            if ($resultCode === 0) {
                return json_encode([
                    'code' => 201,
                    'message' => 'Project is already running on ' . $ip . ':' . $port
                ]);
            }

            // Command to start the project with document root set to /public
            $startCommand = "php -S $ip:$port -t $projectPath > /dev/null 2>&1 & echo $!";
            exec($startCommand, $outputStart);

            return json_encode([
                'code' => 200,
                'message' => 'Project started successfully on ' . $ip . ':' . $port . ', PID: ' . $outputStart[0],
            ]);
        } else {
            // If project should be offline
            if ($resultCode !== 0) {
                return json_encode([
                    'code' => 403,
                    'message' => 'Project is not running.'
                ]);
            }

            // Command to kill the process running on the port
            $killCommand = "kill -9 $(lsof -t -i :$port)";
            exec($killCommand);

            return json_encode([
                'code' => 200,
                'message' => 'Project stopped successfully on ' . $ip . ':' . $port
            ]);
        }
    } catch (Exception $e) {
        return [
            'code' => 500,
            'message' => $e->getMessage(),
        ];
    }
}
