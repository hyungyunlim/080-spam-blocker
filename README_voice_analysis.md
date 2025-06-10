# 080 수신거부 음성 분석 시스템

## 🎤 개요
녹음된 080 수신거부 통화를 자동으로 분석하여 수신거부 처리 성공 여부를 판단하는 시스템입니다.

## ✨ 주요 기능

### 1. 음성 텍스트 변환 (STT)
- **OpenAI Whisper** 사용으로 높은 정확도의 한국어 음성 인식
- 여러 모델 크기 지원 (tiny, base, small, medium, large)
- 기본값: base 모델 (정확도와 속도 균형)

### 2. 자동 판단 시스템
#### ✅ SUCCESS (성공)
- "수신거부", "처리되었습니다", "완료되었습니다", "삭제되었습니다"
- "차단되었습니다", "해지되었습니다", "확인되었습니다"
- "정상처리", "처리완료", "등록완료" 등

#### ❌ FAILURE (실패) 
- "오류", "실패", "잘못된", "확인할 수 없습니다"
- "올바르지 않습니다", "유효하지 않습니다", "처리할 수 없습니다"
- "번호가 맞지", "정보가 일치하지" 등

#### ⚠️ UNCERTAIN (불확실)
- "안내", "메뉴", "번호를 입력", "확인해주세요"
- "잠시만", "처리중", "연결중" 등

#### ❓ UNKNOWN (알 수 없음)
- 명확한 키워드를 찾을 수 없는 경우

### 3. 신뢰도 점수
- 감지된 키워드 수와 패턴 매칭 결과로 0-90% 신뢰도 제공
- 높은 신뢰도일수록 판단 결과가 정확

## 🖥️ 웹 인터페이스 사용법

### 1. 웹 페이지 접속
```
http://[서버주소]/spam/
```

### 2. 녹음 파일 목록에서 🎤 음성분석 버튼 클릭
- 각 녹음 파일 옆에 "🎤 음성분석" 버튼 있음
- 클릭하면 1-2분 소요되며 분석 진행
- 결과가 색상별로 표시됨:
  - 🟢 초록색: 수신거부 성공
  - 🔴 빨간색: 수신거부 실패
  - 🟡 노란색: 불확실한 결과
  - 🔵 파란색: 알 수 없음

### 3. 분석 결과 확인
- 인식된 텍스트 전체 내용
- 판단 결과 (SUCCESS/FAILURE/UNCERTAIN/UNKNOWN)
- 신뢰도 퍼센트
- 판단 근거

## 🔧 명령줄 사용법

### 직접 Python 스크립트 실행
```bash
# 가상환경 활성화
source /home/linux/080-spam-blocker/venv/bin/activate

# 음성 파일 분석
python /home/linux/080-spam-blocker/voice_analyzer.py [음성파일경로]

# 모델 크기 지정 (기본값: base)
python voice_analyzer.py [음성파일경로] -m small

# 결과 파일 지정
python voice_analyzer.py [음성파일경로] -o result.json
```

### API 직접 호출
```bash
# POST 요청으로 분석 시작
curl -X POST -d "filename=녹음파일명.wav" http://localhost/spam/analyze_recording.php

# 분석 결과 조회
curl "http://localhost/spam/analyze_recording.php?filename=녹음파일명.wav"

# 전체 분석 결과 목록
curl "http://localhost/spam/analyze_recording.php?list=true"
```

## 📂 파일 구조

```
/home/linux/080-spam-blocker/
├── voice_analyzer.py          # 메인 음성 분석 스크립트
├── venv/                      # Python 가상환경
└── analyze_recording.php      # 웹 API 인터페이스

/var/www/html/spam/
├── index.php                  # 메인 웹 인터페이스 (음성분석 기능 추가)
├── analyze_recording.php      # 음성 분석 API
└── analysis_results/          # 분석 결과 JSON 파일 저장

/var/spool/asterisk/monitor/   # Asterisk 녹음 파일 위치
```

## 🎯 분석 결과 예시

### 성공 사례
```json
{
  "file_path": "/var/spool/asterisk/monitor/20250609-235131-FROM_SYSTEM-TO_0800121900.wav",
  "timestamp": "2025-06-10T00:08:22.663636",
  "transcription": "수신거부가 정상적으로 처리되었습니다. 감사합니다.",
  "analysis": {
    "status": "success",
    "confidence": 70,
    "reason": "성공 키워드 1개 감지"
  },
  "file_size": 582444
}
```

## ⚙️ 설정 및 최적화

### 모델 크기별 특성
- **tiny**: 가장 빠름, 낮은 정확도 (~39MB)
- **base**: 권장 설정, 균형잡힌 성능 (~139MB) 
- **small**: 더 높은 정확도 (~244MB)
- **medium**: 매우 높은 정확도 (~769MB)
- **large**: 최고 정확도, 느림 (~1550MB)

### 성능 최적화
- ARM64 환경에서 base 모델 권장
- 첫 실행 시 모델 다운로드로 시간 소요
- 이후 실행은 로컬 캐시 사용으로 빠름

## 🔍 트러블슈팅

### 1. ffmpeg 오류
```bash
sudo apt install ffmpeg -y
```

### 2. Python 모듈 오류
```bash
source /home/linux/080-spam-blocker/venv/bin/activate
pip install openai-whisper
```

### 3. 권한 오류
```bash
sudo chown www-data:asterisk /var/www/html/spam/analysis_results/
sudo chmod 755 /var/www/html/spam/analysis_results/
```

### 4. 메모리 부족
- tiny 또는 base 모델 사용
- 시스템 리소스 확인

## 📊 활용 방안

1. **통화 품질 모니터링**: 수신거부 처리 성공률 추적
2. **패턴 개선**: 실패 사례 분석으로 DTMF 패턴 최적화  
3. **자동화 확장**: 성공/실패에 따른 후속 처리 자동화
4. **보고서 생성**: 일일/주간 수신거부 처리 현황 리포트

## 🎉 완성된 시스템

이제 080 수신거부 시스템이 다음과 같이 완전히 구축되었습니다:

1. ✅ **자동 통화 시스템** - 패턴 기반 DTMF 전송
2. ✅ **웹 관리 인터페이스** - 패턴 추가/수정/삭제
3. ✅ **음성 분석 시스템** - STT + 자동 성공/실패 판단
4. ✅ **통합 모니터링** - 녹음 재생 + 분석 결과 확인

한국어 080 수신거부 완전 자동화 솔루션이 완성되었습니다! 🚀 