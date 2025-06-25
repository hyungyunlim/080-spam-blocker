/**
 * 로그인 후 대기 중인 폼 데이터 처리
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('Pending form processor loaded. Logged in:', window.IS_LOGGED);
    
    // 로그인된 사용자만 처리
    if (window.IS_LOGGED) {
        // 세션 스토리지에서 대기 중인 폼 데이터 확인
        const pendingData = sessionStorage.getItem('pending_spam_form');
        console.log('Pending data found:', pendingData ? 'YES' : 'NO');
        if (pendingData) {
            try {
                const formData = JSON.parse(pendingData);
                
                // 데이터가 너무 오래된 경우 무시 (24시간)
                const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                if (Date.now() - formData.timestamp > maxAge) {
                    sessionStorage.removeItem('pending_spam_form');
                    return;
                }
                
                console.log('Processing pending form data:', formData);
                
                // 폼에 데이터 복원
                const spamContentEl = document.getElementById('spamContent');
                const phoneNumberEl = document.getElementById('phoneNumber');
                const notificationPhoneEl = document.getElementById('notificationPhone');
                
                if (spamContentEl && formData.spam_content) {
                    spamContentEl.value = formData.spam_content;
                }
                if (phoneNumberEl && formData.phone_number) {
                    phoneNumberEl.value = formData.phone_number;
                }
                if (notificationPhoneEl && formData.notification_phone) {
                    notificationPhoneEl.value = formData.notification_phone;
                }
                
                // 폼 자동 제출 (사용자 확인 후)
                if (formData.spam_content && formData.notification_phone) {
                    const confirmMessage = `로그인 전에 입력하신 스팸 문자를 자동으로 처리하시겠습니까?\n\n` +
                                         `내용: ${formData.spam_content.substring(0, 100)}${formData.spam_content.length > 100 ? '...' : ''}\n` +
                                         `알림번호: ${formData.notification_phone}`;
                    
                    if (confirm(confirmMessage)) {
                        // 백그라운드에서 폼 제출
                        submitPendingForm(formData);
                    }
                }
                
                // 처리 완료 후 세션 스토리지에서 제거
                sessionStorage.removeItem('pending_spam_form');
                
            } catch (e) {
                console.error('Error processing pending form data:', e);
                sessionStorage.removeItem('pending_spam_form');
            }
        }
    }
});

/**
 * 대기 중인 폼 데이터를 백그라운드에서 제출
 */
function submitPendingForm(formData) {
    const form = new FormData();
    form.append('spam_content', formData.spam_content);
    form.append('notification_phone', formData.notification_phone);
    if (formData.phone_number) {
        form.append('phone_number', formData.phone_number);
    }
    
    // 로딩 표시
    showProcessingMessage('로그인 전 입력하신 스팸 문자를 처리 중입니다...');
    
    fetch('process_v2.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(result => {
        console.log('Pending form processed:', result);
        showSuccessMessage('스팸 문자 처리가 완료되었습니다!');
        
        // 페이지 새로고침하여 최신 상태 반영
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Error processing pending form:', error);
        showErrorMessage('처리 중 오류가 발생했습니다. 다시 시도해주세요.');
    });
}

/**
 * 메시지 표시 함수들
 */
function showProcessingMessage(message) {
    showToast(message, 'info');
}

function showSuccessMessage(message) {
    showToast(message, 'success');
}

function showErrorMessage(message) {
    showToast(message, 'error');
}

function showToast(message, type = 'info') {
    // 기존 토스트가 있으면 제거
    const existingToast = document.getElementById('pendingFormToast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // 새 토스트 생성
    const toast = document.createElement('div');
    toast.id = 'pendingFormToast';
    toast.className = `toast-notification ${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        max-width: 400px;
        word-wrap: break-word;
        transition: all 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // 자동 제거
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}