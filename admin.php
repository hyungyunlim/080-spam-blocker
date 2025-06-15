<?php
require_once __DIR__ . '/auth.php';

// 로그인되지 않은 상태로 admin.php에 직접 접근한 경우 admin_login.php로 리다이렉션
if (!is_logged_in()) {
    header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/admin-login');
    exit;
}

// 로그인은 되어있지만 어드민이 아닌 경우 메인으로 리다이렉션
if (!is_admin()) {
    header('Location: index.php');
    exit;
}

// 어드민 권한 확인 (기존 코드 유지)
require_admin();

// 현재 로그인 시간 업데이트
update_last_access();

$message = '';

// GET 파라미터로 전달된 성공 메시지 처리
if (isset($_GET['created'])) {
    $message = '<div class="alert alert-success auto-hide">✅ 새 사용자가 성공적으로 생성되었습니다.</div>';
} elseif (isset($_GET['updated'])) {
    $message = '<div class="alert alert-success auto-hide">✅ 사용자 정보가 성공적으로 수정되었습니다.</div>';
} elseif (isset($_GET['deleted'])) {
    $message = '<div class="alert alert-success auto-hide">✅ 사용자가 성공적으로 삭제되었습니다.</div>';
} elseif (isset($_GET['blocked'])) {
    $message = '<div class="alert alert-success auto-hide">✅ 사용자를 차단했습니다.</div>';
} elseif (isset($_GET['unblocked'])) {
    $message = '<div class="alert alert-success auto-hide">✅ 사용자 차단을 해제했습니다.</div>';
}

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_phone = $_POST['phone'] ?? '';
    
    try {
        switch ($action) {
            case 'create_user':
                $new_phone = $_POST['new_phone'] ?? '';
                $role = $_POST['role'] ?? 'user';
                
                if (empty($new_phone)) {
                    $message = '<div class="alert alert-error">❌ 전화번호를 입력하세요.</div>';
                } else {
                    $result = create_user($new_phone, $role);
                    if ($result['success']) {
                        // 생성 성공 시 리다이렉트
                        header('Location: ' . $_SERVER['PHP_SELF'] . '?created=1');
                        exit;
                    } else {
                        $message = '<div class="alert alert-error">❌ ' . $result['message'] . '</div>';
                    }
                }
                break;
                
            case 'update_user':
                $update_data = [];
                if (isset($_POST['verified'])) {
                    $update_data['verified'] = $_POST['verified'] === '1';
                }
                if (isset($_POST['blocked'])) {
                    $update_data['blocked'] = $_POST['blocked'] === '1';
                }
                if (!empty($_POST['new_phone']) && $_POST['new_phone'] !== $target_phone) {
                    $update_data['new_phone'] = $_POST['new_phone'];
                }
                
                $result = update_user($target_phone, $update_data);
                if ($result['success']) {
                    // 수정 성공 시 리다이렉트
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
                    exit;
                } else {
                    $message = '<div class="alert alert-error">❌ ' . $result['message'] . '</div>';
                }
                break;
                
            case 'delete_user':
                $result = delete_user($target_phone);
                if ($result['success']) {
                    // 삭제 성공 시 리다이렉트
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?deleted=1');
                    exit;
                } else {
                    $message = '<div class="alert alert-error">❌ ' . $result['message'] . '</div>';
                }
                break;
                
            case 'block_user':
                if (block_user($target_phone, true)) {
                    // 차단 성공 시 리다이렉트
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?blocked=1');
                    exit;
                } else {
                    $message = '<div class="alert alert-error">❌ 사용자 차단에 실패했습니다.</div>';
                }
                break;
                
            case 'unblock_user':
                if (block_user($target_phone, false)) {
                    // 차단 해제 성공 시 리다이렉트
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?unblocked=1');
                    exit;
                } else {
                    $message = '<div class="alert alert-error">❌ 차단 해제에 실패했습니다.</div>';
                }
                break;
                
            default:
                $message = '<div class="alert alert-error">❌ 알 수 없는 액션입니다.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-error">❌ 오류: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// 데이터 수집
$all_users = get_all_users();
$system_stats = get_system_statistics();

function get_system_statistics() {
    try {
        $db = new SQLite3(__DIR__ . '/spam.db');
        
        // 기본 통계
        $stats = [
            'total_users' => 0,
            'active_users_30d' => 0,
            'total_calls' => 0,
            'successful_calls' => 0,
            'total_patterns' => 0,
            'auto_patterns' => 0,
            'recent_activity' => []
        ];
        
        // 사용자 통계
        $userStats = $db->querySingle("
            SELECT COUNT(*) as total,
                   COUNT(CASE WHEN last_access > datetime('now', '-30 days') THEN 1 END) as active_30d
            FROM users
        ", true);
        
        if ($userStats) {
            $stats['total_users'] = $userStats['total'];
            $stats['active_users_30d'] = $userStats['active_30d'];
        }
        
        // 전화 통계
        $callStats = $db->querySingle("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful
            FROM unsubscribe_calls
        ", true);
        
        if ($callStats) {
            $stats['total_calls'] = $callStats['total'];
            $stats['successful_calls'] = $callStats['successful'];
        }
        
        // 패턴 통계
        $patternFile = __DIR__ . '/patterns.json';
        if (file_exists($patternFile)) {
            $patterns = json_decode(file_get_contents($patternFile), true);
            if ($patterns && isset($patterns['patterns'])) {
                $stats['total_patterns'] = count($patterns['patterns']) - 1; // default 제외
                $autoPatterns = 0;
                foreach ($patterns['patterns'] as $key => $pattern) {
                    if ($key !== 'default' && isset($pattern['auto_generated']) && $pattern['auto_generated']) {
                        $autoPatterns++;
                    }
                }
                $stats['auto_patterns'] = $autoPatterns;
            }
        }
        
        // 최근 활동 (스팸 내용 및 패턴 소스 포함)
        $recentActivity = $db->query("
            SELECT u.phone, uc.created_at, 
                   '080' || uc.phone080 as activity,
                   uc.phone080,
                   uc.identification,
                   uc.pattern_source,
                   CASE WHEN uc.status = 'completed' THEN 'success' ELSE 'failed' END as status,
                   (SELECT si.raw_text FROM sms_incoming si 
                    WHERE si.phone080 = uc.phone080 
                    ORDER BY si.received_at DESC LIMIT 1) as spam_content
            FROM unsubscribe_calls uc
            JOIN users u ON uc.user_id = u.id
            ORDER BY uc.created_at DESC 
            LIMIT 10
        ");
        
        while ($row = $recentActivity->fetchArray(SQLITE3_ASSOC)) {
            $stats['recent_activity'][] = $row;
        }
        
        $db->close();
        return $stats;
        
    } catch (Exception $e) {
        error_log("Failed to get system statistics: " . $e->getMessage());
        return [
            'total_users' => 0,
            'active_users_30d' => 0,
            'total_calls' => 0,
            'successful_calls' => 0,
            'total_patterns' => 0,
            'auto_patterns' => 0,
            'recent_activity' => []
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>시스템 관리 - 080 수신거부 자동화</title>
    <link rel="stylesheet" href="assets/modal.css?v=1">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 32px 48px;
            text-align: center;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .header p {
            color: #64748b;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .admin-nav {
            position: absolute;
            top: 24px;
            right: 32px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .admin-info {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .back-link:hover {
            background: rgba(102, 126, 234, 0.2);
            transform: translateY(-1px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 24px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 32px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 32px;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 32px;
        }

        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .users-table thead tr {
            background: #f8fafc;
            border-radius: 12px;
        }

        .users-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: none;
        }

        .users-table th:first-child {
            border-radius: 12px 0 0 12px;
        }

        .users-table th:last-child {
            border-radius: 0 12px 12px 0;
        }

        .users-table tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
        }

        .users-table tbody tr:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .users-table td {
            padding: 16px 20px;
            font-size: 0.9rem;
            border: none;
            vertical-align: middle;
        }

        .users-table td:first-child {
            border-radius: 12px 0 0 12px;
        }

        .users-table td:last-child {
            border-radius: 0 12px 12px 0;
        }

        .user-phone {
            font-weight: 600;
            color: #2d3748;
        }

        .user-stats {
            display: flex;
            gap: 16px;
            font-size: 0.8rem;
            color: #64748b;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-blocked {
            background: #fecaca;
            color: #dc2626;
        }

        .status-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn-success:hover {
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .activity-list {
            max-height: 450px;
            overflow-y: auto;
            padding-right: 8px;
        }

        .activity-list::-webkit-scrollbar {
            width: 6px;
        }

        .activity-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .activity-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .activity-item:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: #d1fae5;
            color: #065f46;
        }

        .activity-icon.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .activity-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .activity-phone {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .activity-action {
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-left: auto;
            padding-left: 16px;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            text-align: right;
            white-space: nowrap;
        }

        .activity-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .activity-status.success {
            background: #d1fae5;
            color: #065f46;
        }

        .activity-status.failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-activity {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
            background: #fafbfc;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
        }

        .empty-activity svg {
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .spam-preview {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            margin-left: 8px;
            padding: 2px 6px;
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: help;
            transition: all 0.2s ease;
        }

        .spam-preview:hover {
            background: rgba(245, 158, 11, 0.25);
            transform: scale(1.05);
        }

        .add-user-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 24px;
            width: 100%;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .add-user-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.4);
        }

        .add-user-form {
            display: none;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .add-user-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .add-user-form.show {
            display: block;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-header h3 {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-close {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: bold;
            margin-left: 16px;
        }

        .form-close:hover {
            background: #dc2626;
            transform: rotate(90deg) scale(1.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .role-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 2px solid #f1f5f9;
        }

        .btn-create {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-create:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-cancel {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }

        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .btn-edit:hover {
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        .edit-form {
            display: none;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 16px;
            margin-top: 8px;
        }

        .edit-form.show {
            display: block;
        }

        .edit-form .form-row {
            grid-template-columns: 1fr 1fr 1fr auto auto;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
                max-height: 0;
            }
            to {
                opacity: 1;
                transform: translateY(0);
                max-height: 500px;
            }
        }

        .admin-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 24px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .admin-nav {
                position: static;
                justify-content: center;
                margin-top: 16px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-body {
                padding: 20px;
            }
            
            .users-table {
                font-size: 0.8rem;
            }
            
            .user-stats {
                flex-direction: column;
                gap: 4px;
            }
        }

        /* Pattern Source Badge Styles */
        .pattern-source-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 6px;
        }

        .pattern-source-badge.community {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .pattern-source-badge.default {
            background: #fef7cd;
            color: #a16207;
            border: 1px solid #fed7aa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="admin-nav">
                <span class="admin-info">👑 <?php echo htmlspecialchars(current_user_phone()); ?> (관리자)</span>
                <a href="index.php" class="back-link">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    메인으로
                </a>
            </div>
            <h1>🛠️ 시스템 관리</h1>
            <p>전체 시스템 모니터링 및 사용자 관리</p>
        </div>

        <?php echo $message; ?>

        <!-- 시스템 통계 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_users']; ?></div>
                <div class="stat-label">총 사용자</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['active_users_30d']; ?></div>
                <div class="stat-label">활성 사용자 (30일)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_calls']; ?></div>
                <div class="stat-label">총 수신거부 전화</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['successful_calls']; ?></div>
                <div class="stat-label">성공한 전화</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['total_patterns']; ?></div>
                <div class="stat-label">등록된 패턴</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $system_stats['auto_patterns']; ?></div>
                <div class="stat-label">자동 생성 패턴</div>
            </div>
        </div>

        <!-- 사용자 관리 -->
        <div class="card">
            <div class="card-header">
                👥 사용자 관리
                <span style="font-size: 0.9rem; opacity: 0.9;"><?php echo count($all_users); ?>명 등록</span>
            </div>
            <div class="card-body">
                <!-- 사용자 추가 트리거 버튼 -->
                <button class="add-user-trigger" onclick="toggleAddUserForm()">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                    </svg>
                    새 사용자 추가
                </button>

                <!-- 사용자 추가 폼 (숨겨짐) -->
                <div id="addUserForm" class="add-user-form">
                    <div class="form-header">
                        <h3>
                            <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1z"/>
                            </svg>
                            새 사용자 추가
                        </h3>
                        <button type="button" class="form-close" onclick="toggleAddUserForm()">×</button>
                    </div>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_phone">전화번호 *</label>
                                <input type="tel" 
                                       id="new_phone" 
                                       name="new_phone" 
                                       placeholder="01012345678" 
                                       required 
                                       pattern="[0-9]{11}"
                                       maxlength="11">
                            </div>
                            <div class="form-group">
                                <label for="role">권한 수준</label>
                                <select id="role" name="role" class="role-select">
                                    <option value="user">일반 사용자</option>
                                    <option value="admin">관리자</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px; padding: 16px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; font-size: 0.85rem; color: #0369a1;">
                            <strong>💡 안내:</strong> 새 사용자는 자동으로 인증된 상태로 생성됩니다. 관리자 권한을 선택하면 시스템에서 자동으로 관리자 권한을 부여합니다.
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="toggleAddUserForm()">
                                취소
                            </button>
                            <button type="submit" class="btn-create">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                                </svg>
                                사용자 생성
                            </button>
                        </div>
                    </form>
                </div>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>사용자</th>
                            <th>통계</th>
                            <th>최근 접속</th>
                            <th>상태</th>
                            <th>액션</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): 
                            $stats = get_user_stats($user['phone']);
                            $isAdmin = in_array($user['phone'], get_admin_phones());
                            $isBlocked = isset($user['blocked']) && $user['blocked'];
                            $lastAccess = $user['last_access'] ?? null;
                            $isActive = $lastAccess && strtotime($lastAccess) > strtotime('-30 days');
                        ?>
                        <tr>
                            <td>
                                <div class="user-phone"><?php echo htmlspecialchars($user['phone']); ?></div>
                                <div style="font-size: 0.8rem; color: #9ca3af;">
                                    ID: <?php echo $user['id']; ?> | 
                                    가입: <?php echo date('Y-m-d', strtotime($user['created_at'] ?? 'now')); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($stats): ?>
                                <div class="user-stats">
                                    <span>📞 <?php echo $stats['total_calls']; ?>회</span>
                                    <span>✅ <?php echo $stats['successful_calls']; ?>회</span>
                                    <span>🧠 <?php echo $stats['patterns_created']; ?>개</span>
                                </div>
                                <?php else: ?>
                                <span style="color: #9ca3af; font-size: 0.8rem;">통계 없음</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($lastAccess): ?>
                                    <div style="font-size: 0.85rem; color: #4a5568;">
                                        <?php echo date('Y-m-d H:i', strtotime($lastAccess)); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #9ca3af;">
                                        (<?php echo time_ago($lastAccess); ?>)
                                    </div>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.8rem;">접속 기록 없음</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <span class="status-badge status-admin">관리자</span>
                                <?php elseif ($isBlocked): ?>
                                    <span class="status-badge status-blocked">차단됨</span>
                                <?php elseif ($isActive): ?>
                                    <span class="status-badge status-active">활성</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">비활성</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$isAdmin && $user['phone'] !== current_user_phone()): ?>
                                    <div class="btn-group">
                                        <button type="button" 
                                                class="btn btn-edit btn-sm" 
                                                onclick="toggleEditForm('<?php echo $user['id']; ?>')">
                                            ✏️ 편집
                                        </button>
                                        
                                        <?php if ($isBlocked): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="unblock_user">
                                                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return handleUnblockUser(event)">
                                                    🔓 해제
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="block_user">
                                                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return handleBlockUser(event)">
                                                    🚫 차단
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return handleDeleteUser(event)">
                                                🗑️ 삭제
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #9ca3af; font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <!-- 편집 폼 (토글되는 행) -->
                        <?php if (!$isAdmin && $user['phone'] !== current_user_phone()): ?>
                        <tr>
                            <td colspan="5">
                                <div id="edit-form-<?php echo $user['id']; ?>" class="edit-form">
                                    <h4 style="margin-bottom: 12px; color: #92400e;">
                                        ✏️ <?php echo htmlspecialchars($user['phone']); ?> 정보 수정
                                    </h4>
                                    <form method="post">
                                        <input type="hidden" name="action" value="update_user">
                                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="edit_phone_<?php echo $user['id']; ?>">새 전화번호</label>
                                                <input type="tel" 
                                                       id="edit_phone_<?php echo $user['id']; ?>" 
                                                       name="new_phone" 
                                                       value="<?php echo htmlspecialchars($user['phone']); ?>"
                                                       pattern="[0-9]{11}">
                                            </div>
                                            <div class="form-group">
                                                <label>인증 상태</label>
                                                <div class="checkbox-group">
                                                    <input type="hidden" name="verified" value="0">
                                                    <input type="checkbox" 
                                                           id="edit_verified_<?php echo $user['id']; ?>" 
                                                           name="verified" 
                                                           value="1" 
                                                           <?php echo $user['verified'] ? 'checked' : ''; ?>>
                                                    <label for="edit_verified_<?php echo $user['id']; ?>" style="margin: 0;">인증됨</label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>차단 상태</label>
                                                <div class="checkbox-group">
                                                    <input type="hidden" name="blocked" value="0">
                                                    <input type="checkbox" 
                                                           id="edit_blocked_<?php echo $user['id']; ?>" 
                                                           name="blocked" 
                                                           value="1" 
                                                           <?php echo $isBlocked ? 'checked' : ''; ?>>
                                                    <label for="edit_blocked_<?php echo $user['id']; ?>" style="margin: 0;">차단됨</label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    💾 저장
                                                </button>
                                            </div>
                                            <div class="form-group">
                                                <button type="button" 
                                                        class="btn btn-sm" 
                                                        style="background: #6b7280;" 
                                                        onclick="toggleEditForm('<?php echo $user['id']; ?>')">
                                                    ❌ 취소
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 최근 활동 -->
        <div class="card">
            <div class="card-header">
                📊 최근 활동
                <span style="font-size: 0.9rem; opacity: 0.9;">최근 10건</span>
            </div>
            <div class="card-body">
                <div class="activity-list">
                    <?php if (!empty($system_stats['recent_activity'])): ?>
                        <?php foreach ($system_stats['recent_activity'] as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['status']; ?>">
                                <?php if ($activity['status'] === 'success'): ?>
                                    ✅
                                <?php else: ?>
                                    ❌
                                <?php endif; ?>
                            </div>
                            
                            <div class="activity-info">
                                <div class="activity-phone">
                                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459l-4.682-4.682a1.75 1.75 0 0 1-.459-1.657l.548-2.19a.68.68 0 0 0-.122-.58z"/>
                                    </svg>
                                    <?php echo htmlspecialchars($activity['phone']); ?>
                                </div>
                                <div class="activity-action">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
                                    </svg>
                                    <?php echo htmlspecialchars($activity['activity']); ?> 수신거부 요청
                                    
                                    <?php if (!empty($activity['pattern_source']) && $activity['pattern_source'] === 'community'): ?>
                                    <span class="pattern-source-badge community">
                                        🌐 커뮤니티
                                    </span>
                                    <?php elseif (!empty($activity['pattern_source']) && $activity['pattern_source'] === 'default'): ?>
                                    <span class="pattern-source-badge default">
                                        ⚙️ 기본패턴
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($activity['spam_content'])): ?>
                                    <span class="spam-preview" title="<?php echo htmlspecialchars($activity['spam_content']); ?>">
                                        📄 스팸내용
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="activity-meta">
                                <div>
                                    <div class="activity-status <?php echo $activity['status']; ?>">
                                        <?php if ($activity['status'] === 'success'): ?>
                                            ✓ 성공
                                        <?php else: ?>
                                            ✗ 실패
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                        $time = strtotime($activity['created_at']);
                                        $now = time();
                                        $diff = $now - $time;
                                        
                                        if ($diff < 60) {
                                            echo '방금 전';
                                        } elseif ($diff < 3600) {
                                            echo floor($diff/60) . '분 전';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff/3600) . '시간 전';
                                        } else {
                                            echo date('m/d H:i', $time);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-activity">
                            <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.5 3a2.5 2.5 0 0 1 5 0v1h1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h1zM3 5v8h9V5z"/>
                                <path d="M4 5.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5M4 7.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5M4 9.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5"/>
                            </svg>
                            <div><strong>최근 활동이 없습니다</strong></div>
                            <div style="margin-top: 8px; font-size: 0.8rem;">수신거부 요청 활동이 여기에 표시됩니다</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/modal.js?v=1"></script>
    <script>
        function toggleAddUserForm() {
            const addForm = document.getElementById('addUserForm');
            const trigger = document.querySelector('.add-user-trigger');
            
            if (addForm.classList.contains('show')) {
                addForm.classList.remove('show');
                trigger.style.display = 'flex';
                // 폼 초기화
                addForm.querySelector('form').reset();
            } else {
                addForm.classList.add('show');
                trigger.style.display = 'none';
                // 첫 번째 입력 필드에 포커스
                setTimeout(() => {
                    addForm.querySelector('#new_phone').focus();
                }, 300);
            }
        }

        function toggleEditForm(userId) {
            const editForm = document.getElementById('edit-form-' + userId);
            if (editForm.classList.contains('show')) {
                editForm.classList.remove('show');
            } else {
                // 다른 모든 편집 폼 닫기
                document.querySelectorAll('.edit-form.show').forEach(form => {
                    form.classList.remove('show');
                });
                // 사용자 추가 폼도 닫기
                const addForm = document.getElementById('addUserForm');
                if (addForm.classList.contains('show')) {
                    toggleAddUserForm();
                }
                // 현재 편집 폼 열기
                editForm.classList.add('show');
            }
        }

        // 전화번호 입력 시 자동 포맷팅 (선택사항)
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function() {
                    // 숫자만 허용
                    this.value = this.value.replace(/[^0-9]/g, '');
                    // 11자리 제한
                    if (this.value.length > 11) {
                        this.value = this.value.slice(0, 11);
                    }
                });
            });
        });
        
        // 사용자 차단 해제 처리
        async function handleUnblockUser(event) {
            event.preventDefault();
            
            const confirmed = await modernConfirm({
                message: '이 사용자의 차단을 해제하시겠습니까?',
                title: '차단 해제 확인',
                confirmText: '해제',
                cancelText: '취소'
            });
            
            if (confirmed) {
                event.target.closest('form').submit();
            }
            
            return false;
        }
        
        // 사용자 차단 처리
        async function handleBlockUser(event) {
            event.preventDefault();
            
            const confirmed = await modernConfirm({
                message: '이 사용자를 차단하시겠습니까?',
                title: '사용자 차단',
                confirmText: '차단',
                cancelText: '취소',
                dangerConfirm: true
            });
            
            if (confirmed) {
                event.target.closest('form').submit();
            }
            
            return false;
        }
        
        // 사용자 삭제 처리
        async function handleDeleteUser(event) {
            event.preventDefault();
            
            const confirmed = await modernConfirmDelete({
                message: '⚠️ 이 사용자와 관련된 모든 데이터가 삭제됩니다.\n\n정말 삭제하시겠습니까?',
                title: '사용자 삭제',
                confirmText: '삭제',
                cancelText: '취소'
            });
            
            if (confirmed) {
                event.target.closest('form').submit();
            }
            
            return false;
        }
        
        // 자동 숨김 알림 처리
        document.addEventListener('DOMContentLoaded', function() {
            const autoHideAlerts = document.querySelectorAll('.alert.auto-hide');
            autoHideAlerts.forEach(alert => {
                // 3초 후 페이드아웃 시작
                setTimeout(() => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    
                    // 페이드아웃 완료 후 URL에서 파라미터 제거
                    setTimeout(() => {
                        // URL에서 파라미터 제거
                        const url = new URL(window.location);
                        url.searchParams.delete('created');
                        url.searchParams.delete('updated');
                        url.searchParams.delete('deleted');
                        url.searchParams.delete('blocked');
                        url.searchParams.delete('unblocked');
                        window.history.replaceState({}, document.title, url.pathname + url.search);
                        
                        // 알림 요소 제거
                        alert.remove();
                    }, 500);
                }, 3000);
            });
        });
    </script>
</body>
</html>

<?php
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return '방금';
    if ($time < 3600) return floor($time/60) . '분 전';
    if ($time < 86400) return floor($time/3600) . '시간 전';
    if ($time < 2592000) return floor($time/86400) . '일 전';
    if ($time < 31536000) return floor($time/2592000) . '개월 전';
    return floor($time/31536000) . '년 전';
}
?>