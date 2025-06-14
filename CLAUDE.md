# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## System Overview

This is a sophisticated **080 SMS spam auto-unsubscriber system** for Korean markets that automatically processes incoming spam SMS messages and makes unsubscribe calls using pattern-based automation. The system integrates with Asterisk PBX and uses a Quectel EC25 modem for SMS/voice operations.

## Common Development Commands

### Build & Testing
```bash
# No traditional build process - this is a PHP application
# Check PHP syntax across all files
find . -name "*.php" -exec php -l {} \;

# Test SMS processing manually
ENC=$(echo -n "수신거부 080… 식별번호 1234" | base64)
php sms_auto_processor.php --caller=01011112222 --msg_base64="$ENC"

# Test call generation
php process_v2.php --phone=08012340000 --id=1234 --notification=01099998888 --auto
```

### Service Management
```bash
# Check daemon health
systemctl status call_queue_runner

# Restart call queue daemon after code changes
sudo systemctl restart call_queue_runner

# Monitor live DTMF transmission
tail -f /var/log/asterisk/full | grep SendDTMF
```

### Database Operations
```bash
# Check recent SMS records
sqlite3 notifications.db "SELECT * FROM sms_incoming ORDER BY id DESC LIMIT 5;"

# Inspect stored call variables
sudo asterisk -rx "database showkey CallFile/<ID>"
```

## Core Architecture

### SMS Processing Flow
```
SMS → Asterisk → sms_auto_processor.php → process_v2.php → Call generation
```

### Key Entry Points
- **`index.php`** - Main web interface for manual processing
- **`sms_auto_processor.php`** - Automatic processor called by Asterisk dialplan
- **`process_v2.php`** - Core processing engine (CLI/web modes)
- **`dashboard.php`** - User dashboard for call history

### Database Schema
- **`users`** - Phone-based user accounts with SMS verification
- **`verification_codes`** - 6-digit codes with 10-minute expiry
- **`sms_incoming`** - Raw SMS processing records
- **`unsubscribe_calls`** - Outgoing call attempts with success analysis

### Pattern Management
- **`patterns.json`** - DTMF patterns for different 080 numbers
- **Variables**: `{ID}` (identification), `{Phone}`, `{Notify}`
- **Auto-discovery** for unknown numbers with confidence scoring

### Authentication System
Flow: `login.php` → SMS verification → `verify.php` → dashboard access
- Session-based with phone number verification
- API endpoints: `/api/send_code.php`, `/api/verify_code.php`

## Key Processing Logic

### ID Priority (automatic SMS route)
1. SMS content `식별번호` keyword extraction
2. Manual web input
3. Caller ID from dialplan

### Duplicate Prevention
- SQLite database check (24-hour window)
- Lock files `/tmp/smslock_*` (5-minute window)
- Call progress log search

### Call Queue Management
- **Immediate**: When modem idle → `/var/spool/asterisk/outgoing/`
- **Queued**: When modem busy → `call_queue/`
- **TTL Policies**: 1-hour queue limit, 30-minute outgoing limit

## Background Services

### Systemd Services
- **`call_queue_runner.service`** - Manages call queue, checks every 5 seconds
- **`sms_fetcher.service`** - SMS polling and cleanup

### Success Detection
- **`success_checker.php`** - Whisper STT analysis of call recordings
- **`sms_sender.php`** - Multipart SMS notifications (300-byte limit)

## File Structure

### Configuration
- **`patterns.json`** - Call patterns and DTMF sequences
- **`spam.db`** - Main SQLite database
- **`notifications.db`** - SMS notification history

### Processing Scripts
- **`call_queue_runner.php`** - Daemon for queue management
- **`success_checker.php`** - STT-based success analysis
- **`PatternManager.php`** - Pattern CRUD interface

### Logs & Monitoring
- `/var/log/asterisk/sms.txt` - Raw SMS monitoring
- `/var/log/asterisk/call_progress/` - Per-call progress logs
- `logs/process_v2_debug.log` - Detailed processing logs
- `analysis_logs/` - STT analysis progress

## Development Notes

### Asterisk Integration
- Uses `chan_quectel` for SMS/voice via Quectel EC25 modem
- Custom dialplan extensions for SMS processing and call handling
- AstDB for storing call variables and state

### Security Features
- Phone-based authentication with SMS verification
- Session management with user isolation
- Input sanitization and SQL injection prevention

### Advanced Features
- Pattern discovery for unknown 080 numbers
- Multipart SMS handling for long notifications
- Real-time call progress updates via AJAX
- Dark mode responsive UI