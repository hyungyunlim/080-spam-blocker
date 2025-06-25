<?php
/**
 * Thin wrapper: allows other PHP files to include the PatternManager class
 * via lowercase path without getting HTML output, while still letting users
 * open /spam/pattern_manager.php in browser to see the UI.
 */

$isDirect = (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__);

if ($isDirect) {
    // 브라우저에서 직접 접근한 경우: UI 페이지 출력
    require __DIR__ . '/PatternManager.php';
    exit;
} else {
    // 다른 파일이 include 할 때: 클래스만 로드하고 HTML은 버림
    // Class-only load to avoid HTML output duplication
    ob_start();
    require_once __DIR__ . '/PatternManager.php';
    ob_end_clean();
}

// End of wrapper (no closing PHP tag)