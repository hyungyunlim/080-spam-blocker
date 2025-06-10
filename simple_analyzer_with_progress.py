#!/usr/bin/env python3
"""
음성 분석기 with Progress Tracking
- Whisper를 사용한 음성-텍스트 변환
- 실시간 진행 상황 업데이트
- 수신거부 키워드 검출 및 분석
"""

import sys
import os
import json
import time
import whisper
import argparse
from datetime import datetime
import re

def update_progress(progress_file, job_id, stage, progress, message):
    """진행 상황 업데이트"""
    progress_data = {
        'job_id': job_id,
        'stage': stage,
        'progress': progress,
        'message': message,
        'timestamp': datetime.now().isoformat(),
        'updated_at': int(time.time())
    }
    
    try:
        with open(progress_file, 'w', encoding='utf-8') as f:
            json.dump(progress_data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Progress update error: {e}")

def detect_unsubscribe_patterns(text):
    """수신거부 패턴 검출"""
    # 수신거부 관련 키워드 패턴
    unsubscribe_patterns = [
        r'수신거부',
        r'수신\s*거부',
        r'080.*?수신거부',
        r'광고.*?중단',
        r'마케팅.*?중단',
        r'SMS.*?수신거부',
        r'문자.*?수신거부',
        r'정지.*?누르',
        r'거부.*?센터',
        r'차단.*?서비스',
        r'수신.*?SNS.*?입력',
        r'SNS.*?수신.*?입력',
        r'전용.*?SNS.*?입력'
    ]
    
    # 성공 패턴
    success_patterns = [
        r'수신거부.*?완료',
        r'차단.*?완료',
        r'등록.*?완료',
        r'처리.*?완료',
        r'성공적.*?등록',
        r'정상.*?처리',
        r'정상적으로.*?처리',
        r'처리되었습니다',
        r'완료되었습니다',
        r'등록되었습니다',
        r'차단되었습니다'
    ]
    
    # 실패 패턴
    failure_patterns = [
        r'일치하지.*?않',
        r'잘못.*?입력',
        r'다시.*?입력',
        r'확인.*?불가',
        r'처리.*?실패',
        r'오류.*?발생'
    ]
    
    text_lower = text.lower()
    
    # 키워드 매칭
    unsubscribe_found = any(re.search(pattern, text, re.IGNORECASE) for pattern in unsubscribe_patterns)
    success_found = any(re.search(pattern, text, re.IGNORECASE) for pattern in success_patterns)
    failure_found = any(re.search(pattern, text, re.IGNORECASE) for pattern in failure_patterns)
    
    if not unsubscribe_found:
        return {
            'status': 'unknown',
            'confidence': 20,
            'reason': '명확한 키워드를 찾을 수 없음'
        }
    
    if success_found:
        return {
            'status': 'success',
            'confidence': 85,
            'reason': '수신거부 성공 메시지 확인됨'
        }
    elif failure_found:
        return {
            'status': 'failed',
            'confidence': 80,
            'reason': '수신거부 실패 메시지 확인됨'
        }
    else:
        return {
            'status': 'attempted',
            'confidence': 60,
            'reason': '수신거부 시도는 확인되나 결과 불분명'
        }

def analyze_audio_with_progress(audio_file, output_file, progress_file, job_id, model_size='small'):
    """진행 상황 추적과 함께 음성 분석"""
    
    try:
        update_progress(progress_file, job_id, 'starting', 0, '음성 분석 시작...')
        
        # 파일 존재 확인
        if not os.path.exists(audio_file):
            update_progress(progress_file, job_id, 'error', 0, f'오디오 파일을 찾을 수 없습니다: {audio_file}')
            return False
            
        update_progress(progress_file, job_id, 'file_check', 10, '파일 확인 완료')
        
        # Whisper 모델 로드
        update_progress(progress_file, job_id, 'loading_model', 20, f'Whisper {model_size} 모델 로딩 중...')
        model = whisper.load_model(model_size)
        
        update_progress(progress_file, job_id, 'model_loaded', 30, '모델 로딩 완료')
        
        # 음성-텍스트 변환
        update_progress(progress_file, job_id, 'transcribing', 40, 'STT 변환 중... (시간이 걸릴 수 있습니다)')
        result = model.transcribe(audio_file, language='ko')
        
        update_progress(progress_file, job_id, 'transcription_done', 70, 'STT 변환 완료, 분석 중...')
        
        transcription = result["text"].strip()
        
        # 수신거부 패턴 분석
        update_progress(progress_file, job_id, 'analyzing', 80, '수신거부 패턴 분석 중...')
        analysis = detect_unsubscribe_patterns(transcription)
        
        # 결과 데이터 구성
        output_data = {
            'file_path': audio_file,
            'timestamp': datetime.now().isoformat(),
            'transcription': transcription,
            'analysis': analysis,
            'file_size': os.path.getsize(audio_file)
        }
        
        update_progress(progress_file, job_id, 'saving', 90, '결과 저장 중...')
        
        # 결과 저장
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output_data, f, ensure_ascii=False, indent=2)
        
        # 파일 권한 설정 (읽기 가능하도록)
        try:
            os.chmod(output_file, 0o644)
        except Exception as e:
            print(f"권한 설정 실패: {e}")
        
        update_progress(progress_file, job_id, 'completed', 100, '분석 완료!')
        
        print(f"분석 완료: {output_file}")
        return True
        
    except Exception as e:
        error_message = f"분석 중 오류 발생: {str(e)}"
        update_progress(progress_file, job_id, 'error', 0, error_message)
        print(error_message)
        return False

def main():
    if len(sys.argv) != 6:
        print("사용법: python3 simple_analyzer_with_progress.py <audio_file> <output_file> <progress_file> <job_id> <model_size>")
        sys.exit(1)
    
    audio_file = sys.argv[1]
    output_file = sys.argv[2]
    progress_file = sys.argv[3]
    job_id = sys.argv[4]
    model_size = sys.argv[5]
    
    success = analyze_audio_with_progress(audio_file, output_file, progress_file, job_id, model_size)
    sys.exit(0 if success else 1)

if __name__ == "__main__":
    main()
