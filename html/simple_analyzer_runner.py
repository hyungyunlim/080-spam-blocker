#!/usr/bin/env python3
"""
simple_analyzer_runner.py
-------------------------------------------------
Wrapper for simple_analyzer.py that provides real-time JSON progress so the
web UI can show detailed steps just like pattern discovery analysis.
"""
import argparse, json, subprocess, sys, time, re
from datetime import datetime


def write_progress(pf, stage, pct, msg):
    data = {
        'stage': stage,
        'percentage': pct,
        'message': msg,
        'timestamp': datetime.now().isoformat(),
        'updated_at': int(time.time())
    }
    try:
        with open(pf, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print('progress write err:', e, file=sys.stderr)

parser = argparse.ArgumentParser(description='Wrapper for simple analyzer with progress')
parser.add_argument('audio')
parser.add_argument('output')
parser.add_argument('model', default='small', nargs='?')
parser.add_argument('--progress_file', required=True)
parser.add_argument('--analysis_id', required=True)
parser.add_argument('--script', default='/var/www/html/simple_analyzer.py')
args = parser.parse_args()

pf = args.progress_file
write_progress(pf, 'starting', 5, 'Python 래퍼 시작중...')

cmd = ['python3', '-u', args.script,
       '--file', args.audio,
       '--output_dir', '/var/www/html/analysis_results',
       '--model', args.model,
       '--analysis_id', args.analysis_id]
print('Executing:', ' '.join(cmd))
proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)

stage_map = [
    (re.compile(r'파일 확인 완료'), ('file_check', 10)),
    (re.compile(r'모델 로딩'), ('loading_model', 20)),
    (re.compile(r'모델 로드 완료'), ('model_loaded', 30)),
    (re.compile(r'음성 인식 시작'), ('transcribing', 40)),
    (re.compile(r'STT 변환 완료'), ('transcription_done', 70)),
    (re.compile(r'음성을 텍스트|STT 변환(?! 완료)'), ('transcribing', 40)),
    (re.compile(r'패턴 분석'), ('analyzing_keywords', 80)),
    (re.compile(r'결과 저장'), ('saving', 90)),
]
cur_pct = 5
cur_stage = 'starting'
last_stage_change = time.time()

# Progress throttling constants
THROTTLE = 0.1  # seconds between automatic message emits for non-matched lines
# 각 단계가 UI에 충분히 노출되도록 최소 지속 시간(초)
MIN_STAGE_DURATION = 1.2

for line in proc.stdout:
    line = line.rstrip()
    print(line)
    matched = False

    # 1) 명시적 stage 키워드 매칭
    for patt, (stg, pct) in stage_map:
        if patt.search(line):
            # 새 단계 전환 처리 – 최소 단계 지속 시간 보장
            if stg != cur_stage or pct != cur_pct:
                elapsed = time.time() - last_stage_change
                if elapsed < MIN_STAGE_DURATION:
                    time.sleep(MIN_STAGE_DURATION - elapsed)

                cur_stage, cur_pct = stg, pct
                write_progress(pf, stg, pct, line[:120])
                last_stage_change = time.time()
            matched = True
            break

    # 2) Whisper 세그먼트 처리
    if not matched:
        if line.startswith('['):
            # 세그먼트 실시간 스트림→ transcribing 단계 유지
            if cur_stage != 'transcribing':
                cur_stage, cur_pct = 'transcribing', 40
            write_progress(pf, cur_stage, cur_pct, line[:120])
            last_stage_change = time.time()
            continue  # 다음 줄 처리

        # 기타 로그는 THROTTLE 간격으로 출력
        now = time.time()
        if now - last_stage_change > THROTTLE:
            write_progress(pf, cur_stage, cur_pct, line[:120])
            last_stage_change = now

ret = proc.wait()
if ret == 0:
    write_progress(pf, 'completed', 100, '분석 완료!')
else:
    write_progress(pf, 'error', cur_pct, f'분석 오류 code {ret}')
    sys.exit(ret) 