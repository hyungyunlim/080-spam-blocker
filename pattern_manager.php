<?php
// legacy wrapper to maintain backward compatibility with lowercase path
ob_start();
require_once __DIR__ . '/PatternManager.php';
ob_end_clean();

// If accessed directly via browser, display UI
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    // flush buffered HTML if any
    ob_end_clean();
    readfile(__DIR__ . '/PatternManager.php');
    exit;
} 