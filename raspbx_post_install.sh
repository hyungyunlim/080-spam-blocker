#!/bin/bash

# RaspPBX Post-Installation Script for 080 SMS System
# RaspPBX 설치 후 chan_quectel 및 추가 설정을 위한 스크립트

set -e

echo "=== RaspPBX Post-Installation Setup ==="
echo "080 SMS System을 위한 추가 구성요소 설치"

# 1. 시스템 업데이트
echo "📦 1단계: 시스템 업데이트..."
apt update && apt upgrade -y

# 2. 필요한 패키지 설치
echo "📦 2단계: 필요한 패키지 설치..."
apt install -y \
    build-essential \
    git \
    libssl-dev \
    libncurses5-dev \
    libnewt-dev \
    libxml2-dev \
    libsqlite3-dev \
    uuid-dev \
    libjansson-dev \
    libspeex-dev \
    libspeexdsp-dev \
    sqlite3 \
    php-sqlite3 \
    usb-modeswitch \
    usb-modeswitch-data \
    python3 \
    python3-pip \
    python3-venv \
    ffmpeg

# 3. chan_quectel 설치
echo "📡 3단계: chan_quectel 설치..."
cd /tmp
git clone https://github.com/ca4ti/asterisk-chan-quectel.git
cd asterisk-chan-quectel

# Asterisk 버전 확인 및 컴파일
ASTERISK_VERSION=$(asterisk -V | grep -oP '\d+\.\d+')
echo "  → Asterisk 버전: $ASTERISK_VERSION"

./bootstrap
./configure --with-astversion=$ASTERISK_VERSION
make
make install

echo "  ✅ chan_quectel 설치 완료"

# 3.5. Python 가상환경 및 STT 시스템 설치
echo "🎤 3.5단계: STT 분석 시스템 설치..."
cd /var/www/html

# Python 가상환경 생성
python3 -m venv stt_env
source stt_env/bin/activate

# Whisper 및 필요한 패키지 설치
pip install --upgrade pip
pip install openai-whisper==20240930
pip install torch==2.7.1 --index-url https://download.pytorch.org/whl/cpu
pip install psutil==5.9.4

# 가상환경 활성화 스크립트 생성
cat > /var/www/html/activate_stt.sh << 'EOF'
#!/bin/bash
source /var/www/html/stt_env/bin/activate
exec "$@"
EOF
chmod +x /var/www/html/activate_stt.sh

# success_checker.php에서 Python 경로 수정
if [ -f /var/www/html/success_checker.php ]; then
    sed -i 's|python3 |/var/www/html/activate_stt.sh python3 |g' /var/www/html/success_checker.php
fi

echo "  ✅ STT 시스템 설치 완료"

# 4. Quectel 모뎀 설정 파일 생성
echo "📱 4단계: Quectel 설정 파일 생성..."
cat > /etc/asterisk/quectel.conf << 'EOF'
;
; Quectel channel configuration for 080 SMS System
;

[general]
interval=15
smsdb=/var/lib/asterisk/smsdb
csmsttl=600

; EC25 모뎀 설정 (실제 연결 후 수정 필요)
[quectel0]
audio=/dev/ttyUSB1
data=/dev/ttyUSB0
imei=auto
imsi=auto
disable=no
resetdongle=yes
u2diag=-1
usecallingpres=yes
autodeletesms=yes
resetdongle=yes
disablesms=no
smsaspdu=yes

; Context 설정 (dialplan에서 사용)
context=sms-incoming
group=1
rxgain=0
txgain=0
EOF

echo "  ✅ /etc/asterisk/quectel.conf 생성 완료"

# 5. Asterisk dialplan에 SMS 처리 추가
echo "📞 5단계: Asterisk dialplan 설정..."
cat >> /etc/asterisk/extensions_custom.conf << 'EOF'

; 080 SMS System - Incoming SMS Handler
[sms-incoming]
exten => sms,1,NoOp(Incoming SMS from ${CALLERID(num)} via ${QUECTEL_DEV})
exten => sms,n,Set(SMS_BODY=${BASE64_DECODE(${SMS_BASE64})})
exten => sms,n,System(/usr/bin/php /var/www/html/sms_auto_processor.php --caller="${CALLERID(num)}" --msg_base64="${SMS_BASE64}")
exten => sms,n,Hangup()

; 080 Call Outgoing Context
[080-calls]
exten => _080XXXXXXXX,1,NoOp(080 Call to ${EXTEN})
exten => _080XXXXXXXX,n,Dial(Quectel/quectel0/${EXTEN},30)
exten => _080XXXXXXXX,n,Hangup()
EOF

echo "  ✅ Dialplan 설정 완료"

# 6. USB 모뎀 인식을 위한 udev 규칙 추가
echo "🔌 6단계: USB 모뎀 udev 규칙 설정..."
cat > /etc/udev/rules.d/99-quectel.rules << 'EOF'
# Quectel EC25 USB Modem
SUBSYSTEM=="tty", ATTRS{idVendor}=="2c7c", ATTRS{idProduct}=="0125", SYMLINK+="quectel%n"
SUBSYSTEM=="usb", ATTRS{idVendor}=="2c7c", ATTRS{idProduct}=="0125", RUN+="/usr/sbin/usb_modeswitch -v 2c7c -p 0125 -M '55534243123456780000000000000011062000000100000000000000000000'"
EOF

echo "  ✅ udev 규칙 설정 완료"

# 7. systemd 서비스 파일 확인 및 활성화
echo "🔧 7단계: systemd 서비스 확인..."
if [ -f /etc/systemd/system/call_queue_runner.service ]; then
    systemctl enable call_queue_runner
    echo "  ✅ call_queue_runner 서비스 활성화"
else
    echo "  ⚠️  call_queue_runner.service 파일이 없습니다. 마이그레이션 스크립트를 먼저 실행하세요."
fi

if [ -f /etc/systemd/system/sms_fetcher.service ]; then
    systemctl enable sms_fetcher
    echo "  ✅ sms_fetcher 서비스 활성화"
else
    echo "  ⚠️  sms_fetcher.service 파일이 없습니다. 마이그레이션 스크립트를 먼저 실행하세요."
fi

# 8. 방화벽 설정 (필요시)
echo "🔥 8단계: 방화벽 설정..."
if command -v ufw &> /dev/null; then
    ufw allow 80/tcp   # HTTP
    ufw allow 443/tcp  # HTTPS
    ufw allow 5060/udp # SIP
    ufw allow 10000:20000/udp # RTP
    echo "  ✅ UFW 방화벽 규칙 추가 완료"
fi

# 9. Asterisk 모듈 로드 설정
echo "📡 9단계: Asterisk 모듈 설정..."
echo "load => chan_quectel.so" >> /etc/asterisk/modules.conf

# 10. 서비스 재시작
echo "🔄 10단계: 서비스 재시작..."
systemctl daemon-reload
systemctl restart asterisk
systemctl restart apache2

echo ""
echo "🎉 RaspPBX 080 SMS System 설정 완료!"
echo ""
echo "=== 다음 단계 ==="
echo "1. 📱 Quectel EC25 모뎀을 USB에 연결하고 독립 전원 공급"
echo "2. 🔍 모뎀 인식 확인: lsusb | grep -i quectel"
echo "3. 🎛️  모뎀 디바이스 확인: ls -la /dev/ttyUSB*"
echo "4. ⚙️  quectel.conf에서 올바른 ttyUSB 포트 설정"
echo "5. 🧪 SMS 처리 테스트 실행"
echo ""
echo "=== 테스트 명령어 ==="
echo "# 모뎀 상태 확인"
echo "asterisk -rx 'quectel show devices'"
echo ""
echo "# SMS 테스트"
echo 'ENC=$(echo -n "수신거부 080-1234-5678 식별번호 1234" | base64)'
echo 'php /var/www/html/sms_auto_processor.php --caller=01011112222 --msg_base64="$ENC"'
echo ""
echo "=== 웹 인터페이스 ==="
echo "🌐 FreePBX: http://$(hostname -I | awk '{print $1}')/admin/"
echo "📱 080 SMS Dashboard: http://$(hostname -I | awk '{print $1}')/dashboard.php"