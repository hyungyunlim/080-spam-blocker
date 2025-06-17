<?php
/**
 * Migrate patterns.json to SQL database
 * 
 * Usage: php migrate_patterns_to_sql.php [--dry-run]
 */

$isDryRun = in_array('--dry-run', $argv);
$basePath = __DIR__;

echo "=== Patterns.json to SQL Migration ===\n";
echo "Mode: " . ($isDryRun ? "DRY RUN" : "LIVE") . "\n\n";

try {
    // 1. Check files exist
    $patternsFile = $basePath . '/patterns.json';
    $schemaFile = $basePath . '/patterns_schema.sql';
    $dbFile = $basePath . '/spam.db';
    
    if (!file_exists($patternsFile)) {
        throw new Exception("patterns.json not found at: $patternsFile");
    }
    
    if (!file_exists($schemaFile)) {
        throw new Exception("patterns_schema.sql not found at: $schemaFile");
    }
    
    echo "✓ Files found\n";
    
    // 2. Load patterns.json
    $jsonData = json_decode(file_get_contents($patternsFile), true);
    if (!$jsonData) {
        throw new Exception("Failed to parse patterns.json");
    }
    
    $patterns = $jsonData['patterns'] ?? [];
    $variables = $jsonData['variables'] ?? [];
    
    echo "✓ Loaded " . count($patterns) . " patterns and " . count($variables) . " variables\n";
    
    if ($isDryRun) {
        echo "\n--- PATTERNS TO MIGRATE ---\n";
        foreach ($patterns as $phone080 => $pattern) {
            echo "- $phone080: " . ($pattern['name'] ?? 'Unknown') . "\n";
        }
        
        echo "\n--- VARIABLES TO MIGRATE ---\n";
        foreach ($variables as $var => $desc) {
            echo "- $var: $desc\n";
        }
        
        echo "\n[DRY RUN] Migration would continue here...\n";
        exit(0);
    }
    
    // 3. Open database connection
    $db = new SQLite3($dbFile);
    if (!$db) {
        throw new Exception("Failed to open database: $dbFile");
    }
    
    echo "✓ Database connection opened\n";
    
    // 4. Create tables from schema
    $schemaSql = file_get_contents($schemaFile);
    $statements = explode(';', $schemaSql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        $result = $db->exec($statement);
        if ($result === false) {
            throw new Exception("Failed to execute schema statement: " . $db->lastErrorMsg());
        }
    }
    
    echo "✓ Database schema created/updated\n";
    
    // 5. Migrate patterns
    $patternStmt = $db->prepare("
        INSERT OR REPLACE INTO patterns (
            phone080, name, description, initial_wait, dtmf_timing, dtmf_pattern,
            confirmation_wait, confirmation_dtmf, total_duration, confirm_delay,
            confirm_repeat, pattern_type, auto_supported, notes, usage_count,
            last_used, created_at, updated_at, created_by, updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $migratedPatterns = 0;
    foreach ($patterns as $phone080 => $pattern) {
        $patternStmt->bindValue(1, $phone080, SQLITE3_TEXT);
        $patternStmt->bindValue(2, $pattern['name'] ?? '', SQLITE3_TEXT);
        $patternStmt->bindValue(3, $pattern['description'] ?? '', SQLITE3_TEXT);
        $patternStmt->bindValue(4, $pattern['initial_wait'] ?? 3, SQLITE3_INTEGER);
        $patternStmt->bindValue(5, $pattern['dtmf_timing'] ?? 6, SQLITE3_INTEGER);
        $patternStmt->bindValue(6, $pattern['dtmf_pattern'] ?? '{ID}#', SQLITE3_TEXT);
        $patternStmt->bindValue(7, $pattern['confirmation_wait'] ?? 2, SQLITE3_INTEGER);
        $patternStmt->bindValue(8, $pattern['confirmation_dtmf'] ?? '1', SQLITE3_TEXT);
        $patternStmt->bindValue(9, $pattern['total_duration'] ?? 30, SQLITE3_INTEGER);
        $patternStmt->bindValue(10, $pattern['confirm_delay'] ?? 2, SQLITE3_INTEGER);
        $patternStmt->bindValue(11, $pattern['confirm_repeat'] ?? 3, SQLITE3_INTEGER);
        $patternStmt->bindValue(12, $pattern['pattern_type'] ?? 'standard', SQLITE3_TEXT);
        $patternStmt->bindValue(13, ($pattern['auto_supported'] ?? true) ? 1 : 0, SQLITE3_INTEGER);
        $patternStmt->bindValue(14, $pattern['notes'] ?? '', SQLITE3_TEXT);
        $patternStmt->bindValue(15, $pattern['usage_count'] ?? 0, SQLITE3_INTEGER);
        $patternStmt->bindValue(16, $pattern['last_used'] ?? null, SQLITE3_TEXT);
        $patternStmt->bindValue(17, $pattern['created_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $patternStmt->bindValue(18, $pattern['updated_at'] ?? date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $patternStmt->bindValue(19, $pattern['created_by'] ?? 'migration', SQLITE3_TEXT);
        $patternStmt->bindValue(20, $pattern['updated_by'] ?? 'migration', SQLITE3_TEXT);
        
        if ($patternStmt->execute()) {
            $migratedPatterns++;
        } else {
            echo "Warning: Failed to migrate pattern $phone080\n";
        }
    }
    
    echo "✓ Migrated $migratedPatterns patterns\n";
    
    // 6. Migrate variables
    $varStmt = $db->prepare("
        INSERT OR REPLACE INTO pattern_variables (variable, description, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
    ");
    
    $migratedVars = 0;
    foreach ($variables as $var => $desc) {
        $varStmt->bindValue(1, $var, SQLITE3_TEXT);
        $varStmt->bindValue(2, $desc, SQLITE3_TEXT);
        
        if ($varStmt->execute()) {
            $migratedVars++;
        } else {
            echo "Warning: Failed to migrate variable $var\n";
        }
    }
    
    echo "✓ Migrated $migratedVars variables\n";
    
    // 7. Create backup of original JSON
    $backupFile = $patternsFile . '.backup.' . date('YmdHis');
    if (copy($patternsFile, $backupFile)) {
        echo "✓ Backup created: $backupFile\n";
    } else {
        echo "Warning: Failed to create backup\n";
    }
    
    $db->close();
    
    echo "\n=== Migration completed successfully! ===\n";
    echo "Next steps:\n";
    echo "1. Update PatternManager.php to use SQL instead of JSON\n";
    echo "2. Test all functionality\n";
    echo "3. Remove patterns.json after verification\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}