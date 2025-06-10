#!/bin/bash
# 080 수신거부 음성 분석기 실행 스크립트

# 인자 확인
if [ $# -lt 2 ]; then
    echo "사용법: $0 [음성파일] [출력파일]"
    exit 1
fi

AUDIO_FILE="$1"
OUTPUT_FILE="$2"
MODEL="${3:-base}"

# 가상환경 활성화 및 스크립트 실행
source /home/linux/080-spam-blocker/venv/bin/activate
cd /home/linux/080-spam-blocker
python voice_analyzer.py "$AUDIO_FILE" -o "$OUTPUT_FILE" -m "$MODEL" 