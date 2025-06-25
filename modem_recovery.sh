#!/bin/bash

# Modem Auto Recovery Script for Raspberry Pi
# This script checks modem status and performs recovery if needed

LOG_FILE="/var/log/modem_recovery.log"
MAX_RETRIES=3
RETRY_DELAY=10

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

check_modem_status() {
    local status=$(asterisk -rx 'quectel show devices' 2>/dev/null | grep quectel0 | awk '{print $3}')
    echo "$status"
}

reload_quectel() {
    log_message "Reloading Quectel module..."
    asterisk -rx 'quectel reload now' >/dev/null 2>&1
    sleep 5
}

restart_asterisk() {
    log_message "Restarting Asterisk service..."
    systemctl restart asterisk
    sleep 15
}

check_usb_devices() {
    local quectel_count=$(lsusb | grep -c "2c7c:0125")
    echo "$quectel_count"
}

main() {
    log_message "Starting modem recovery check..."
    
    # Check if USB device is present
    usb_count=$(check_usb_devices)
    if [ "$usb_count" -eq 0 ]; then
        log_message "ERROR: Quectel USB device not found"
        exit 1
    fi
    
    # Check if ttyUSB devices exist
    if [ ! -e "/dev/ttyUSB3" ] || [ ! -e "/dev/ttyUSB1" ]; then
        log_message "ERROR: Required ttyUSB devices not found"
        exit 1
    fi
    
    retry_count=0
    while [ $retry_count -lt $MAX_RETRIES ]; do
        modem_status=$(check_modem_status)
        log_message "Modem status: $modem_status"
        
        case "$modem_status" in
            "Free"|"Busy")
                log_message "Modem is working properly (Status: $modem_status)"
                exit 0
                ;;
            "Not")
                log_message "Modem not connected, attempting recovery (Attempt $((retry_count + 1))/$MAX_RETRIES)"
                reload_quectel
                ;;
            "")
                log_message "Unable to get modem status, restarting Asterisk (Attempt $((retry_count + 1))/$MAX_RETRIES)"
                restart_asterisk
                ;;
            *)
                log_message "Unknown modem status: $modem_status (Attempt $((retry_count + 1))/$MAX_RETRIES)"
                reload_quectel
                ;;
        esac
        
        retry_count=$((retry_count + 1))
        if [ $retry_count -lt $MAX_RETRIES ]; then
            log_message "Waiting $RETRY_DELAY seconds before next attempt..."
            sleep $RETRY_DELAY
        fi
    done
    
    log_message "ERROR: Failed to recover modem after $MAX_RETRIES attempts"
    exit 1
}

main "$@"