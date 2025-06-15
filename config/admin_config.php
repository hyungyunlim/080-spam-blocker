<?php
/**
 * 관리자 설정 파일
 * 보안을 위해 관리자 전화번호를 별도 파일로 분리
 */

// 환경변수에서 관리자 번호 읽기, 없으면 기본값 사용
function get_admin_phones_config(): array {
    // 환경변수에서 읽기 (콤마로 구분된 번호들)
    $admin_phones_env = getenv('ADMIN_PHONES');
    if ($admin_phones_env) {
        return array_map('trim', explode(',', $admin_phones_env));
    }
    
    // 환경변수가 없을 경우 기본 관리자 번호 (개발용)
    // 프로덕션에서는 반드시 환경변수 설정 필요
    return [
        '01012345678',  // 기본 관리자 번호 (변경 필요)
        '01021918573'   // 추가 관리자 번호 (변경 필요)
    ];
}
?>