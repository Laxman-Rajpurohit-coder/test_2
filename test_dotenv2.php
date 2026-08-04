<?php
require 'vendor/autoload.php';
putenv('RAILWAY_PUBLIC_DOMAIN=example.com');
$_ENV['RAILWAY_PUBLIC_DOMAIN'] = 'example.com';
$_SERVER['RAILWAY_PUBLIC_DOMAIN'] = 'example.com';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.railway');
$dotenv->load();

echo "Parsed APP_URL: " . $_ENV['APP_URL'] . "\n";
