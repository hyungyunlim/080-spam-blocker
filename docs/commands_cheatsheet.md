# 080-Spam Blocker – Frequently Used Commands

> Handy one-liners and reference snippets repeatedly used during development/operation.
> Paths assume you are inside `/var/www/html/spam` unless stated otherwise.

---
## 1. Git workflow
* `git status -s`  – quick diff of modified/untracked files.
* `git add -A && git commit -m "<msg>"`  – stage everything and commit.
* `git push`  – push to the `main` branch on GitHub.

## 2. Systemd service (call queue)
* `systemctl status call_queue_runner`  – check daemon health.
* `sudo systemctl restart call_queue_runner`  – reload code & restart.

## 3. Asterisk CLI helpers
* `sudo asterisk -rx "core show channels concise"`  – check if the modem channel is busy.
* `sudo asterisk -rx "database showkey CallFile/<ID>"`  – inspect variables stored for a call.
* `tail -f /var/log/asterisk/full | grep SendDTMF`  – live-monitor DTMF being sent.

## 4. Call-file & queue directories
* `ls -l /var/spool/asterisk/outgoing`  – currently pending `.call` files.
* `ls -l call_queue/`  – files waiting because the modem was busy.

## 5. PHP helper scripts
| Script | Purpose | Example |
|--------|---------|---------|
| `process_v2.php` | Generate a `.call` file (CLI or web) | `php process_v2.php --phone=08012340000 --id=1234 --notification=01099998888 --auto` |
| `sms_auto_processor.php` | Called from dial-plan for every incoming SMS | manual test:<br>`ENC=$(echo -n "수신거부 080… 식별번호 1234" | base64)`<br>`php sms_auto_processor.php --caller=01011112222 --msg_base64="$ENC"` |
| `call_queue_runner.php` | Moves queued calls when modem is idle | `php call_queue_runner.php --loop` |
| `cleanup_backups.sh` | Remove old `*.backup.*` files | `bash cleanup_backups.sh 3` |
| `retry_call.php` | UI AJAX endpoint to re-dial | *(POSTed by front-end)* |

## 6. SQLite quick checks
* Show last 5 incoming SMS records
  ```bash
  sqlite3 notifications.db "SELECT * FROM sms_incoming ORDER BY id DESC LIMIT 5;"
  ```
* Schema is auto-created by `sms_auto_processor.php` if missing.

## 7. GitHub Codespace / server housekeeping
* `sudo apt-get install --yes sqlite3`  – install CLI SQLite tool (PHP uses extension already).
* `sudo chown -R asterisk:asterisk tmp_calls/ call_queue/ pattern_discovery/`  – fix perms.

---
## 8. Quectel EC25 – useful AT commands
Open a terminal session:  
`minicom -D /dev/ttyUSB2 -b 115200`  (or use `screen /dev/ttyUSB2 115200`)

| Command | Meaning |
|---------|---------|
| `AT` | Basic ping – expect `OK`. |
| `ATE0` | Turn off command echo. |
| `AT+CMGF=1` | SMS text mode (needed for reading). |
| `AT+CSCS="UCS2"` | Unicode charset if messages are Korean. |
| `AT+CMGL="ALL"` | List **all** stored SMS. |
| `AT+CMGD=INDEX` | Delete SMS at slot `INDEX`. |
| `AT+QMGDA="DEL READ"` | Bulk-delete all *read* SMS (used in dial-plan). |
| `AT+QENG?` | Quick network info. |
| `AT+CPAS` | Query device activity (`0=ready`, `4=call in progress`). |

### One-shot fetch & clear stored SMS
```bash
# Detach Asterisk first if needed
sudo asterisk -rx "module unload chan_quectel.so"

# Read & delete
minicom -D /dev/ttyUSB2 <<'EOF'
AT+CMGF=1
AT+CMGL="ALL"
AT+QMGDA="DEL READ"
EOF

# Re-enable in Asterisk
sudo asterisk -rx "module load chan_quectel.so"
```

---
## 9. Log directories
```
logs/                    – PHP debug logs
/var/log/asterisk/full   – Asterisk main log
/var/log/asterisk/call_progress/*.log  – per-call progress
pattern_discovery/       – JSON results of automatic pattern detection
analysis_logs/           – STT / pattern analysis progress
```

## 10. Misc snippets
* Duplicate-SMS lock file lives in `/tmp/smslock_<080>_<ID>` (auto-expires in 5 min).
* Force reload front-end resources after CSS tweak: **Shift+Cmd+R** (Mac) or **Ctrl+F5**.

> Keep this cheat-sheet under version control (`docs/commands_cheatsheet.md`) and extend as new recurring commands emerge. 