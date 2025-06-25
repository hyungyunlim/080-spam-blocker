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
