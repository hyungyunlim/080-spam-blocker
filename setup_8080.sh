#!/bin/bash

echo "Apache 8080 포트 설정을 시작합니다..."

# 백업 생성
echo "1. 설정 파일 백업 중..."
sudo cp /etc/apache2/ports.conf /etc/apache2/ports.conf.backup
sudo cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf.backup

# ports.conf에 8080 포트 추가
echo "2. ports.conf에 8080 포트 추가 중..."
if ! grep -q "Listen 8080" /etc/apache2/ports.conf; then
    sudo sed -i '/^Listen 80$/a Listen 8080' /etc/apache2/ports.conf
    echo "   - 8080 포트 추가 완료"
else
    echo "   - 8080 포트가 이미 설정되어 있습니다"
fi

# 8080 가상호스트 설정 복사
echo "3. 8080 가상호스트 설정 중..."
sudo cp /var/www/html/8080-default.conf /etc/apache2/sites-available/
sudo a2ensite 8080-default.conf

# Apache 설정 테스트
echo "4. Apache 설정 테스트 중..."
if sudo apache2ctl configtest; then
    echo "   - 설정 테스트 통과"
    
    # Apache 재시작
    echo "5. Apache 재시작 중..."
    sudo systemctl reload apache2
    
    # 포트 확인
    echo "6. 포트 상태 확인 중..."
    ss -tlnp | grep :8080
    
    echo "7. 테스트 중..."
    sleep 2
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200"; then
        echo "   ✅ 8080 포트 설정 완료! 정상 작동 중입니다."
    else
        echo "   ⚠️  8080 포트가 응답하지 않습니다. 설정을 확인하세요."
    fi
else
    echo "   ❌ Apache 설정에 오류가 있습니다. 설정을 확인하세요."
fi

echo "설정 완료!"