<?php
$baseUrl = 'https://thetest2-production.up.railway.app';

$endpoints = [
    'Auth Login' => '/login',
    'Contacts Page' => '/contacts',
    'Message Logs' => '/logs',
    'SAAS Dashboard' => '/dashboard',
    'Admin Login' => '/admin/login',
    'Live Chat Inbox' => '/chat/whatsapp',
    'MSG91 Webhook' => '/api/msg91/webhook',
    'Meta Webhook' => '/webhooks/meta?hub.mode=subscribe&hub.challenge=123456&hub.verify_token=test',
    'Media Storage Test' => '/storage/media/test.jpg'
];

echo "===============================================================\n";
echo "       RAILWAY PRODUCTION COMPREHENSIVE DIAGNOSTIC SUITE       \n";
echo "===============================================================\n";
echo "Target URL: " . $baseUrl . "\n";
echo "Execution Time: " . date('Y-m-d H:i:s T') . "\n";
echo "===============================================================\n\n";

$results = [];
$totalTimeSum = 0;
$count = 0;

foreach ($endpoints as $name => $path) {
    $url = $baseUrl . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);
    
    $isOk = ($httpCode >= 200 && $httpCode < 400) || ($name === 'Media Storage Test' && $httpCode === 404) || ($name === 'MSG91 Webhook' && ($httpCode === 400 || $httpCode === 405 || $httpCode === 200));
    $statusEmoji = $isOk ? "🟢 PASS" : "🔴 FAIL";
    
    echo sprintf("[%s] %-20s | Code: HTTP %-3d | Time: %5.2f ms | Content-Type: %s\n",
        $statusEmoji,
        $name,
        $httpCode,
        $totalTime * 1000,
        $contentType ?: 'N/A'
    );
    
    $results[] = [
        'name' => $name,
        'http_code' => $httpCode,
        'time_ms' => round($totalTime * 1000, 2),
        'content_type' => $contentType,
        'pass' => $isOk
    ];
    
    $totalTimeSum += $totalTime;
    $count++;
}

echo "\n---------------------------------------------------------------\n";
echo sprintf("Average Endpoint Response Time: %.2f ms\n", ($totalTimeSum / $count) * 1000);
echo "Diagnostic Run Completed.\n";
echo "===============================================================\n";
