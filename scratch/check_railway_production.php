<?php
$urls = [
    'Login' => 'https://thetest2-production.up.railway.app/login',
    'Contacts' => 'https://thetest2-production.up.railway.app/contacts',
    'Message Logs' => 'https://thetest2-production.up.railway.app/logs',
    'Dashboard' => 'https://thetest2-production.up.railway.app/dashboard',
    'Admin Login' => 'https://thetest2-production.up.railway.app/admin/login'
];

echo "=== RAILWAY PRODUCTION LIVE DEPLOYMENT VERIFICATION ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $statusEmoji = ($httpCode >= 200 && $httpCode < 400) ? "🟢 ONLINE" : "🔴 ERROR";
    echo sprintf("[%s] %-15s | Status: HTTP %d (%s) | Response Time: %.2f sec\n", 
        $statusEmoji, $name, $httpCode, $statusEmoji, $totalTime);
}
