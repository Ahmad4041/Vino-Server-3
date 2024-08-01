<?php

class DbConnect
{
    private $connections = [];
    private $config;

    function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig()
    {
        // Load configuration from a file or environment variables
        // This is a simplified version; you might want to use a proper configuration system
        $this->config = [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => '127.0.0.1',
                    'port' => '3306',
                    'database' => 'forge',
                    'username' => 'forge',
                    'password' => '',
                    'charset' => 'utf8mb4',
                ],
                '101' => [
                    'driver' => 'sqlsrv',
                    'host' => 'localhost',
                    'port' => '1433',
                    'database' => 'forge',
                    'username' => 'forge',
                    'password' => '',
                    'charset' => 'utf8',
                ],
                'log' => [
                    'driver' => 'sqlsrv',
                    'host' => 'localhost',
                    'port' => '1433',
                    'database' => 'forge',
                    'username' => 'forge',
                    'password' => '',
                    'charset' => 'utf8',
                ],
                '014' => [
                    'driver' => 'sqlsrv',
                    'host' => '10.0.0.24',
                    'port' => '1433',
                    'database' => 'MicroFinance',
                    'username' => 'kiriji',
                    'password' => 'q4M@i93v',
                    'charset' => 'utf8',
                ],
            ],
        ];
    }


    public function getConnections()
    {
        return $this->config['connections'];
    }

    function connect($connection = null)
    {
        $connection = $connection ?: $this->config['default'];

        if (!isset($this->connections[$connection])) {
            if (!isset($this->config['connections'][$connection])) {
                throw new Exception("Undefined connection: $connection");
            }

            $config = $this->config['connections'][$connection];
            $dsn = $this->createDsn($config);

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            try {
                $this->connections[$connection] = new PDO($dsn, $config['username'], $config['password'], $options);

                if ($config['driver'] === 'mysql') {
                    $this->connections[$connection]->exec("SET NAMES {$config['charset']}");
                }
            } catch (PDOException $e) {
                throw new Exception("Connection failed: " . $e->getMessage());
            }
        }

        return $this->connections[$connection];
    }

    private function createDsn($config)
    {
        switch ($config['driver']) {
            case 'mysql':
                return "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
            case 'sqlsrv':
                return "sqlsrv:Server={$config['host']},{$config['port']};Database={$config['database']}";
            default:
                throw new Exception("Unsupported driver: {$config['driver']}");
        }
    }
}
