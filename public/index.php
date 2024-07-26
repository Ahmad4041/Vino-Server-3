<?php
require '../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Controller\HomeController;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$controller = new HomeController();
$controller->index();
