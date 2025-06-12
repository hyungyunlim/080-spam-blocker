#!/usr/bin/env bash
# cleanup_backups.sh
# -------------------------------------------
# Deletes old backup files, keeping only the
# most recent N copies for each pattern.
# -------------------------------------------
# Usage: bash cleanup_backups.sh [keep]
#   keep : number of recent files to retain (default 3)
#
# Recommend to add to crontab, e.g.
#   0 3 * * * /var/www/html/spam/cleanup_backups.sh

set -euo pipefail

KEEP="${1:-3}"
DRY_RUN=false
if [[ "$1" == "--dry-run" || "$2" == "--dry-run" ]]; then
  DRY_RUN=true
  shift || true
fi
if [[ $1 =~ ^[0-9]+$ ]]; then
  KEEP="$1"
fi
BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$BASE_DIR"

# Enable nullglob so patterns that match nothing expand to empty
shopt -s nullglob

cleanup() {
  local pattern="$1"
  local keep="$2"
  files=( $pattern )
  # sort by modification time (newest first)
  IFS=$'\n' files=( $(ls -1t ${files[@]} 2>/dev/null || true) )
  local idx=0
  for f in "${files[@]}"; do
    idx=$((idx+1))
    if (( idx > keep )); then
      # Skip directories – remove only regular files
      if [[ -f "$f" ]]; then
        # Delete only if filename contains explicit backup marker (.bak. or .backup.)
        if [[ "$f" =~ \.bak\. || "$f" =~ \.backup\. ]]; then
          if $DRY_RUN; then
            echo "[cleanup_backups] (dry-run) Would remove: $f"
          else
            echo "[cleanup_backups] Removing old backup: $f"
            rm -f -- "$f"
          fi
        else
          echo "[cleanup_backups] Skip (non-backup file): $f"
        fi
      fi
    fi
  done
}

# patterns.json backups
cleanup "$BASE_DIR/patterns.json.backup.*" "$KEEP"

# extensions_custom.conf backups in project and /etc path if linked
cleanup "$BASE_DIR/extensions_custom.conf.bak.*" "$KEEP"

# (안전) .bak.* 의무 제거는 중단 — 예기치 않은 매칭 가능성 때문에 수동 관리 권장
echo "[cleanup_backups] Skipping generic *.bak.* cleanup for safety"

# ---------------------------------------------
#  progress/*.json  (analysis / discovery) – older than 48h
# ---------------------------------------------

find "$BASE_DIR/progress" -type f -name '*.json' -mmin +$((48*60)) -print -delete 2>/dev/null || true

# ---------------------------------------------
#  pattern_discovery/*.json[.done] – keep last 5 per phone
# ---------------------------------------------

discovery_dir="$BASE_DIR/pattern_discovery"
if [[ -d "$discovery_dir" ]]; then
  # Iterate over files group by phone number extracted from filename
  for file in "$discovery_dir"/pattern_*_*.json*; do
    [[ -e "$file" ]] || continue
    # extract phone (last field before .json)
    # filename format: pattern_<uniqid>_<ts>.json[.done]
    base=$(basename "$file")
    phone_key=$(echo "$base" | awk -F '_' '{print $(NF-1)}' | sed 's/\.json.*//')
    [[ -z "$phone_key" ]] && phone_key="misc"
    mapfile -t d_files < <(ls -1t "$discovery_dir"/*_${phone_key}.json* 2>/dev/null)
    idx=0
    for f in "${d_files[@]}"; do
      idx=$((idx+1))
      if (( idx > 5 )); then
        if [[ -f "$f" ]]; then
          case "$f" in *.php|*.py|*.sh) echo "[cleanup_backups] Skip (source file): $f";; * ) echo "[cleanup_backups] Removing old discovery result: $f"; rm -f -- "$f";; esac
        fi
      fi
    done
  done
fi

# ---------------------------------------------
#  analysis_logs/discovery_*.log – keep last 5
# ---------------------------------------------

cleanup "$BASE_DIR/analysis_logs/discovery_*.log" 5

exit 0 