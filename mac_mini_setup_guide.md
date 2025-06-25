# M1 맥미니 STT 분석 서버 설정 가이드

## 1. 필요한 소프트웨어 설치

맥미니에서 터미널을 열고 다음 명령을 실행하세요:

```bash
# Homebrew 설치 (없는 경우)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Python 및 필요 패키지 설치
brew install python3
pip3 install flask whisper torch
```

## 2. 분석 서버 실행

1. 서버 파일을 맥미니에 생성:

```bash
# 홈 디렉토리에 stt_server 디렉토리 생성
mkdir ~/stt_server
cd ~/stt_server

# 서버 파일 생성 (mac_analyzer_server.py 내용 복사)
nano mac_analyzer_server.py
```

2. 서버 실행:

```bash
python3 mac_analyzer_server.py
```

## 3. 자동 시작 설정 (선택사항)

macOS에서 부팅 시 자동 시작하려면 LaunchAgent 설정:

```bash
# Launch Agent 파일 생성
mkdir -p ~/Library/LaunchAgents
cat > ~/Library/LaunchAgents/com.stt.analyzer.plist << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.stt.analyzer</string>
    <key>ProgramArguments</key>
    <array>
        <string>/usr/local/bin/python3</string>
        <string>/Users/hyungyunlim/stt_server/mac_analyzer_server.py</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>/Users/hyungyunlim/stt_server/server.log</string>
    <key>StandardErrorPath</key>
    <string>/Users/hyungyunlim/stt_server/error.log</string>
</dict>
</plist>
EOF

# Launch Agent 로드
launchctl load ~/Library/LaunchAgents/com.stt.analyzer.plist
```

## 4. 방화벽 설정

시스템 환경설정 > 보안 및 개인정보 보호 > 방화벽에서:
- Python을 허용하도록 설정
- 또는 포트 8080을 허용

## 5. 테스트

브라우저에서 `http://192.168.1.92:8080/health` 접속하여 서버 상태 확인

## 서버 실행 확인

서버가 정상 실행되면 다음과 같은 메시지가 표시됩니다:

```
🚀 M1 STT Analysis Server starting...
📱 Optimized for Apple Silicon Neural Engine  
🎯 Listening on 0.0.0.0:8080
Loading Whisper model on M1 Mac...
Whisper model loaded successfully!
```

## 성능 최적화

M1 맥미니에서 최적의 성능을 위해:
- Whisper 모델: base 또는 small 사용 (라즈베리파이보다 10-20배 빠름)
- 메모리: 8GB 이상 권장
- 동시 처리: 2-3개 요청까지 가능