<?php
// Test actual live media file retrieval from Railway production
$testFilename = '0d3e61b4-ce60-456a-b190-37b2e4a8ae52.jpg'; // or any media filename
$url = "https://thetest2-production.up.railway.app/storage/media/" . $testFilename;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
curl_close($ch);

echo "=== LIVE RAILWAY MEDIA STREAM PROOF ===\n";
echo "Requested URL: " . $url . "\n";
echo "HTTP Response Code: " . $httpCode . "\n";
echo "Content-Type Header: " . $contentType . "\n";
echo "Content-Length: " . $contentLength . " bytes\n";
