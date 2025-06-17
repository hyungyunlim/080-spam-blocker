<?php
/**
 * SMS 시스템 설정
 */

return [
    // SMS 메시지 최대 길이 (바이트) - Quectel 모뎀 안정성을 위해 300바이트로 제한
    'max_message_length' => 300,
    
    // 경고 표시 임계값 (바이트)
    'warning_threshold' => 250,
    
    
    // SMS 메시지 모드 ('short' = 간결, 'detailed' = 상세)
    'message_mode' => 'short',
    
    // 단일 SMS 최대 길이 (분할 방지)
    'single_sms_max_length' => 300,
    
    // Quectel 명령어 설정
    'quectel_command' => 'quectel sms quectel0',
    
    // 로그 파일 경로
    'log_file' => '/var/log/asterisk/sms_notifications.log',
    'error_log_file' => '/var/log/asterisk/sms_error_log.txt',
    'completion_log_file' => '/var/log/asterisk/sms_completion_log.json',
    
    // 재시도 설정
    'max_retries' => 3,
    'retry_delay' => 5, // 초
    
    // 메시지 타입별 설정
    'message_types' => [
        'sms' => [
            'max_length' => 160,
            'encoding' => 'gsm7'
        ],
        'lms' => [
            'max_length' => 300,
            'encoding' => 'ucs2'
        ]
    ],
    
    // 서버 설정
    'server_url' => 'https://spam.juns.mywire.org',  // 외부 도메인으로 설정
    
    // 알림 메시지 템플릿
    'notification_templates' => [
        'success' => '✅ 성공',
        'completed' => '✅ 완료',
        'failed' => '❌ 실패',
        'error' => '⚠️ 오류'
    ]
];
?> 