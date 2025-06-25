#!/bin/bash

# 080 SMS System Migration to RaspPBX Script
# 현재 시스템을 RaspPBX로 마이그레이션하는 스크립트

set -e

BACKUP_DIR="/tmp/migration_backup_$(date +%Y%m%d_%H%M%S)"
RASPBX_IP=""
RASPBX_USER="root"

echo "=== 080 SMS System → RaspPBX Migration Script ==="
echo "백업 디렉토리: $BACKUP_DIR"

# 라즈베리파이 IP 입력받기
read -p "RaspPBX 라즈베리파이 IP 주소를 입력하세요: " RASPBX_IP
if [[ -z "$RASPBX_IP" ]]; then
    echo "❌ IP 주소를 입력해야 합니다."
    exit 1
fi

echo "🔄 마이그레이션 시작: $RASPBX_IP"

# 1. 백업 디렉토리 생성
mkdir -p "$BACKUP_DIR"
cd "$BACKUP_DIR"

echo "📦 1단계: 현재 시스템 백업 중..."

# MySQL/MariaDB 백업
echo "  → MySQL 데이터베이스 백업..."
mysqldump -u root -p asterisk > freepbx_backup.sql 2>/dev/null || echo "  ⚠️  asterisk DB 백업 실패"
mysqldump -u root -p asteriskcdrdb > cdr_backup.sql 2>/dev/null || echo "  ⚠️  CDR DB 백업 실패"

# Asterisk 설정 백업
echo "  → Asterisk 설정 백업..."
sudo tar -czf asterisk_config.tar.gz /etc/asterisk/ 2>/dev/null || echo "  ⚠️  Asterisk 설정 백업 실패"

# FreePBX 웹 파일 백업
echo "  → FreePBX 웹 파일 백업..."
sudo tar -czf freepbx_web.tar.gz /var/www/html/admin/ 2>/dev/null || echo "  ⚠️  FreePBX 웹 백업 실패"

# Asterisk 런타임 데이터 백업
echo "  → Asterisk 런타임 데이터 백업..."
sudo tar -czf asterisk_lib.tar.gz /var/lib/asterisk/ 2>/dev/null || echo "  ⚠️  Asterisk 라이브러리 백업 실패"

# 080 SMS 커스텀 시스템 백업
echo "  → 080 SMS 커스텀 시스템 백업..."
cd /var/www/html
tar -czf "$BACKUP_DIR/sms_system.tar.gz" \
    *.php *.db *.json \
    logs/ analysis_logs/ call_queue/ \
    --exclude=admin/ \
    --exclude=ucp 2>/dev/null || echo "  ⚠️  SMS 시스템 백업 실패"

# systemd 서비스 파일 백업
echo "  → systemd 서비스 파일 백업..."
sudo cp /etc/systemd/system/call_queue_runner.service "$BACKUP_DIR/" 2>/dev/null || echo "  ⚠️  call_queue_runner 서비스 백업 실패"
sudo cp /etc/systemd/system/sms_fetcher.service "$BACKUP_DIR/" 2>/dev/null || echo "  ⚠️  sms_fetcher 서비스 백업 실패"

echo "✅ 백업 완료: $BACKUP_DIR"

# 2. RaspPBX 연결 테스트
echo "🔗 2단계: RaspPBX 연결 테스트..."
if ! ping -c 1 "$RASPBX_IP" &> /dev/null; then
    echo "❌ RaspPBX ($RASPBX_IP)에 연결할 수 없습니다."
    exit 1
fi

if ! ssh -o ConnectTimeout=5 "$RASPBX_USER@$RASPBX_IP" "echo 'Connection OK'" &> /dev/null; then
    echo "❌ SSH 연결 실패. SSH 키 설정을 확인하세요."
    echo "💡 힌트: ssh-copy-id $RASPBX_USER@$RASPBX_IP"
    exit 1
fi

echo "✅ RaspPBX 연결 성공"

# 3. 백업 파일 전송
echo "📤 3단계: 백업 파일 RaspPBX로 전송..."
ssh "$RASPBX_USER@$RASPBX_IP" "mkdir -p /tmp/migration_restore"
scp -r "$BACKUP_DIR"/* "$RASPBX_USER@$RASPBX_IP:/tmp/migration_restore/"
echo "✅ 파일 전송 완료"

# 4. RaspPBX에서 복원 스크립트 생성 및 실행
echo "🔧 4단계: RaspPBX에서 복원 스크립트 실행..."

# 복원 스크립트 생성
cat > restore_script.sh << 'EOF'
#!/bin/bash
set -e

echo "=== RaspPBX 복원 시작 ==="

# FreePBX 서비스 중지
echo "  → FreePBX 서비스 중지..."
systemctl stop asterisk apache2 mysql

# MySQL 데이터베이스 복원
echo "  → MySQL 데이터베이스 복원..."
systemctl start mysql
sleep 5

if [ -f /tmp/migration_restore/freepbx_backup.sql ]; then
    mysql -u root asterisk < /tmp/migration_restore/freepbx_backup.sql
    echo "    ✅ FreePBX DB 복원 완료"
fi

if [ -f /tmp/migration_restore/cdr_backup.sql ]; then
    mysql -u root asteriskcdrdb < /tmp/migration_restore/cdr_backup.sql
    echo "    ✅ CDR DB 복원 완료"
fi

# Asterisk 설정 복원
echo "  → Asterisk 설정 복원..."
if [ -f /tmp/migration_restore/asterisk_config.tar.gz ]; then
    tar -xzf /tmp/migration_restore/asterisk_config.tar.gz -C /
    echo "    ✅ Asterisk 설정 복원 완료"
fi

# FreePBX 웹 파일 복원
echo "  → FreePBX 웹 파일 복원..."
if [ -f /tmp/migration_restore/freepbx_web.tar.gz ]; then
    tar -xzf /tmp/migration_restore/freepbx_web.tar.gz -C /
    echo "    ✅ FreePBX 웹 파일 복원 완료"
fi

# Asterisk 런타임 데이터 복원
echo "  → Asterisk 런타임 데이터 복원..."
if [ -f /tmp/migration_restore/asterisk_lib.tar.gz ]; then
    tar -xzf /tmp/migration_restore/asterisk_lib.tar.gz -C /
    echo "    ✅ Asterisk 런타임 데이터 복원 완료"
fi

# 080 SMS 커스텀 시스템 복원
echo "  → 080 SMS 커스텀 시스템 복원..."
if [ -f /tmp/migration_restore/sms_system.tar.gz ]; then
    cd /var/www/html
    tar -xzf /tmp/migration_restore/sms_system.tar.gz
    echo "    ✅ 080 SMS 시스템 복원 완료"
fi

# systemd 서비스 설치
echo "  → 커스텀 서비스 설치..."
if [ -f /tmp/migration_restore/call_queue_runner.service ]; then
    cp /tmp/migration_restore/call_queue_runner.service /etc/systemd/system/
    systemctl enable call_queue_runner
    echo "    ✅ call_queue_runner 서비스 설치 완료"
fi

if [ -f /tmp/migration_restore/sms_fetcher.service ]; then
    cp /tmp/migration_restore/sms_fetcher.service /etc/systemd/system/
    systemctl enable sms_fetcher
    echo "    ✅ sms_fetcher 서비스 설치 완료"
fi

# 권한 설정
echo "  → 파일 권한 설정..."
chown -R asterisk:asterisk /var/www/html/admin/
chown -R asterisk:asterisk /var/www/html/*.php
chown -R asterisk:asterisk /var/www/html/*.db
chown -R asterisk:asterisk /var/www/html/*.json
chown -R asterisk:asterisk /var/lib/asterisk/
chown -R asterisk:asterisk /etc/asterisk/

# 필요한 PHP 패키지 설치
echo "  → 필요한 패키지 설치..."
apt update
apt install -y php-sqlite3 sqlite3

# FreePBX 재시작
echo "  → FreePBX 서비스 재시작..."
systemctl daemon-reload
systemctl start mysql
systemctl start asterisk
systemctl start apache2
systemctl start call_queue_runner
systemctl start sms_fetcher

echo "=== 복원 완료 ==="
echo "🌐 FreePBX: http://$(hostname -I | awk '{print $1}')/admin/"
echo "📱 080 SMS Dashboard: http://$(hostname -I | awk '{print $1}')/dashboard.php"
EOF

# 복원 스크립트 전송 및 실행
scp restore_script.sh "$RASPBX_USER@$RASPBX_IP:/tmp/migration_restore/"
ssh "$RASPBX_USER@$RASPBX_IP" "chmod +x /tmp/migration_restore/restore_script.sh && /tmp/migration_restore/restore_script.sh"

echo "🎉 마이그레이션 완료!"
echo "=== 접속 정보 ==="
echo "🌐 FreePBX 관리자: http://$RASPBX_IP/admin/"
echo "📱 080 SMS 대시보드: http://$RASPBX_IP/dashboard.php"
echo "🔧 SSH 접속: ssh $RASPBX_USER@$RASPBX_IP"
echo ""
echo "⚠️  마이그레이션 후 확인사항:"
echo "1. Quectel EC25 모뎀 USB 연결 및 전원 공급"
echo "2. chan_quectel 모듈 설치: https://github.com/ca4ti/asterisk-chan-quectel"
echo "3. /etc/asterisk/quectel.conf 설정"
echo "4. SMS 처리 테스트 실행"
echo ""
echo "📁 백업 파일 위치: $BACKUP_DIR"