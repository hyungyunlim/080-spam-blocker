#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
간단한 080 수신거부 음성 분석기
"""

import os
import sys
import json
import re
import whisper
from datetime import datetime

def analyze_text(text):
    """텍스트 내용을 분석하여 수신거부 성공 여부 판단"""
    if not text:
        return {
            "status": "error",
            "confidence": 0,
            "reason": "텍스트 변환 실패"
        }
    
    text_lower = text.lower()
    
    # 성공 키워드
    success_keywords = [
        "수신거부", "처리되었습니다", "완료되었습니다", "삭제되었습니다",
        "차단되었습니다", "해지되었습니다", "확인되었습니다", "정상처리",
        "처리완료", "등록완료", "해지완료", "삭제완료"
    ]
    
    # 실패 키워드  
    failure_keywords = [
        "오류", "실패", "잘못된", "확인할 수 없습니다", "다시", "재시도",
        "올바르지 않습니다", "유효하지 않습니다", "처리할 수 없습니다", "일치하지 않습니다"
    ]
    
    success_count = sum(1 for keyword in success_keywords if keyword in text_lower)
    failure_count = sum(1 for keyword in failure_keywords if keyword in text_lower)
    
    if success_count > failure_count and success_count > 0:
        return {
            "status": "success",
            "confidence": min(90, 60 + success_count * 10),
            "reason": f"성공 키워드 {success_count}개 감지"
        }
    elif failure_count > success_count and failure_count > 0:
        return {
            "status": "failure", 
            "confidence": min(90, 60 + failure_count * 10),
            "reason": f"실패 키워드 {failure_count}개 감지"
        }
    else:
        return {
            "status": "unknown",
            "confidence": 20,
            "reason": "명확한 키워드를 찾을 수 없음"
        }

def main():
    if len(sys.argv) < 3:
        print("사용법: python3 simple_analyzer.py [음성파일] [출력파일]")
        sys.exit(1)
    
    audio_file = sys.argv[1]
    output_file = sys.argv[2]
    model_size = sys.argv[3] if len(sys.argv) > 3 else "base"
    
    try:
        # Whisper 모델 로드
        model = whisper.load_model(model_size)
        
        # 음성 변환
        result = model.transcribe(audio_file, language="ko")
        transcription = result["text"].strip()
        
        # 텍스트 분석
        analysis = analyze_text(transcription)
        
        # 결과 저장
        output_data = {
            "file_path": audio_file,
            "timestamp": datetime.now().isoformat(),
            "transcription": transcription,
            "analysis": analysis,
            "file_size": os.path.getsize(audio_file) if os.path.exists(audio_file) else 0
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output_data, f, ensure_ascii=False, indent=2)
        
        print("분석 완료:", output_file)
        
    except Exception as e:
        error_data = {
            "file_path": audio_file,
            "timestamp": datetime.now().isoformat(),
            "transcription": None,
            "analysis": {
                "status": "error",
                "confidence": 0,
                "reason": f"분석 실패: {str(e)}"
            },
            "file_size": 0
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(error_data, f, ensure_ascii=False, indent=2)
        
        print(f"오류: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main() 