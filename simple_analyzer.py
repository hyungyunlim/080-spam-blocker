#!/usr/bin/env python3
"""
음성 분석기
- Whisper를 사용한 음성-텍스트 변환
- 수신거부 키워드 검출 및 분석
- 실시간 진행 상황 추적
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

def update_progress(progress_file, stage, percentage, message):
    """진행 상황을 JSON 파일로 저장"""
    try:
        progress_data = {
            'stage': stage,
            'percentage': percentage,
            'message': message,
            'timestamp': datetime.now().isoformat(),
            'updated_at': int(time.time())
        }
        with open(progress_file, 'w', encoding='utf-8') as f:
            json.dump(progress_data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Progress update error: {e}", file=sys.stderr)

def detect_unsubscribe_patterns(text):
    """수신거부 패턴 검출 (개선된 로직)"""
    
    # 텍스트 정규화
    text_normalized = text.lower().replace(" ", "").replace(".", "")
    
    success_patterns = {
        '처리완료': (r'(정상적|성공적)으로(처리|완료|등록|차단|해지|거부)되었|이미처리', 95),
        '완료': (r'(처리|등록|차단|해지|거부|접수)완료|(처리|등록|차단|해지|거부)되었습니다', 90),
        '접수': (r'접수되었습니다|접수됐', 85),
        '거부관련': (r'거부(가|를|에|의)?\s*(완료|처리|등록|신청)|수신거부.*완료|거부되었습니다|거부\s*되었습니다', 88)
    }
    
    failure_patterns = {
        '실패': (r'실패|오류|잘못입력|일치하지않|확인불가', 90),
        '초과': (r'초과|제한|한도|넘었|넘어', 85),
        '다시': (r'다시\s*(입력|시도|확인)|재입력|재시도|다시시도|다시입력', 85),
        '죄송': (r'죄송|미안|양해|불편|죄송합니다', 80),
        '틀림': (r'틀렸|틀린|잘못된|맞지않', 85)
    }
    
    best_success = None
    for reason, (pattern, confidence) in success_patterns.items():
        if re.search(pattern, text_normalized):
            best_success = {'status': 'success', 'confidence': confidence, 'reason': f'명확한 성공 키워드 감지: {reason}'}
            break

    if best_success:
        return best_success

    for reason, (pattern, confidence) in failure_patterns.items():
        if re.search(pattern, text_normalized):
            return {'status': 'failed', 'confidence': confidence, 'reason': f'명확한 실패 키워드 감지: {reason}'}

    attempt_patterns = {
        '시도': (r'수신거부|수신차단|해지|거부.*?(번호|안내|방법)', 60)
    }
    for reason, (pattern, confidence) in attempt_patterns.items():
         if re.search(pattern, text_normalized):
            return {'status': 'attempted', 'confidence': confidence, 'reason': '결과가 불분명한 수신거부 시도 키워드 감지'}

    return {'status': 'unknown', 'confidence': 20, 'reason': '관련 키워드를 찾을 수 없음'}


def analyze_audio(audio_file, output_file, progress_file=None, model_size='small'):
    """음성 분석 메인 함수"""
    try:
        if progress_file:
            update_progress(progress_file, 'starting', 0, '분석을 시작합니다...')
        
        if not os.path.exists(audio_file):
            raise FileNotFoundError(f'오디오 파일을 찾을 수 없습니다: {audio_file}')
        
        if progress_file:
            update_progress(progress_file, 'loading_model', 20, f'Whisper {model_size} 모델을 로딩중입니다...')
            
        model = whisper.load_model(model_size)
        
        if progress_file:
            update_progress(progress_file, 'transcribing', 40, '음성을 텍스트로 변환중입니다...')
        
        with open(os.devnull, 'w') as f, contextlib.redirect_stderr(f):
            result = model.transcribe(audio_file, language='ko', fp16=False, verbose=None) 
        
        if progress_file:
            update_progress(progress_file, 'analyzing', 70, '변환된 텍스트를 분석중입니다...')
        
        transcription = result["text"].strip()
        analysis = detect_unsubscribe_patterns(transcription)
        
        if progress_file:
            update_progress(progress_file, 'saving', 90, '결과를 저장중입니다...')
        
        output_data = {
            'file_path': audio_file,
            'timestamp': datetime.now().isoformat(),
            'transcription': transcription,
            'analysis': analysis
        }
        
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(output_data, f, ensure_ascii=False, indent=2)
        
        if progress_file:
            update_progress(progress_file, 'completed', 100, '분석이 완료되었습니다!')
            # 완료 후 progress 파일 삭제
            time.sleep(1)  # UI가 완료 상태를 볼 수 있도록 잠시 대기
            try:
                os.remove(progress_file)
            except:
                pass
            
        return True
        
    except Exception as e:
        error_message = f"분석 중 오류 발생: {str(e)}"
        
        if progress_file:
            update_progress(progress_file, 'error', 0, error_message)
        
        # 오류 발생 시, 출력 파일에 오류 정보 기록
        error_data = {
            'file_path': audio_file,
            'timestamp': datetime.now().isoformat(),
            'transcription': None,
            'analysis': {'status': 'error', 'reason': error_message}
        }
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(error_data, f, ensure_ascii=False, indent=2)
        print(error_message, file=sys.stderr)
        return False

def main():
    parser = argparse.ArgumentParser(description="Whisper-based audio analysis for unsubscribe confirmation.")
    parser.add_argument("--file", required=True, help="Path to the audio file to analyze.")
    parser.add_argument("--output_dir", required=True, help="Directory to save the analysis JSON result.")
    parser.add_argument("--model", default="small", help="Whisper model size (e.g., tiny, base, small, medium).")
    parser.add_argument("--progress_file", help="Path to save progress updates.")
    
    args = parser.parse_args()
    
    output_filename = 'analysis_' + os.path.splitext(os.path.basename(args.file))[0] + '.json'
    output_file = os.path.join(args.output_dir, output_filename)
    
    try:
        analyze_audio(args.file, output_file, args.progress_file, args.model)
    except Exception as e:
        print(f"스크립트 실행 중 치명적 오류: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main() 