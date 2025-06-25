#!/usr/bin/env python3
"""
M1 맥미니용 STT 분석 서버
라즈베리파이에서 전송된 음성 파일을 M1의 Neural Engine으로 빠르게 분석
"""

import os
import json
import time
import whisper
import tempfile
import threading
from datetime import datetime
from flask import Flask, request, jsonify, send_file
from werkzeug.utils import secure_filename
import re

# ffmpeg 경로 설정 (M1 Mac)
os.environ['PATH'] = '/opt/homebrew/bin:' + os.environ.get('PATH', '')

app = Flask(__name__)

# 설정
UPLOAD_FOLDER = '/tmp/stt_uploads'
RESULTS_FOLDER = '/tmp/stt_results'
ALLOWED_EXTENSIONS = {'wav', 'mp3', 'flac', 'm4a'}
MAX_CONTENT_LENGTH = 50 * 1024 * 1024  # 50MB

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = MAX_CONTENT_LENGTH

# 디렉토리 생성
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(RESULTS_FOLDER, exist_ok=True)

# Whisper 모델 로드 (M1 최적화 - small 모델 사용)
print("Loading Whisper model on M1 Mac...")
model = whisper.load_model("small")  # base에서 small로 한 단계 업그레이드
print("Whisper SMALL model loaded successfully! (정확도 향상)")

# 진행 상황 추적
analysis_progress = {}

def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def detect_success_keywords(text):
    """수신거부 성공 키워드 검출 (개선된 패턴)"""
    # 더 포괄적인 성공 패턴
    success_patterns = [
        # 직접적인 완료 표현
        r'수신거부.*완료',
        r'수신거부.*처리.*완료',
        r'수신거부.*되었습니다',
        r'차단.*완료',
        r'차단.*되었습니다',
        r'해지.*완료',
        r'해지.*되었습니다',
        r'처리.*완료',
        r'확인.*완료',
        r'접수.*완료',
        r'등록.*완료',
        
        # 간접적인 성공 표현
        r'정상.*처리',
        r'성공.*처리',
        r'완료.*처리',
        r'신청.*완료',
        r'요청.*완료',
        r'등록.*성공',
        
        # 감사 표현 (보통 성공 후)
        r'감사합니다.*완료',
        r'이용.*감사',
        r'완료.*감사',
        
        # 확인 코드나 번호 제공
        r'확인번호.*[0-9]+',
        r'접수번호.*[0-9]+',
        r'처리번호.*[0-9]+'
    ]
    
    text_clean = re.sub(r'\s+', '', text.lower())
    
    for pattern in success_patterns:
        if re.search(pattern, text_clean):
            return True, pattern
    
    return False, None

def analyze_audio_file(filepath, call_id):
    """음성 파일 분석 (백그라운드 실행)"""
    try:
        start_time = time.time()
        
        # 진행 상황 업데이트
        analysis_progress[call_id] = {
            'status': 'processing',
            'progress': 10,
            'message': '음성 파일 처리 중...'
        }
        
        # Whisper로 음성 인식
        analysis_progress[call_id]['progress'] = 30
        analysis_progress[call_id]['message'] = 'STT 변환 중...'
        
        result = model.transcribe(filepath, language='ko')
        transcription = result["text"].strip()
        
        analysis_progress[call_id]['progress'] = 70
        analysis_progress[call_id]['message'] = '키워드 분석 중...'
        
        # 키워드 분석
        is_success, matched_pattern = detect_success_keywords(transcription)
        
        processing_time = time.time() - start_time
        
        if is_success:
            status = 'success'
            confidence = 85
            reason = f'수신거부 성공 패턴 발견: {matched_pattern}'
        else:
            status = 'failed'
            confidence = 30
            reason = '수신거부 키워드를 찾을 수 없음'
        
        # 결과 저장
        result_data = {
            'call_id': call_id,
            'transcription': transcription,
            'status': status,
            'confidence': confidence,
            'reason': reason,
            'processing_time': round(processing_time, 2),
            'timestamp': datetime.now().isoformat(),
            'model': 'whisper-base-m1',
            'pattern_hint': matched_pattern if is_success else None
        }
        
        # 진행 상황 완료 업데이트
        analysis_progress[call_id] = {
            'status': 'completed',
            'progress': 100,
            'message': '분석 완료',
            'result': result_data
        }
        
        # 결과 파일 저장
        result_file = os.path.join(RESULTS_FOLDER, f'{call_id}.json')
        with open(result_file, 'w', encoding='utf-8') as f:
            json.dump(result_data, f, ensure_ascii=False, indent=2)
        
        print(f"Analysis completed for {call_id}: {status} ({processing_time:.2f}s)")
        
    except Exception as e:
        error_msg = str(e)
        print(f"Analysis error for {call_id}: {error_msg}")
        
        analysis_progress[call_id] = {
            'status': 'error',
            'progress': 0,
            'message': f'분석 오류: {error_msg}',
            'result': {
                'status': 'failed',
                'confidence': 0,
                'reason': f'분석 중 오류 발생: {error_msg}'
            }
        }

@app.route('/health')
def health_check():
    """헬스 체크"""
    return jsonify({
        'status': 'healthy',
        'service': 'M1-STT-Analyzer',
        'model': 'whisper-base',
        'timestamp': datetime.now().isoformat()
    })

@app.route('/analyze', methods=['POST'])
def analyze():
    """음성 파일 분석 요청"""
    if 'audio_file' not in request.files:
        return jsonify({'error': 'No audio file provided'}), 400
    
    file = request.files['audio_file']
    call_id = request.form.get('call_id', 'unknown')
    
    if file.filename == '':
        return jsonify({'error': 'No file selected'}), 400
    
    if not allowed_file(file.filename):
        return jsonify({'error': 'File type not allowed'}), 400
    
    try:
        # 파일 저장
        filename = secure_filename(f"{call_id}_{file.filename}")
        filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
        file.save(filepath)
        
        # 백그라운드에서 분석 시작
        thread = threading.Thread(target=analyze_audio_file, args=(filepath, call_id))
        thread.start()
        
        return jsonify({
            'message': '분석 시작됨',
            'call_id': call_id,
            'status': 'processing'
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/progress/<call_id>')
def get_progress(call_id):
    """분석 진행 상황 조회"""
    if call_id not in analysis_progress:
        return jsonify({'error': 'Analysis not found'}), 404
    
    return jsonify(analysis_progress[call_id])

@app.route('/result/<call_id>')
def get_result(call_id):
    """분석 결과 조회"""
    result_file = os.path.join(RESULTS_FOLDER, f'{call_id}.json')
    
    if not os.path.exists(result_file):
        return jsonify({'error': 'Result not found'}), 404
    
    with open(result_file, 'r', encoding='utf-8') as f:
        result = json.load(f)
    
    return jsonify(result)

@app.route('/status')
def server_status():
    """서버 상태 및 통계"""
    active_analyses = len([p for p in analysis_progress.values() if p['status'] == 'processing'])
    completed_analyses = len([p for p in analysis_progress.values() if p['status'] == 'completed'])
    
    return jsonify({
        'server': 'M1-STT-Analyzer',
        'model': 'whisper-base',
        'active_analyses': active_analyses,
        'completed_analyses': completed_analyses,
        'total_requests': len(analysis_progress),
        'uptime': time.time() - app.start_time if hasattr(app, 'start_time') else 0
    })

if __name__ == '__main__':
    app.start_time = time.time()
    print("🚀 M1 STT Analysis Server starting...")
    print("📱 Optimized for Apple Silicon Neural Engine")
    print("🎯 Listening on 0.0.0.0:8080")
    
    # M1 맥미니에서 실행
    app.run(host='0.0.0.0', port=8080, debug=False, threaded=True)