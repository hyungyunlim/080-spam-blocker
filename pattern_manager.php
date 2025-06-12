<?php
// legacy wrapper to maintain backward compatibility with lowercase path
ob_start();
require_once __DIR__ . '/PatternManager.php';
ob_end_clean(); 