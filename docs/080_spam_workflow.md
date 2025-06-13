# 080 Spam-Blocker Workflow Guide

> **목표** : 080 광고 문자(SMS)를 수신하면 자동으로 음성 수신거부 전화를 걸어 처리하고, 실패 시 패턴을 학습·재시도하는 전(全) 과정을 자동화한다.

---

## 0. 기본 컨셉

1. **SMS 수신** : USB LTE 모뎀이 광고 문자를 수신하면 Asterisk `incoming-mobile,sms` exten이 트리거된다.
2. **080 번호/식별번호 추출 & 패턴 조회** : `process_v2.php`가 문자 내용을 분석해 080 번호·식별번호(ID)를 추출하고, `patterns.json`에서 자동 DTMF 패턴을 찾는다.
3. **Call-file 생성 & 발신** : 패턴이 있으면 Call-file을 만들어 Asterisk에 스풀 → 실제 전화 걸림.
4. **통화 진행 & 녹음** : Dial-plan `[callfile-handler]` 컨텍스트가 DTMF를 전송하고, 통화 전 과정을 녹음하며 `call_progress/*.log`에 상태를 남긴다.
5. **즉시 STT 판정** : 통화 종료 직후 `success_checker.php`가 Whisper(base)로 녹음을 STT하여 "성공/실패" 로그(`UNSUB_*`)를 남긴다.
6. **모니터링 & 재시도/디스커버리** : `monitor_call_outcomes.php`(cron) 가 2 분 간격으로 로그를 읽어 실패 → 패턴 없으면 디스커버리 통화(45 초) → 새 패턴 학습.
7. **상세 분석 & UI 표시** : 사용자가 "분석" 버튼을 누르거나 자동 조건이 맞으면 `simple_analyzer_runner.py`가 Whisper(small)로 정밀 분석하여 UI에 JSON 결과/진행률을 제공한다.

---

## 1단계 : SMS 수신 확인

> **목적** : 모뎀·dial-plan이 정상적으로 문자 이벤트를 서버에 전달하는지 검증한다.

### 1-1. 실시간 로그 모니터링

```bash
# Asterisk CLI에서
asterisk -rvv
> core set verbose 3
> dialplan show incoming-mobile@extensions_custom.conf
> # 문자 수신 시 아래 exten이 호출되는지 확인
```

### 1-2. 텍스트 로그 확인

* `/var/log/asterisk/sms.txt` 파일의 마지막 줄에 수신 SMS 내용이 기록돼야 함.
* 예시 :

```text
SMS From 01012345678 on 2025-06-13 14:25:55
080-1234-5678 수신거부는 본문 식별번호123456 입력
```

### 1-3. 문제 해결 체크리스트

- [ ] Quectel 모뎀이 `/dev/ttyUSB*` 로 인식되고 Asterisk `chan_quectel` 드라이버가 로드됨
- [ ] `incoming-mobile,sms,1` dial-plan이 `extensions_custom.conf` 에 존재
- [ ] SELinux/AppArmor, 퍼미션, USB 전원관리 문제 없음

---

## 2단계 : PHP 엔드포인트 연결 확인

**파일** : `process_v2.php`

1. 문자 본문을 파싱하여 080 번호·식별번호 추출 성공 여부를 `logs/process_v2_debug.log` 로 확인.
2. 오류 시 : 정규식 매칭 패턴 / 문자 인코딩(EUC-KR vs UTF-8) / 특수문자(whitespace) 확인.

---

## 3단계 : 콜 발신 → 통화 로그 확인

1. `/var/spool/asterisk/outgoing/` 에 Call-file 생성 → `callfile-handler` 실행.
2. `/var/log/asterisk/call_progress/<CALLFILE_ID>.log` 로 실시간 상태 모니터.
3. 녹음 파일은 `/var/spool/asterisk/monitor/` 에 저장.

---

## 4단계 : 빠른 Whisper 판정(success_checker)

| 항목 | 값 |
|------|-----|
| 스크립트 | `success_checker.php` |
| Whisper 모델 | base |
| 키워드 | 성공 : '수신거부', '완료' / 실패 : '다시 시도', '번호를 확인' |

결과 라인은 `UNSUB_success` 또는 `UNSUB_failed` 로 `call_progress` 로그에 남는다.

---

## 5단계 : 자동 재시도 & 패턴 디스커버리

* **모니터링 스크립트** : `monitor_call_outcomes.php` (cron)
* 실패 + 특정 조건(전용 패턴 없음, auto_supported != false) →
  1. `PatternDiscovery::startDiscovery()` 호출
  2. 90 초 후 `process_v2.php --auto` 로 재발신 스케줄

---

## 6단계 : 정밀 분석(simple_analyzer)

* 사용자가 수동으로 "분석" 클릭하거나, `get_recordings.php` 가 `*.done` 파일 없음→자동 분석.
* Whisper small 모델 사용, 진행률 JSON(`progress/*.json`) 생성.
* 분석 결과 JSON(`analysis_results/*.json`)에서 성공/실패·confidence·pattern_hint 등을 UI에 표시.

---

## 7단계 : 패턴 매니저 & UI 피드백

* `PatternManager.php` : `patterns.json` CRUD, `auto_supported`·`pattern_type` 뱃지.
* index.php : 진행률 바, 배지, 자동 분석 트리거, 실패 시 재시도 버튼.

---

## 부록 A : 주요 로그 & 디렉터리

| 경로 | 설명 |
|------|------|
| `/var/log/asterisk/sms.txt` | 수신 SMS 원본 로그 |
| `/var/log/asterisk/call_progress/` | 통화-별 단계 로그 + STT 판정 |
| `logs/process_v2_debug.log` | Call-file 생성기 디버그 |
| `analysis_results/` | 정밀 STT/분석 결과 JSON |
| `analysis_logs/` | Python analyzer stdout/stderr |
| `pattern_discovery/` | 디스커버리 결과 JSON |

---

## 앞으로의 진행 계획 Checklist

- [ ] **1단계** SMS 수신 확인 완료 (모뎀/Dial-plan 로깅)
- [ ] **2단계** `process_v2.php` 정상 파싱·Call-file 생성
- [ ] **3단계** Dial-plan 통화 흐름·DTMF 전송·녹음 확인
- [ ] **4단계** `success_checker.php` 빠른 판정 → 로그 확인
- [ ] **5단계** `monitor_call_outcomes.php` 재시도·디스커버리 동작
- [ ] **6단계** UI에서 정밀 분석 진행률·결과 확인
- [ ] **7단계** 패턴 매니저로 신규 패턴 저장 & `auto_supported` 검증

진행 중 막히는 단계가 있으면 체크리스트를 기준으로 원인(권한, 경로, 네트워크, 모델 다운로드) 을 좁혀 나가면 됩니다. 