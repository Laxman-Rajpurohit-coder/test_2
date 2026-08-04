<?php
require 'vendor/autoload.php';
try {
    Illuminate\Http\Request::create('https://${RAILWAY_PUBLIC_DOMAIN}');
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
}
