<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.railway');
$dotenv->load();
echo "Parsed APP_URL: " . $_ENV['APP_URL'] . "\n";
