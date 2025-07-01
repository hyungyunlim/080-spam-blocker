<?php
// CLI 모드에서는 HTTP 관련 체크 스킵
if (php_sapi_name() === 'cli') {
    $isHttps = false;
} else {
    // HTTPS 여부 감지
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || $_SERVER['SERVER_PORT'] == 443
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

// 세션 쿠키 설정 – 스팸 시스템 전용 도메인 설정
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if (stripos($currentHost, 'spam.juns.mywire.org') !== false) {
    // 스팸 시스템 전용 호스트 기반 쿠키 (다른 서브도메인과 분리)
    ini_set('session.cookie_domain', 'spam.juns.mywire.org');
} elseif (stripos($currentHost, 'juns.mywire.org') !== false) {
    // 기타 juns.mywire.org 서브도메인
    ini_set('session.cookie_domain', $currentHost);
} // IP 접근 또는 다른 도메인일 경우, 기본(host-only) 쿠키 사용
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', $isHttps ? 1 : 0); // HTTPS일 때만 secure
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 365); // 1년

// 세션 이름을 호스트별로 고유하게 설정
$sessionName = 'SPAM_SESSION';
if (stripos($currentHost, 'spam.juns.mywire.org') !== false) {
    $sessionName = 'SPAM_080_SESSION';
} elseif (stripos($currentHost, 'juns.mywire.org') !== false) {
    // 다른 서브도메인과 구분
    $sessionName = 'SPAM_' . strtoupper(str_replace('.', '_', $currentHost));
}
session_name($sessionName);
session_start();

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(){
    if(!is_logged_in()){
        header('Location: login.php');
        exit;
    }
}

function current_user_phone(){
    return $_SESSION['phone'] ?? '';
}

function get_admin_phones(): array {
    require_once __DIR__ . '/config/admin_config.php';
    return get_admin_phones_config();
}

function is_admin(): bool {
    $current_phone = current_user_phone();
    return is_logged_in() && in_array($current_phone, get_admin_phones());
}

function current_user_role(): string {
    return is_admin() ? 'admin' : 'user';
}

function require_admin() {
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}

function get_real_ip_address() {
    // Check for various headers that might contain the real IP
    $ip_headers = [
        'HTTP_CF_CONNECTING_IP',      // Cloudflare
        'HTTP_CLIENT_IP',             // Proxy
        'HTTP_X_FORWARDED_FOR',       // Load balancer/proxy
        'HTTP_X_FORWARDED',           // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
        'HTTP_FORWARDED_FOR',         // Proxy
        'HTTP_FORWARDED',             // Proxy
        'REMOTE_ADDR'                 // Standard
    ];
    
    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function parse_user_agent($user_agent) {
    if (empty($user_agent)) return 'Unknown Device';
    
    // Mobile devices
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|Windows Phone|BlackBerry/', $user_agent)) {
        if (preg_match('/iPhone/', $user_agent)) return '📱 iPhone';
        if (preg_match('/iPad/', $user_agent)) return '📱 iPad';
        if (preg_match('/Android/', $user_agent)) return '📱 Android';
        if (preg_match('/Windows Phone/', $user_agent)) return '📱 Windows Phone';
        if (preg_match('/BlackBerry/', $user_agent)) return '📱 BlackBerry';
        return '📱 Mobile';
    }
    
    // Desktop browsers
    if (preg_match('/Chrome/', $user_agent) && !preg_match('/Edge/', $user_agent)) return '💻 Chrome';
    if (preg_match('/Firefox/', $user_agent)) return '💻 Firefox';
    if (preg_match('/Safari/', $user_agent) && !preg_match('/Chrome/', $user_agent)) return '💻 Safari';
    if (preg_match('/Edge/', $user_agent)) return '💻 Edge';
    if (preg_match('/Opera/', $user_agent)) return '💻 Opera';
    if (preg_match('/Trident|MSIE/', $user_agent)) return '💻 Internet Explorer';
    
    return '💻 Desktop';
}

function update_last_access($phone = null) {
    if (!$phone) {
        $phone = current_user_phone();
    }
    if (!$phone) return;
    
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');

        // 현재 users 테이블에 컬럼이 존재하는지 확인 (마이그레이션 호환)
        $columnsRes = $db->query('PRAGMA table_info(users)');
        $columns = [];
        while ($col = $columnsRes->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $col['name'];
        }

        $hasUA = in_array('last_user_agent', $columns, true);
        $hasIP = in_array('last_ip_address', $columns, true);

        // 동적 SET 절 구성
        $setParts = ["last_access = datetime('now')"];
        if ($hasUA)  $setParts[] = 'last_user_agent = :user_agent';
        if ($hasIP)  $setParts[] = 'last_ip_address = :ip_address';
        $setClause = implode(', ', $setParts);

        $sql = "UPDATE users SET {$setClause} WHERE phone = :phone";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        if ($hasUA) $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '', SQLITE3_TEXT);
        if ($hasIP) $stmt->bindValue(':ip_address', get_real_ip_address(), SQLITE3_TEXT);

        $stmt->execute();
        $db->close();
    } catch (Exception $e) {
        error_log("Failed to update last access: " . $e->getMessage());
    }
}

function get_user_stats($phone) {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        
        // 기본 사용자 정보
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = :phone");
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        if (!$user) return null;
        
        // 통계 수집
        $stats = [
            'user' => $user,
            'total_calls' => 0,
            'successful_calls' => 0,
            'patterns_created' => 0,
            'last_call_date' => null,
            'sms_sent' => 0
        ];
        
        // 전화 통계
        $stmt = $db->prepare("
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                   MAX(created_at) as last_call
            FROM unsubscribe_calls 
            WHERE user_id = :user_id
        ");
        $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $callStats = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($callStats) {
            $stats['total_calls'] = $callStats['total'];
            $stats['successful_calls'] = $callStats['successful'];
            $stats['last_call_date'] = $callStats['last_call'];
        }
        
        // SMS 발송 통계
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM sms_incoming 
            WHERE user_id = :user_id
        ");
        $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        $smsStats = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($smsStats) {
            $stats['sms_sent'] = $smsStats['total'];
        }
        
        // 패턴 생성 통계 (patterns.json에서 확인)
        $patternFile = __DIR__ . '/patterns.json';
        if (file_exists($patternFile)) {
            $patterns = json_decode(file_get_contents($patternFile), true);
            if ($patterns && isset($patterns['patterns'])) {
                $userPatterns = 0;
                foreach ($patterns['patterns'] as $pattern) {
                    if (isset($pattern['owner_phone']) && $pattern['owner_phone'] === $phone) {
                        $userPatterns++;
                    }
                }
                $stats['patterns_created'] = $userPatterns;
            }
        }
        
        $db->close();
        return $stats;
        
    } catch (Exception $e) {
        error_log("Failed to get user stats: " . $e->getMessage());
        return null;
    }
}

function get_all_users() {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $result = $db->query("SELECT * FROM users ORDER BY last_access DESC");
        
        $users = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        
        $db->close();
        return $users;
        
    } catch (Exception $e) {
        error_log("Failed to get all users: " . $e->getMessage());
        return [];
    }
}

function promote_to_admin($phone) {
    // 이 함수는 데이터베이스가 아닌 코드 수정이 필요하므로 
    // 실제로는 설정 파일이나 환경변수를 사용하는 것이 좋습니다
    return false; // 보안상 코드로 구현
}

function block_user($phone, $blocked = true) {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $status = $blocked ? 1 : 0;
        $db->exec("UPDATE users SET blocked = {$status} WHERE phone = '{$phone}'");
        $db->close();
        return true;
    } catch (Exception $e) {
        error_log("Failed to block/unblock user: " . $e->getMessage());
        return false;
    }
}

function is_user_blocked($phone) {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $result = $db->querySingle("SELECT blocked FROM users WHERE phone = '{$phone}'");
        $db->close();
        return (bool)$result;
    } catch (Exception $e) {
        return false;
    }
}

function create_user($phone, $role = 'user') {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $phone = $db->escapeString($phone);
        
        // 중복 체크
        $existing = $db->querySingle("SELECT id FROM users WHERE phone = '{$phone}'");
        if ($existing) {
            $db->close();
            return ['success' => false, 'message' => '이미 존재하는 사용자입니다.'];
        }
        
        // 관리자로 생성하는 경우 자동으로 auth.php 파일 수정
        if ($role === 'admin') {
            $result = $db->exec("INSERT INTO users (phone, verified, created_at) VALUES ('{$phone}', 1, datetime('now'))");
            if ($result) {
                // auth.php 파일에 관리자 번호 자동 추가
                $authFile = __DIR__ . '/auth.php';
                $content = file_get_contents($authFile);
                
                // get_admin_phones 함수에서 번호 배열 찾기
                $pattern = '/(return \[\s*)(.*?)(\s*\];)/s';
                if (preg_match($pattern, $content, $matches)) {
                    $currentNumbers = $matches[2];
                    // 이미 존재하는지 확인
                    if (strpos($currentNumbers, "'{$phone}'") === false) {
                        $newNumbers = trim($currentNumbers) . ",\n        '{$phone}'   // 자동 추가된 관리자";
                        $newContent = str_replace($matches[0], $matches[1] . $newNumbers . $matches[3], $content);
                        file_put_contents($authFile, $newContent);
                    }
                }
                
                $db->close();
                return ['success' => true, 'message' => '관리자 사용자가 생성되고 권한이 자동으로 설정되었습니다.'];
            } else {
                $db->close();
                return ['success' => false, 'message' => '사용자 생성에 실패했습니다.'];
            }
        } else {
            $result = $db->exec("INSERT INTO users (phone, verified, created_at) VALUES ('{$phone}', 1, datetime('now'))");
            $db->close();
            
            if ($result) {
                return ['success' => true, 'message' => '사용자가 생성되었습니다.'];
            } else {
                return ['success' => false, 'message' => '사용자 생성에 실패했습니다.'];
            }
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '오류: ' . $e->getMessage()];
    }
}

function delete_user($phone) {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $phone = $db->escapeString($phone);
        
        // 어드민 사용자 삭제 방지
        if (in_array($phone, get_admin_phones())) {
            $db->close();
            return ['success' => false, 'message' => '관리자 계정은 삭제할 수 없습니다.'];
        }
        
        // 관련 데이터도 함께 삭제
        $userId = $db->querySingle("SELECT id FROM users WHERE phone = '{$phone}'");
        if (!$userId) {
            $db->close();
            return ['success' => false, 'message' => '사용자를 찾을 수 없습니다.'];
        }
        
        // 트랜잭션 시작
        $db->exec('BEGIN TRANSACTION');
        
        // 사용자의 패턴을 익명화 (패턴 자체는 보존)
        $patterns = [];
        $patternsFile = __DIR__ . '/patterns.json';
        $patternsModified = false;
        
        if (file_exists($patternsFile)) {
            $patternsData = json_decode(file_get_contents($patternsFile), true);
            if ($patternsData && isset($patternsData['patterns'])) {
                $patterns = $patternsData['patterns'];
                
                foreach ($patterns as $phoneNumber => &$pattern) {
                    if (isset($pattern['owner_phone']) && $pattern['owner_phone'] === $phone) {
                        // 소유자 정보를 익명화
                        $pattern['owner_phone'] = 'anonymous_' . substr(md5($phone), 0, 8);
                        $pattern['anonymized_at'] = date('Y-m-d H:i:s');
                        $pattern['original_owner_removed'] = true;
                        $pattern['admin_removed'] = true;
                        
                        // 패턴 이름도 익명화
                        if (isset($pattern['name']) && strpos($pattern['name'], $phone) !== false) {
                            $pattern['name'] = str_replace($phone, '익명사용자', $pattern['name']);
                        }
                        
                        $patternsModified = true;
                    }
                }
                
                // 패턴 파일 업데이트
                if ($patternsModified) {
                    $patternsData['patterns'] = $patterns;
                    file_put_contents($patternsFile, json_encode($patternsData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }
        }
        
        // 관련 데이터 삭제
        $db->exec("DELETE FROM verification_codes WHERE user_id = {$userId}");
        $db->exec("DELETE FROM sms_incoming WHERE user_id = {$userId}");
        $db->exec("DELETE FROM unsubscribe_calls WHERE user_id = {$userId}");
        
        // 사용자 삭제
        $result = $db->exec("DELETE FROM users WHERE phone = '{$phone}'");
        
        if ($result) {
            $db->exec('COMMIT');
            $db->close();
            $message = '사용자가 삭제되었습니다.';
            if ($patternsModified) {
                $message .= ' (소유 패턴들은 익명화되어 보존되었습니다.)';
            }
            return ['success' => true, 'message' => $message];
        } else {
            $db->exec('ROLLBACK');
            $db->close();
            return ['success' => false, 'message' => '사용자 삭제에 실패했습니다.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '오류: ' . $e->getMessage()];
    }
}

function update_user($phone, $data) {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        $phone = $db->escapeString($phone);
        
        $updates = [];
        
        if (isset($data['verified'])) {
            $verified = $data['verified'] ? 1 : 0;
            $updates[] = "verified = {$verified}";
        }
        
        if (isset($data['blocked'])) {
            $blocked = $data['blocked'] ? 1 : 0;
            $updates[] = "blocked = {$blocked}";
        }
        
        if (isset($data['new_phone'])) {
            $newPhone = $db->escapeString($data['new_phone']);
            // 새 번호 중복 체크
            $existing = $db->querySingle("SELECT id FROM users WHERE phone = '{$newPhone}' AND phone != '{$phone}'");
            if ($existing) {
                $db->close();
                return ['success' => false, 'message' => '새 전화번호가 이미 사용 중입니다.'];
            }
            $updates[] = "phone = '{$newPhone}'";
        }
        
        if (empty($updates)) {
            $db->close();
            return ['success' => false, 'message' => '수정할 데이터가 없습니다.'];
        }
        
        $updateQuery = "UPDATE users SET " . implode(', ', $updates) . " WHERE phone = '{$phone}'";
        $result = $db->exec($updateQuery);
        $db->close();
        
        if ($result) {
            return ['success' => true, 'message' => '사용자 정보가 수정되었습니다.'];
        } else {
            return ['success' => false, 'message' => '사용자 정보 수정에 실패했습니다.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => '오류: ' . $e->getMessage()];
    }
}

// 세션 디버깅 함수
function debug_session_info() {
    global $isHttps;
    
    return [
        'session_id' => session_id(),
        'session_name' => session_name(),
        'cookie_params' => session_get_cookie_params(),
        'is_logged_in' => is_logged_in(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'phone' => $_SESSION['phone'] ?? null,
        'server_name' => $_SERVER['SERVER_NAME'] ?? null,
        'http_host' => $_SERVER['HTTP_HOST'] ?? null,
        'is_https' => $isHttps,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'session_data' => $_SESSION ?? [],
    ];
}

// flash message helpers
function set_flash(string $msg){
    $_SESSION['flash']=$msg;
}
function get_flash(): ?string {
    if(isset($_SESSION['flash'])){
        $m=$_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}