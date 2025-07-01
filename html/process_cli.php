<?php
/**
 * CLI-only version of process_v2.php 
 * Designed specifically for background call processing without session dependencies
 */

// Ensure this is only run from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line');
}

// Set error handling for CLI
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/call_process_errors.log');

// Get CLI arguments
$options = getopt('', [
    'phone:',      // --phone=080xxxxxx (required)
    'notification::', // --notification=010...
    'id::',           // --id=1234 (identification)
    'auto',          // --auto flag
]);

if (!isset($options['phone'])) {
    fwrite(STDERR, "--phone parameter required\n");
    exit(1);
}

// Extract parameters
$phoneNumber = $options['phone'];
$notificationPhone = $options['notification'] ?? '';
$identificationNumber = $options['id'] ?? '';

if (empty($notificationPhone)) {
    fwrite(STDERR, "Error: Notification phone is required\n");
    exit(1);
}

// Wait for modem to be ready (SMS sending might still be in progress)
$maxWait = 15; // Maximum 15 seconds
$waited = 0;
error_log("[PROCESS_CLI] Starting modem wait check for {$phoneNumber}");

while ($waited < $maxWait) {
    $out = [];
    $modemStatus = [];
    exec('echo "hacker03" | sudo -S /usr/sbin/asterisk -rx "quectel show devices" 2>/dev/null | grep quectel0', $modemStatus);
    exec('echo "hacker03" | sudo -S /usr/sbin/asterisk -rx "quectel show devices" 2>/dev/null | grep quectel0 | grep Free', $out);
    
    $status = isset($modemStatus[0]) ? trim($modemStatus[0]) : 'Unknown';
    $isFree = count($out) > 0;
    
    error_log("[PROCESS_CLI] Wait check #{$waited}: {$status}, isFree: " . ($isFree ? 'true' : 'false'));
    
    if ($isFree) {
        break; // Modem is Free
    }
    sleep(1);
    $waited++;
}

error_log("[PROCESS_CLI] Modem wait completed after {$waited} seconds");

// Log the start
$logMessage = sprintf(
    "[%s] CLI Call Processing: phone=%s, id=%s, notify=%s (waited %ds for modem)\n",
    date('Y-m-d H:i:s'),
    $phoneNumber,
    $identificationNumber,
    $notificationPhone,
    $waited
);
file_put_contents('/tmp/call_process.log', $logMessage, FILE_APPEND);

// Load patterns without session dependencies
$patternsFile = __DIR__ . '/patterns.json';
$patterns = [];
if (file_exists($patternsFile)) {
    $patternsData = json_decode(file_get_contents($patternsFile), true);
    if ($patternsData && isset($patternsData['patterns'])) {
        $patterns = $patternsData['patterns'];
    }
}

// Find pattern for the phone number
$pattern = null;
$patternSource = 'none';

if (isset($patterns[$phoneNumber])) {
    $pattern = $patterns[$phoneNumber];
    $patternSource = 'user';
} elseif (isset($patterns['default'])) {
    $pattern = $patterns['default'];
    $patternSource = 'default';
} else {
    file_put_contents('/tmp/call_process.log', "No pattern found for $phoneNumber\n", FILE_APPEND);
    exit(1);
}

// Generate Call File
$uniqueId = uniqid();

// Database logging
try {
    $dbPath = __DIR__ . '/spam.db';
    $db = new SQLite3($dbPath);
    $cleanNotifyDigits = preg_replace('/[^0-9]/', '', $notificationPhone);
    
    $uidRow = $db->querySingle("SELECT id FROM users WHERE phone='{$cleanNotifyDigits}'", true);
    $uidVal = $uidRow ? (int)$uidRow['id'] : null;
    
    $stmt = $db->prepare('INSERT OR IGNORE INTO unsubscribe_calls (call_id,user_id,phone080,identification,created_at,status,pattern_source,notification_phone) VALUES (?,?,?,?,datetime("now"),"pending",?,?)');
    $stmt->bindValue(1, $uniqueId, SQLITE3_TEXT);
    $stmt->bindValue(2, $uidVal, $uidVal !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(3, $phoneNumber, SQLITE3_TEXT);
    $stmt->bindValue(4, $identificationNumber, SQLITE3_TEXT);
    $stmt->bindValue(5, $patternSource, SQLITE3_TEXT);
    $stmt->bindValue(6, $notificationPhone, SQLITE3_TEXT);
    $stmt->execute();
    $db->close();
} catch (Exception $e) {
    file_put_contents('/tmp/call_process.log', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Store variables in AstDB
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} dtmf {$pattern['dtmf_pattern']}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} notification_phone {$notificationPhone}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} identification_number {$identificationNumber}\"");
exec("/usr/sbin/asterisk -rx \"database put CallFile/{$uniqueId} recnum {$phoneNumber}\"");

// Create Call File
$callFileContent = "Channel: quectel/quectel0/{$phoneNumber}\n";
$callFileContent .= "CallerID: \"Spam Blocker\" <0212345678>\n";
$callFileContent .= "MaxRetries: 0\n";
$callFileContent .= "RetryTime: 60\n";
$callFileContent .= "WaitTime: 45\n";
$callFileContent .= "Context: callfile-handler\n";
$callFileContent .= "Extension: s\n";
$callFileContent .= "Priority: 1\n";
$callFileContent .= "Set: CALL_ID={$uniqueId}\n";
$callFileContent .= "Set: CALLFILE_ID={$uniqueId}\n";
$callFileContent .= "Set: __AUTO_CALL=1\n";

// Set pattern variables
foreach (['confirmation_wait', 'total_duration', 'initial_wait', 'dtmf_timing'] as $key) {
    if (isset($pattern[$key])) {
        $callFileContent .= "Set: __" . strtoupper($key) . "={$pattern[$key]}\n";
    }
}

// Create temporary call file
$tempDir = __DIR__ . '/tmp_calls/';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

$tempFile = $tempDir . 'call_' . $uniqueId . '.call';
file_put_contents($tempFile, $callFileContent);

// Check modem status and place accordingly
function modem_is_busy(): bool {
    $out = [];
    exec('/usr/sbin/asterisk -rx "core show channels concise" | grep quectel', $out);
    return count($out) > 0;
}

$queueDir = __DIR__ . '/call_queue/';
$destinationDir = modem_is_busy() ? $queueDir : '/var/spool/asterisk/outgoing/';

if (!is_dir($queueDir)) {
    mkdir($queueDir, 0775, true);
}

$finalFile = $destinationDir . basename($tempFile);

if (rename($tempFile, $finalFile)) {
    @chown($finalFile, 'asterisk');
    @chgrp($finalFile, 'asterisk');
    
    $status = ($destinationDir === $queueDir) ? 'queued' : 'immediate';
    $logMessage = sprintf(
        "[%s] Call file created successfully: %s (%s)\n",
        date('Y-m-d H:i:s'),
        $finalFile,
        $status
    );
    file_put_contents('/tmp/call_process.log', $logMessage, FILE_APPEND);
    
    echo "SUCCESS: Call file created - $finalFile ($status)\n";
    exit(0);
} else {
    $logMessage = sprintf(
        "[%s] FAILED to create call file: %s\n",
        date('Y-m-d H:i:s'),
        $finalFile
    );
    file_put_contents('/tmp/call_process.log', $logMessage, FILE_APPEND);
    
    echo "ERROR: Failed to create call file\n";
    exit(1);
}
?>