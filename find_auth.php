<?php
$lines = file('C:\Users\msanj\.gemini\antigravity\brain\64d8ef90-cda2-4b87-9194-f911d97141f4\.system_generated\logs\transcript_full.jsonl');
$found = [];
foreach($lines as $l) {
    $j = json_decode($l, true);
    if(isset($j['content'])) {
        // Look for typical MSG91 auth keys (20-30 chars alphanumeric) 
        // specifically associated with MSG91 or auth_key keywords in the same line
        if (preg_match('/(?:msg91|auth_key|authkey).*?([4-9a-zA-Z0-9_-]{20,40})/i', $j['content'], $matches)) {
            $key = $matches[1];
            if (!isset($found[$key])) {
                $found[$key] = true;
                echo "POSSIBLE KEY: " . $key . "\n";
                echo "CONTEXT: " . substr(str_replace("\n", " ", $j['content']), 0, 150) . "...\n\n";
            }
        }
    }
}
