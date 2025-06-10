#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
080 수신거부 음성 분석기
- 녹음된 음성 파일을 텍스트로 변환 (STT)
- 수신거부 성공/실패 여부 자동 판단
- 결과를 JSON 형태로 저장
"""

import os
import sys
import json
import re
import whisper
from datetime import datetime
import argparse

class VoiceAnalyzer:
    def __init__(self, model_size="base"):
        """
        음성 분석기 초기화
        model_size: tiny, base, small, medium, large (정확도 vs 속도)
        """
        print(f"Whisper 모델 로딩 중... ({model_size})")
        self.model = whisper.load_model(model_size)
        
        # 수신거부 성공/실패 키워드
        self.success_keywords = [
            "수신거부", "처리되었습니다", "완료되었습니다", "삭제되었습니다",
            "차단되었습니다", "해지되었습니다", "확인되었습니다", "정상처리",
            "처리완료", "등록완료", "해지완료", "삭제완료", "수신차단",
            "광고수신거부", "문자차단", "SMS차단"
        ]
        
        self.failure_keywords = [
            "오류", "실패", "잘못된", "확인할 수 없습니다", "다시", "재시도",
            "올바르지 않습니다", "유효하지 않습니다", "처리할 수 없습니다",
            "시스템 오류", "접수되지", "번호가 맞지", "정보가 일치하지"
        ]
        
        self.uncertain_keywords = [
            "안내", "메뉴", "번호를 입력", "다시 입력", "확인해주세요",
            "잠시만", "처리중", "연결중", "대기"
        ]
    
    def transcribe_audio(self, audio_path):
        """
        음성 파일을 텍스트로 변환
        """
        try:
            print(f"음성 파일 분석 중: {audio_path}")
            result = self.model.transcribe(audio_path, language="ko")
            return result["text"].strip()
        except Exception as e:
            print(f"STT 오류: {e}")
            return None
    
    def analyze_text(self, text):
        """
        텍스트 내용을 분석하여 수신거부 성공 여부 판단
        """
        if not text:
            return {
                "status": "error",
                "confidence": 0,
                "reason": "텍스트 변환 실패"
            }
        
        text_lower = text.lower()
        
        # 성공 키워드 점수
        success_score = sum(1 for keyword in self.success_keywords 
                          if keyword in text_lower)
        
        # 실패 키워드 점수  
        failure_score = sum(1 for keyword in self.failure_keywords 
                          if keyword in text_lower)
        
        # 불확실 키워드 점수
        uncertain_score = sum(1 for keyword in self.uncertain_keywords 
                            if keyword in text_lower)
        
        # 특별 패턴 검사
        patterns = {
            "success": [
                r"수신거부.*완료",
                r"처리.*완료",
                r"삭제.*완료",
                r"\d+.*수신거부",
                r"정상.*처리"
            ],
            "failure": [
                r"오류.*발생",
                r"실패.*처리",
                r"잘못된.*정보",
                r"다시.*입력"
            ]
        }
        
        pattern_success = sum(1 for pattern in patterns["success"] 
                            if re.search(pattern, text_lower))
        pattern_failure = sum(1 for pattern in patterns["failure"] 
                            if re.search(pattern, text_lower))
        
        total_success = success_score + pattern_success
        total_failure = failure_score + pattern_failure
        
        # 판단 로직
        if total_success > total_failure and total_success > 0:
            confidence = min(90, 60 + total_success * 10)
            return {
                "status": "success",
                "confidence": confidence,
                "reason": f"성공 키워드 {total_success}개 감지"
            }
        elif total_failure > total_success and total_failure > 0:
            confidence = min(90, 60 + total_failure * 10)
            return {
                "status": "failure", 
                "confidence": confidence,
                "reason": f"실패 키워드 {total_failure}개 감지"
            }
        elif uncertain_score > 2:
            return {
                "status": "uncertain",
                "confidence": 40,
                "reason": f"불확실한 응답 (대기/안내 메시지)"
            }
        else:
            return {
                "status": "unknown",
                "confidence": 20,
                "reason": "명확한 키워드를 찾을 수 없음"
            }
    
    def analyze_file(self, audio_path, output_path=None):
        """
        음성 파일 전체 분석 프로세스
        """
        # 텍스트 변환
        transcription = self.transcribe_audio(audio_path)
        
        # 내용 분석
        analysis = self.analyze_text(transcription)
        
        # 결과 구성
        result = {
            "file_path": audio_path,
            "timestamp": datetime.now().isoformat(),
            "transcription": transcription,
            "analysis": analysis,
            "file_size": os.path.getsize(audio_path) if os.path.exists(audio_path) else 0
        }
        
        # 결과 저장
        if output_path:
            with open(output_path, 'w', encoding='utf-8') as f:
                json.dump(result, f, ensure_ascii=False, indent=2)
            print(f"분석 결과 저장: {output_path}")
        
        return result

def main():
    parser = argparse.ArgumentParser(description="080 수신거부 음성 분석기")
    parser.add_argument("audio_file", help="분석할 음성 파일 경로")
    parser.add_argument("-o", "--output", help="결과 저장할 JSON 파일 경로")
    parser.add_argument("-m", "--model", default="base", 
                       choices=["tiny", "base", "small", "medium", "large"],
                       help="Whisper 모델 크기 (기본값: base)")
    
    args = parser.parse_args()
    
    if not os.path.exists(args.audio_file):
        print(f"오류: 파일을 찾을 수 없습니다: {args.audio_file}")
        sys.exit(1)
    
    # 분석기 초기화
    analyzer = VoiceAnalyzer(model_size=args.model)
    
    # 출력 파일 경로 생성
    if not args.output:
        base_name = os.path.splitext(os.path.basename(args.audio_file))[0]
        args.output = f"{base_name}_analysis.json"
    
    # 분석 실행
    try:
        result = analyzer.analyze_file(args.audio_file, args.output)
        
        # 결과 출력
        print("\n" + "="*50)
        print("🎤 음성 분석 결과")
        print("="*50)
        print(f"📄 텍스트: {result['transcription']}")
        print(f"📊 판단: {result['analysis']['status'].upper()}")
        print(f"🎯 신뢰도: {result['analysis']['confidence']}%")
        print(f"💭 이유: {result['analysis']['reason']}")
        print("="*50)
        
    except Exception as e:
        print(f"분석 중 오류 발생: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main() 