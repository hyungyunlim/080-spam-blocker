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
import contextlib

def update_progress(progress_file, job_id, stage, progress, message):
    """진행 상황 업데이트"""
    progress_data = {
        'job_id': job_id,
        'status': stage,
        'progress': progress,
        'message': message,
        'timestamp': datetime.now().isoformat(),
        'updated_at': int(time.time())
    }
    
    try:
        with open(progress_file, 'w', encoding='utf-8') as f:
            json.dump(progress_data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Progress update error: {e}", file=sys.stderr)

def detect_unsubscribe_patterns(text):
    """수신거부 패턴 검출 (개선된 로직)"""
    
    # 텍스트 정규화
    text_normalized = text.lower().replace(" ", "").replace(".", "")
    
    # 1. 명확한 성공/실패 키워드를 우선적으로 확인
    # "접수되었습니다"는 성공으로 간주하지만, "정상적으로 처리"보다는 약한 성공으로 판단
    success_patterns = {
        '처리완료': (r'(정상적|성공적)으로(처리|완료|등록|차단|해지|거부)되었|이미처리', 95),
        '완료': (r'(처리|등록|차단|해지|거부|접수)완료|(처리|등록|차단|해지|거부)되었습니다', 90),
        '접수': (r'접수되었습니다|접수됐', 85)
    }
    
    failure_patterns = {
        '실패': (r'실패|오류|잘못입력|일치하지않|확인불가', 90)
    }
    
    # 가장 높은 신뢰도의 성공 패턴 찾기
    best_success = None
    for reason, (pattern, confidence) in success_patterns.items():
        if re.search(pattern, text_normalized):
            best_success = {'status': 'success', 'confidence': confidence, 'reason': f'명확한 성공 키워드 감지: {reason}'}
            break # 첫번째로 매칭되는 가장 높은 우선순위의 패턴을 사용

    if best_success:
        return best_success

    # 실패 패턴 확인
    for reason, (pattern, confidence) in failure_patterns.items():
        if re.search(pattern, text_normalized):
            return {'status': 'failed', 'confidence': confidence, 'reason': f'명확한 실패 키워드 감지: {reason}'}

    # 2. 명확한 결과가 없을 경우, '시도' 키워드 확인
    # "수신거부", "0번" 등의 단어는 있지만, "완료" 등의 명확한 결과가 없는 경우
    attempt_patterns = {
        '시도': (r'수신거부|수신차단|해지|거부.*?(번호|안내|방법)', 60)
    }
    for reason, (pattern, confidence) in attempt_patterns.items():
         if re.search(pattern, text_normalized):
            return {'status': 'attempted', 'confidence': confidence, 'reason': '결과가 불분명한 수신거부 시도 키워드 감지'}

    # 3. 어떤 관련 키워드도 찾지 못한 경우
    return {'status': 'unknown', 'confidence': 20, 'reason': '관련 키워드를 찾을 수 없음'}

def get_system_info():
    """시스템 사양 정보 수집"""
    try:
        import psutil
        return {
            'cpu_cores': psutil.cpu_count(),
            'memory_total_gb': round(psutil.virtual_memory().total / (1024**3), 1),
            'memory_available_gb': round(psutil.virtual_memory().available / (1024**3), 1)
        }
    except Exception as e:
        # psutil이 실패할 경우 (예: 권한 문제), 안전한 기본값 반환
        print(f"Warning: Could not get system info with psutil: {e}. Defaulting to safe values.", file=sys.stderr)
        return {'cpu_cores': 1, 'memory_available_gb': 1.0}

def detect_unsubscribe_patterns_old(text):
    """수신거부 패턴 검출"""
    unsubscribe_patterns = [
        r'수신거부', r'수신\s*거부', r'080.*?수신거부', r'광고.*?중단',
        r'마케팅.*?중단', r'SMS.*?수신거부', r'문자.*?수신거부', r'정지.*?누르',
        r'거부.*?센터', r'차단.*?서비스', r'수신.*?SNS.*?입력', r'SNS.*?수신.*?입력',
        r'전용.*?SNS.*?입력'
    ]
    success_patterns = [
        r'수신거부.*?완료', r'차단.*?완료', r'등록.*?완료', r'처리.*?완료',
        r'성공적.*?등록', r'정상.*?처리', r'정상적으로.*?처리', r'처리되었습니다',
        r'완료되었습니다', r'등록되었습니다', r'차단되었습니다',
        r'거부되었습니다',
        r'수신거부가.정상적으로.처리', 
        r'접수되었습니다', r'.*?번이.*?접수', r'.*?수신거부.*?됩니다',
        r'해지.*?완료', r'해지.*?되었습니다'
    ]
    failure_patterns = [
        r'일치하지.*?않', r'잘못.*?입력', r'다시.*?입력', r'확인.*?불가',
        r'처리.*?실패', r'오류.*?발생'
    ]
    
    text_lower = text.lower()
    
    if any(re.search(p, text_lower) for p in success_patterns):
        return {'status': 'success', 'confidence': 90, 'reason': '명확한 성공 키워드 감지됨'}
        
    if any(re.search(p, text_lower) for p in failure_patterns):
        return {'status': 'failed', 'confidence': 90, 'reason': '명확한 실패 키워드 감지됨'}
    
    if any(re.search(p, text_lower) for p in unsubscribe_patterns):
        return {'status': 'attempted', 'confidence': 60, 'reason': '수신거부 시도는 확인되나 결과 불분명'}

    return {'status': 'unknown', 'confidence': 10, 'reason': '관련 키워드를 찾을 수 없음'}

def select_optimal_model(requested_model_size):
    """시스템 사양에 맞는 최적 모델 선택 (로직 수정)"""
    sys_info = get_system_info()
    available_memory = sys_info['memory_available_gb']
    
    # 요청된 모델이 small, base, tiny 같은 작은 모델이면 그대로 존중
    if requested_model_size in ['small', 'base', 'tiny']:
        return requested_model_size

    # 큰 모델 요청 시 메모리 기반으로 결정
    if available_memory >= 6.0:
        if requested_model_size in ['large-v3', 'large-v2', 'medium']:
            return requested_model_size
        else: # large-v3-turbo 등
            return 'large-v3'
    elif available_memory >= 3.0:
        if requested_model_size in ['large-v3', 'large-v2', 'medium']:
            return 'medium'
        else: # 더 작은 모델 요청은 위에서 처리됨
            return 'base' 
    else:
        return 'base'

def analyze_audio_with_progress(audio_file, output_file, progress_file, job_id, model_size='small'):
    """진행 상황 추적과 함께 음성 분석"""
    try:
        sys_info = get_system_info()
        update_progress(progress_file, job_id, 'starting', 5, f'🚀 분석 시작 (CPU: {sys_info["cpu_cores"]}, RAM: {sys_info["memory_available_gb"]}GB)')
        
        if not os.path.exists(audio_file):
            raise FileNotFoundError(f'오디오 파일을 찾을 수 없습니다: {audio_file}')
        
        # optimal_model = select_optimal_model(model_size) # 더 이상 사용하지 않음
        update_progress(progress_file, job_id, 'loading_model', 20, f'⚡ Whisper {model_size} 모델 로딩 중...')
        model = whisper.load_model(model_size)
        
        update_progress(progress_file, job_id, 'transcribing', 40, '🎤 음성-텍스트 변환 중...')
        
        # Whisper의 불필요한 stderr 출력을 막기 위해 리디렉션
        # verbose=None 으로 프로그레스바 비활성화
        with open(os.devnull, 'w') as f, contextlib.redirect_stderr(f):
            result = model.transcribe(audio_file, language='ko', fp16=False, verbose=None) 
        
        update_progress(progress_file, job_id, 'analyzing', 80, '🔍 텍스트 분석 중...')
        transcription = result["text"].strip()
        analysis = detect_unsubscribe_patterns(transcription)
        
        output_data = {
            'file_path': audio_file,
            'timestamp': datetime.now().isoformat(),
            'transcription': transcription,
            'analysis': analysis
        }
        
        update_progress(progress_file, job_id, 'saving', 90, '💾 결과 저장 중...')
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output_data, f, ensure_ascii=False, indent=2)
        
        final_msg = f'🎉 분석 완료! 상태: {analysis["status"]}'
        update_progress(progress_file, job_id, 'completed', 100, final_msg)
        return True
        
    except Exception as e:
        error_message = f"분석 중 오류 발생: {str(e)}"
        update_progress(progress_file, job_id, 'failed', 0, error_message)
        print(error_message, file=sys.stderr)
        return False

def main():
    if len(sys.argv) < 5:
        print(f"사용법: {sys.argv[0]} <audio_file> <output_file> <progress_file> <job_id> [model_size]")
        sys.exit(1)
    
    audio_file = sys.argv[1]
    output_file = sys.argv[2]
    progress_file = sys.argv[3]
    job_id = sys.argv[4]
    model_size = sys.argv[5] if len(sys.argv) > 5 else 'small'
    
    try:
        analyze_audio_with_progress(audio_file, output_file, progress_file, job_id, model_size)
    except Exception as e:
        print(f"스크립트 실행 중 치명적 오류: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
