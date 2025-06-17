#!/usr/bin/env python3
"""
pattern_analyzer_runner.py
-------------------------------------------------
Wrapper around advanced_pattern_analyzer.py that adds real-time progress
updates by parsing stdout lines and writing a JSON progress file that the
web UI already polls.

Usage (same positional args as original script) + --progress_file:
    python3 pattern_analyzer_runner.py <audio_wav> <result_json> <phone> \
        --progress_file <progress_json> [--model medium]

The wrapper writes stages:
  queued → starting → loading_model → transcribing → analyzing_pattern
  → saving → completed / error

If the underlying script prints Korean keywords ("Whisper 모델 로딩", "음성 인식 시작",
"인식 완료", "패턴 분석 완료"), we bump percentages accordingly.
"""

import argparse, json, subprocess, sys, time, os, re
from datetime import datetime

# --------------------------- stage → steps map --------------------------------

def stage_to_steps(stage: str, pct: int):
    """Return detailed progress dict for each analysis step so that the UI can
    render multi-bar progress (audio_processing, pattern_detection, pattern_analysis, saving).

    The percentages are *approximate* and chosen to give users visual feedback.
    """
    steps = {
        "audio_processing": 0,
        "pattern_detection": 0,
        "pattern_analysis": 0,
        "saving": 0,
    }

    # Audio processing covers model loading & STT
    if stage in ("starting", "loading_model"):
        steps["audio_processing"] = min(pct, 30)
    elif stage in ("model_loaded", "transcribing", "transcribed", "analyzing_pattern", "saving", "completed"):
        steps["audio_processing"] = 100

    # Pattern detection (basic regex / preliminary)
    if stage in ("transcribing", "transcribed", "analyzing_pattern", "saving", "completed"):
        # Give a small lead-in until STT is finished
        steps["pattern_detection"] = 10 if stage == "transcribing" else 100

    # Pattern analysis (advanced analyzer)
    if stage in ("analyzing_pattern", "saving", "completed"):
        steps["pattern_analysis"] = 70 if stage == "analyzing_pattern" else 100

    # Saving step
    if stage in ("saving", "completed"):
        steps["saving"] = 50 if stage == "saving" else 100

    return steps

# --------------------------- progress helper ---------------------------------

def write_progress(progress_file: str, stage: str, pct: int, msg: str):
    data = {
        "stage": stage,
        "percentage": pct,
        "message": msg,
        "timestamp": datetime.now().isoformat(),
        "updated_at": int(time.time()),
        "steps": stage_to_steps(stage, pct),
    }
    try:
        with open(progress_file, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
    except Exception as e:
        print(f"Progress write error: {e}", file=sys.stderr)

# --------------------------- CLI ---------------------------------------------

parser = argparse.ArgumentParser(description="Pattern analyzer wrapper with progress")
parser.add_argument("audio", help="wav file")
parser.add_argument("output", help="result json file")
parser.add_argument("phone", help="target phone")
parser.add_argument("--model", default="base", help="Whisper model size")
parser.add_argument("--progress_file", required=True, help="progress json path")
default_script = os.path.join(os.path.dirname(__file__), 'advanced_pattern_analyzer.py')
parser.add_argument("--script", default=default_script, help="underlying analyzer script")
args = parser.parse_args()

pf = args.progress_file
write_progress(pf, "starting", 5, "Python 래퍼 시작중...")

# underlying script expects: audio output phone [model]
cmd = [
    "python3", "-u",  # unbuffered output so wrapper can read lines realtime
    args.script,
    args.audio,
    args.output,
    args.phone,
    args.model  # positional model size
]

# 디버그 출력
print("Executing:", " ".join(cmd))

proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)

stage_map = [
    (re.compile(r"모델 로딩"), ("loading_model", 15)),
    (re.compile(r"모델 로드 완료"), ("model_loaded", 25)),
    (re.compile(r"음성 인식 시작|음성 ?인식 시작|STT"), ("transcribing", 40)),
    (re.compile(r"인식 완료"), ("transcribed", 60)),
    (re.compile(r"패턴 분석", re.I), ("analyzing_pattern", 80)),
    (re.compile(r"결과 저장"), ("saving", 90)),
]

current_pct = 5
current_stage = "starting"
last_emit = time.time()
try:
    for line in proc.stdout:
        line = line.rstrip()
        print(line)
        for pattern, (stage, pct) in stage_map:
            if pattern.search(line):
                if pct > current_pct:
                    current_pct = pct
                current_stage = stage
                write_progress(pf, stage, current_pct, line[:120])
                break
        else:
            # 일반 로그 라인도 주기적으로 메시지에 반영하여 UI에 표시
            now = time.time()
            if now - last_emit > 0.5:
                write_progress(pf, current_stage, current_pct, line[:120])
                last_emit = now
except Exception as e:
    write_progress(pf, "error", current_pct, f"Wrapper read error: {e}")
    sys.exit(1)

ret = proc.wait()
if ret == 0:
    write_progress(pf, "completed", 100, "분석 완료!")
    # Optionally remove progress file after short delay
    time.sleep(1)
else:
    write_progress(pf, "error", current_pct, f"분석 스크립트 오류 (code {ret})")
    sys.exit(ret) 