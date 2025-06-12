# 080 스팸 차단 자동화 시스템

080번 스팸 전화에 대한 수신거부 자동화 및 SMS 알림 시스템입니다.

## 주요 기능

### 🤖 자동 수신거부
- 스팸 문자 내용을 분석하여 080번호와 식별번호 자동 추출
- Asterisk를 통한 자동 전화 걸기 및 DTMF 전송
- 다양한 080 서비스별 패턴 학습 및 적용

### 📱 SMS 알림 시스템
- 수신거부 완료 후 SMS 알림 전송
- 음성 분석 결과에 따른 성공/실패 판단
- 녹음 파일 URL 포함한 상세 알림

### 🎙️ 음성 분석
- STT(Speech-to-Text)를 통한 통화 내용 분석
- AI 기반 수신거부 성공/실패 판단
- 신뢰도 점수 제공

### 📊 관리 인터페이스
- 웹 기반 관리 페이지
- 녹음 파일 재생 및 분석 결과 확인
- SMS 전송 테스트 기능
- 실시간 진행 상황 모니터링

## 시스템 구성

### 핵심 파일들
- `index.php` - 메인 웹 인터페이스
- `process_v2.php` - 수신거부 자동화 처리
- `sms_sender.php` - SMS 전송 클래스
- `analyze_recording.php` - 음성 분석 API
- `pattern_manager.php` - 패턴 관리
- `patterns.json` - 080 서비스별 패턴 정의

### 지원 파일들
- `get_recordings.php` - 녹음 파일 목록 API
- `player.php` - 녹음 파일 재생
- `test_sms.php` - SMS 전송 테스트
- `recording_info_extractor.php` - 녹음 파일 정보 추출
- `sms_config.php` - SMS 설정

## 기술 스택

- **Backend**: PHP, Asterisk/FreePBX
- **Frontend**: HTML, CSS, JavaScript
- **Hardware**: Quectel EG25 모뎀
- **AI**: Python STT + 패턴 분석
- **Database**: Asterisk AstDB

## 설치 및 설정

1. **시스템 요구사항**
   - Debian/Ubuntu Linux
   - Asterisk/FreePBX
   - PHP 7.4+
   - Python 3.8+
   - Quectel 모뎀

2. **권한 설정**
   ```bash
   sudo chmod 755 /var/spool/asterisk/monitor/
   sudo chmod 666 /var/spool/asterisk/monitor/*.wav
   sudo chown -R www-data:www-data logs/
   ```

3. **Asterisk 설정**
   - Call File 처리용 컨텍스트 설정
   - DTMF 패턴 및 타이밍 조정
   - 녹음 기능 활성화

## 사용법

1. **스팸 문자 분석**: 메인 페이지에서 스팸 문자 내용 입력
2. **자동 처리**: 080번호와 식별번호 자동 인식 후 전화 걸기
3. **결과 확인**: 녹음 파일 분석 결과 및 SMS 알림 확인
4. **패턴 학습**: 새로운 080 서비스 패턴 추가 및 학습

## 최근 업데이트

### 2025-06-12 (restore-fixes 브랜치)

* UI 성능 향상: 녹음 목록 5초 주기 갱신 시 DOM 전체 재생성을 방지해 깜빡임 제거 · `lastRecordingsUpdate` 체크 추가.
* 자동 분석 & 진행 상태
  * 녹음이 `ready_for_analysis` 상태가 되면 버튼 클릭 없이도 자동으로 분석 트리거.
  * 진행 중인 호출에 대한 실시간 프로그레스바 표시, 새로고침 후에도 복원(`localStorage` 사용).
* 패턴 분석 고도화: 결과 `.done` 으로 마킹해 중복 분석 루프 방지.
* API 개선: `get_recordings.php` / `get_call_progress.php` 등에서 예외 처리 강화, 빈 요청 시 500 오류 해결.
* **PatternManager 래퍼**: `pattern_manager.php` → HTML을 제거한 얇은 래퍼로 변경하여 API들이 클래스를 로드할 때 JSON 응답에 HTML이 섞이지 않도록 하고, 브라우저로 직접 접근 시 UI 페이지를 그대로 제공.
* 파일명 대소문자 통일(`PatternManager.php`) 및 include 경로 수정.

### 2025-06-10

* SMS 로그 저장 기능 개선
* 권한 문제 해결
* JavaScript 에러 수정
* 녹음 파일 자동 정리 (최신 3개 유지)
* 실시간 진행 상황 모니터링
* 메시지 길이 최적화 (300바이트 제한)

## 라이센스

이 프로젝트는 개인용으로 제작되었습니다. 