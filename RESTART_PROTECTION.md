# Asterisk 재시작 시 SMS 중복 처리 방지 시스템

## 문제점
Asterisk 재시작 시 SIM 카드에 저장된 오래된 SMS 메시지들이 자동으로 재처리되어 불필요한 수신거부 전화가 걸리는 문제가 발생했습니다.

## 해결책
다층 방어 시스템을 구축하여 재시작 시 안전성을 보장합니다.

### 1. 시작 관리자 (Startup Manager)
- **파일**: `startup_manager.php`
- **기능**: 재시작 감지, 안전 임계점 설정, 큐 정리
- **동작**: 재시작 시점을 기록하고 그 이전 메시지들을 안전하지 않은 것으로 분류

### 2. SMS 페처 개선 (Enhanced SMS Fetcher) 
- **파일**: `sms_fetcher.php` (수정됨)
- **기능**: SIM 메시지 타임스탬프 검증
- **동작**: 재시작 임계점 이전 메시지는 삭제만 하고 처리하지 않음

### 3. SMS 자동 처리기 강화 (Enhanced Auto Processor)
- **파일**: `sms_auto_processor.php` (수정됨)  
- **기능**: 재시작 후 보안 모드, 강화된 중복 방지
- **동작**: 재시작 후 10분간 1시간 이내 중복도 차단

### 4. 큐 러너 정리 기능 (Queue Runner Cleanup)
- **파일**: `call_queue_runner.php` (수정됨)
- **기능**: 시작 시 자동 정리, 재시작 마킹
- **동작**: `--startup` 플래그로 정리 모드 실행

## 사용법

### 수동 재시작 시 안전 절차
```bash
# 1. Asterisk 재시작 전 준비
php /var/www/html/spam/asterisk_startup_hook.php --clear-sim

# 2. Asterisk 재시작
sudo systemctl restart asterisk

# 3. 큐 러너 재시작 (자동 정리 포함)
sudo systemctl restart call_queue_runner

# 4. 상태 확인
php /var/www/html/spam/system_status.php
```

### 자동화된 정리 명령들
```bash
# 재시작 마킹 및 정리
php /var/www/html/spam/startup_manager.php mark asterisk_restart
php /var/www/html/spam/startup_manager.php cleanup

# SIM 메시지 강제 삭제
php /var/www/html/spam/clear_sim_messages.php --force --verbose

# 큐 러너 정리 모드
php /var/www/html/spam/call_queue_runner.php --startup

# 시스템 상태 확인
php /var/www/html/spam/system_status.php
```

### Systemd 서비스 개선 (권장)
현재 call_queue_runner 서비스에 시작 전 정리 단계 추가:

```ini
[Unit]
Description=080 Call Queue Runner (process queued .call files)
After=network.target asterisk.service

[Service]
Type=simple
WorkingDirectory=/var/www/html/spam
ExecStartPre=/usr/bin/php /var/www/html/spam/call_queue_runner.php --startup
ExecStart=/usr/bin/php /var/www/html/spam/call_queue_runner.php --loop
Restart=always
RestartSec=5
User=asterisk
```

## 보안 레벨

### 레벨 1: 타임스탬프 필터링
- SIM 메시지의 수신 시간 확인
- 재시작 전 메시지는 자동 삭제

### 레벨 2: 데이터베이스 중복 방지  
- 24시간 중복 방지 (일반)
- 1시간 중복 방지 (재시작 후 보안모드)

### 레벨 3: 락 파일 시스템
- 5분간 동일 조합 차단
- 재시작 시 자동 정리

### 레벨 4: 큐 정리
- 대기 중인 호출 파일 정리
- 임시 파일 및 상태 파일 정리

## 모니터링

### 로그 파일들
- `logs/startup.log` - 재시작 및 정리 기록
- `logs/sms_fetcher.log` - SMS 페처 동작 기록  
- `logs/sms_auto_processor.log` - 보안 모드 차단 기록
- `logs/queue_runner.log` - 큐 관리 기록

### 상태 확인 명령어
```bash
# 전체 시스템 상태
php system_status.php

# 시작 관리자 상태만
php startup_manager.php status

# 진단 정보
php startup_manager.php
```

## 트러블슈팅

### 문제: 재시작 후에도 오래된 메시지가 처리됨
```bash
# 해결: SIM 메시지 강제 정리
php clear_sim_messages.php --force

# 시작점 재설정
php startup_manager.php mark manual_fix
```

### 문제: 큐에 호출이 쌓여있음
```bash
# 해결: 큐 정리
php startup_manager.php cleanup

# 또는 큐 러너 재시작
sudo systemctl restart call_queue_runner
```

### 문제: 너무 많은 임시 파일
```bash
# 해결: 전체 정리
php startup_manager.php cleanup
rm -f /tmp/smslock_*
rm -f /tmp/call_queue_*
```

## 예방 권장사항

1. **정기적인 SIM 정리**: 매일 또는 매주 SIM 메시지 정리
2. **로그 모니터링**: startup.log와 보안 차단 로그 확인  
3. **서비스 재시작 시**: 항상 정리 명령 먼저 실행
4. **시스템 상태 확인**: 정기적으로 system_status.php 실행

이 시스템으로 Asterisk 재시작 시 SMS 중복 처리 문제가 완전히 해결됩니다.