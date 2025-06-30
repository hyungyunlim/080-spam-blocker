# 080 스팸 자동수신거부 시스템 – 로직 개요

> 2025-06-14 리팩터링(Whisper 통합 · SMS 알림 개편) 기준.

## 1. 전체 흐름
```
SMS → Asterisk Dial-plan(incoming-mobile/sms) → sms_auto_processor.php
    → process_v2.php  → *.call 파일 작성 → (즉시 | 대기) → Asterisk 통화
                                            ↘ call_queue_runner.php (daemon)
                                            ↘ callfile-handler (Dial-plan)
                                                     ↘ success_checker.php / Voice-analysis 등
```

1. **SMS 수신**: Quectel EC25 모뎀 → `chan_quectel` → Asterisk.
2. **Dial-plan** (`extensions_custom.conf`)
   - 로그 작성 & `sms_auto_processor.php` 실행.
   - `AT+CMGF=1` + `AT+QMGDA="DEL READ"` 로 즉시 **읽음 문자** 삭제.
3. **sms_auto_processor.php**
   - BASE64 본문 디코딩, **080 번호 + 식별번호(ID)** 추출.
   - **중복 방지**
     1) `notifications.db` 최근 24 h 내 동일 080+ID 존재 시 즉시 skip.
     2) 5 분 락파일 `/tmp/smslock_*`(폭주 보호) 추가 검사.
   - `process_v2.php --auto` CLI 호출.
4. **process_v2.php**
   - 패턴(`patterns.json`) 로드, 토큰 치환 `{ID}, {Notify}, {Phone}`.
   - Call-file 생성 → AstDB 변수 저장 후
     - 모뎀 Busy : `call_queue/` 보관.
     - Idle      : `/var/spool/asterisk/outgoing/` 즉시 발신.
5. **call_queue_runner.php** (systemd 서비스)
   - 5 s 간격으로 Idle 확인 후 가장 오래된 `.call` 이동.
   - **TTL 정책**
     • `call_queue/` 파일 > 1 h  → 폐기
     • `outgoing/` 파일 > 30 min → 폐기(재부팅·실패 잔여 방지)
   - 통화 간 45 s 간격 보장.
6. **callfile-handler** Dial-plan
   - 통화 연결 시 DTMF 전송 / 녹음 / 로그.
7. **성공 여부 판단 & 알림**
   - `success_checker.php`가 h-extension에서 실행 → Whisper 분석.
   - 분석 결과에 따라 **결과 SMS**(✅/❌/⚠️) 발송.
   - `sms_sender.php` : 300 byte 단문 한도, 초과 시 자동 multipart `(1/2)` 분할.
   - 진행 단계에서 process_v2 가 “[시도] … 진행중” 1차 알림 전송.
8. **패턴 관리 & 디스커버리**
   - `PatternManager.php`(UI), `pattern_discovery.php` + Python Analyzer.

## 2. 식별번호(ID) 결정 우선순위 (자동 SMS 경로)
| 순서 | 출처 | 설명 |
|------|------|------|
|①| SMS 본문 `식별번호` 키워드 | 정규식 `/식별번호\s*:?\s*([0-9]{4,8})/u` |
|②| 수동 입력 (웹 UI)          | `phone_number` POST 값 |
|③| **발신자 번호** (CALLERID) | Dial-plan → `--caller=` → `notification_phone` |

※ 문자 본문에 포함된 010/080 번호는 더 이상 사용하지 않습니다.

## 3. SMS 삭제 로직
1. **즉시 삭제** (Dial-plan)
   ```asterisk
   exten => sms,n,NoOp(-- SMS cleanup begin --)
   exten => sms,n,System(echo -ne "AT+CMGF=1\r" > /dev/ttyUSB2)
   exten => sms,n,System(sleep 0.2)
   exten => sms,n,System(echo -ne "AT+CPMS=\"SM\",\"SM\",\"SM\"\r" > /dev/ttyUSB2)
   exten => sms,n,System(sleep 0.2)
   exten => sms,n,System(echo -ne "AT+QMGDA=\"DEL READ\"\r" > /dev/ttyUSB2)
   exten => sms,n,NoOp(-- SMS cleanup end --)
   ```
2. **보조 정리**: `sms_fetcher.php` (loop / cron)
   - Asterisk가 점유하지 않는 `/dev/ttyUSB3` 우선.
   - `AT+CMGL="ALL"` 목록 → `AT+CMGD=<idx>,0` 개별 삭제.

## 4. 중복 방지 & 재시도 (업데이트)
| 메커니즘 | 윈도우 |
|-----------|---------|
| SQLite `notifications.db` (phone080+ID) | 24 시간 |
| 락파일 `/tmp/smslock_*` | 5 분 |
| 최근 `call_progress` 로그 검색 | 5 분 |

재시도(모뎀 Busy 등) 큐는 TTL 정책(위 5번)으로 자동 정리.

## 5. Call-file & 통화 제어
- 파일명: `call_<uniq>.call` (temp → outgoing/ 또는 call_queue/)
- Dial-plan 변수
  | 변수 | 용도 |
  |------|------|
  | `DTMF_TO_SEND` | 1-차 입력(예 `1234#`) |
  | `CONFIRM_DTMF` | 2-차 확인(예 `1`) |
  | `CONFIRM_REPEAT`, `CONFIRM_DELAY` | 반복/지연 |
- 녹음: `/var/spool/asterisk/monitor/…` (30 s) → STT 등 후처리.

## 6. 패턴 관리
- `patterns.json` 구조
  ```json
  {
    "patterns": {
       "0801234567": {
         "dtmf_pattern": "{ID}#",
         "initial_wait": 4,
         …
       },
       "default": { … }
    }
  }
  ```
- UI `PatternManager.php` 로 CRUD.
- 신규 번호는 기본 패턴으로 1회 시도 후 실패 시 디스커버리.

## 7. 서비스/스크립트 목록
| 항목 | 설명 |
|------|------|
| `sms_fetcher.service` | SIM SMS polling & 삭제 |
| `call_queue_runner.service` | 발신 대기열 관리 |
| Asterisk Dial-plan `incoming-mobile`, `callfile-handler` | 수신·발신 로직 |
| `cleanup_backups.sh` | 백업·로그 순환 |

## 8. 로그 & 데이터 경로
| 경로 | 내용 |
|------|------|
| `/var/log/asterisk/sms.txt` | 원문 SMS 모니터링 |
| `/var/log/asterisk/sms_auto.log` | sms_auto_processor 로그 |
| `/var/log/asterisk/call_progress/` | 콜 진행 상태별 로그 |
| `logs/process_v2_debug.log` | process_v2 상세 디버그 |
| `notifications.db` | SMS 수신 내역(SQLite) |

---
문서 최종 수정: 2025-06-14 