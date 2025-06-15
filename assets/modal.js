/**
 * 모던 모달 라이브러리
 * 브라우저 기본 alert, confirm, prompt를 대체하는 커스텀 모달
 */

class ModernModal {
    constructor() {
        this.overlay = null;
        this.container = null;
        this.isOpen = false;
        this.currentResolve = null;
        this.currentReject = null;
        
        // ESC 키 이벤트 바인딩
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close(false);
            }
        });
    }

    /**
     * 모달 DOM 생성
     */
    createModal() {
        if (this.overlay) {
            this.overlay.remove();
        }

        this.overlay = document.createElement('div');
        this.overlay.className = 'modal-overlay';
        
        this.container = document.createElement('div');
        this.container.className = 'modal-container';
        
        this.overlay.appendChild(this.container);
        document.body.appendChild(this.overlay);

        // 오버레이 클릭으로 닫기
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close(false);
            }
        });
    }

    /**
     * 모달 표시
     */
    show() {
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
        
        // 애니메이션을 위한 지연
        requestAnimationFrame(() => {
            this.overlay.classList.add('show');
        });
    }

    /**
     * 모달 닫기
     */
    close(result = null) {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        document.body.style.overflow = '';
        this.overlay.classList.remove('show');

        setTimeout(() => {
            if (this.overlay) {
                this.overlay.remove();
                this.overlay = null;
                this.container = null;
            }
        }, 300);

        if (this.currentResolve) {
            this.currentResolve(result);
            this.currentResolve = null;
            this.currentReject = null;
        }
    }

    /**
     * Alert 대체
     */
    alert(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        const config = {
            title: '알림',
            message: '',
            type: 'info',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>',
            confirmText: '확인',
            ...options
        };

        return new Promise((resolve) => {
            this.currentResolve = resolve;
            this.createModal();

            this.container.innerHTML = `
                <div class="modal-header modal-type-${config.type}">
                    <h3 class="modal-title">
                        <span class="modal-icon">${config.icon}</span>
                        ${config.title}
                    </h3>
                    <button class="modal-close" onclick="modernModal.close(true)">×</button>
                </div>
                <div class="modal-body">
                    <div class="modal-message">${config.message}</div>
                </div>
                <div class="modal-actions center">
                    <button class="modal-btn modal-btn-primary" onclick="modernModal.close(true)">
                        ${config.confirmText}
                    </button>
                </div>
            `;

            this.show();
        });
    }

    /**
     * Confirm 대체
     */
    confirm(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        const config = {
            title: '확인',
            message: '',
            type: 'confirm',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z"/></svg>',
            confirmText: '확인',
            cancelText: '취소',
            dangerConfirm: false,
            ...options
        };

        return new Promise((resolve) => {
            this.currentResolve = resolve;
            this.createModal();

            const confirmClass = config.dangerConfirm ? 'modal-btn-danger' : 'modal-btn-primary';

            this.container.innerHTML = `
                <div class="modal-header modal-type-${config.type}">
                    <h3 class="modal-title">
                        <span class="modal-icon">${config.icon}</span>
                        ${config.title}
                    </h3>
                    <button class="modal-close" onclick="modernModal.close(false)">×</button>
                </div>
                <div class="modal-body">
                    <div class="modal-message">${config.message}</div>
                </div>
                <div class="modal-actions">
                    <button class="modal-btn modal-btn-secondary" onclick="modernModal.close(false)">
                        ${config.cancelText}
                    </button>
                    <button class="modal-btn ${confirmClass}" onclick="modernModal.close(true)">
                        ${config.confirmText}
                    </button>
                </div>
            `;

            this.show();
        });
    }

    /**
     * 삭제 확인 전용
     */
    confirmDelete(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        const config = {
            title: '삭제 확인',
            message: '정말로 삭제하시겠습니까?',
            type: 'error',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>',
            confirmText: '삭제',
            cancelText: '취소',
            ...options
        };

        return this.confirm({
            ...config,
            dangerConfirm: true
        });
    }

    /**
     * 성공 메시지
     */
    success(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        return this.alert({
            title: '성공',
            type: 'success',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>',
            ...options
        });
    }

    /**
     * 에러 메시지
     */
    error(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        return this.alert({
            title: '오류',
            type: 'error',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>',
            ...options
        });
    }

    /**
     * 경고 메시지
     */
    warning(options) {
        if (typeof options === 'string') {
            options = { message: options };
        }

        return this.alert({
            title: '경고',
            type: 'confirm',
            icon: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>',
            ...options
        });
    }

    /**
     * 로딩 모달
     */
    loading(message = '처리 중...') {
        this.createModal();

        this.container.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">
                    <span class="modal-icon"><svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg></span>
                    처리 중
                </h3>
            </div>
            <div class="modal-body">
                <div class="modal-loading">
                    <div class="modal-spinner"></div>
                    <span>${message}</span>
                </div>
            </div>
        `;

        this.show();
        return this;
    }

    /**
     * 커스텀 모달
     */
    custom(options) {
        const config = {
            title: '',
            content: '',
            type: 'info',
            actions: [],
            closable: true,
            ...options
        };

        return new Promise((resolve) => {
            this.currentResolve = resolve;
            this.createModal();

            const closeButton = config.closable ? 
                `<button class="modal-close" onclick="modernModal.close(null)">×</button>` : '';

            const actionsHtml = config.actions.length > 0 ? `
                <div class="modal-actions">
                    ${config.actions.map((action, index) => `
                        <button class="modal-btn modal-btn-${action.type || 'secondary'}" 
                                onclick="modernModal.close(${index})">
                            ${action.text}
                        </button>
                    `).join('')}
                </div>
            ` : '';

            this.container.innerHTML = `
                <div class="modal-header modal-type-${config.type}">
                    <h3 class="modal-title">${config.title}</h3>
                    ${closeButton}
                </div>
                <div class="modal-body">
                    ${config.content}
                </div>
                ${actionsHtml}
            `;

            this.show();
        });
    }
}

// 전역 인스턴스 생성
const modernModal = new ModernModal();

// 기존 함수들을 모던 모달로 대체하는 헬퍼 함수들
window.modernAlert = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.alert(message);
    }
    return modernModal.alert({ message, ...options });
};

window.modernConfirm = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.confirm(message);
    }
    return modernModal.confirm({ message, ...options });
};

window.modernSuccess = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.success(message);
    }
    return modernModal.success({ message, ...options });
};

window.modernError = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.error(message);
    }
    return modernModal.error({ message, ...options });
};

window.modernWarning = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.warning(message);
    }
    return modernModal.warning({ message, ...options });
};

window.modernConfirmDelete = (message, options = {}) => {
    if (typeof message === 'object') {
        return modernModal.confirmDelete(message);
    }
    return modernModal.confirmDelete({ message, ...options });
};

window.modernLoading = (message) => {
    return modernModal.loading(message);
};

// 텍스트 입력이 포함된 확인 모달
window.showCustomConfirm = (title, message, confirmText, callback, includeInput = false) => {
    const modal = modernModal;
    modal.createModal();
    
    const inputField = includeInput ? `
        <div class="modal-input-group">
            <input type="text" id="confirm-input" class="modal-input" placeholder="DELETE_MY_ACCOUNT" autocomplete="off">
        </div>
    ` : '';
    
    modal.container.innerHTML = `
        <div class="modal-header modal-type-confirm">
            <h3 class="modal-title">
                <span class="modal-icon">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                </span>
                ${title}
            </h3>
            <button class="modal-close" onclick="modernModal.close(false)">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-message">${message.replace(/\n/g, '<br>')}</div>
            ${inputField}
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-secondary" onclick="modernModal.close(false)">
                취소
            </button>
            <button class="modal-btn modal-btn-danger" onclick="if(typeof arguments[0] === 'function') arguments[0](); else (${callback.toString()})()">
                ${confirmText}
            </button>
        </div>
    `;
    
    modal.show();
    
    // 입력 필드가 있으면 포커스
    if (includeInput) {
        setTimeout(() => {
            const input = document.getElementById('confirm-input');
            if (input) input.focus();
        }, 100);
    }
    
    // 확인 버튼 클릭 이벤트 수정
    const confirmBtn = modal.container.querySelector('.modal-btn-danger');
    confirmBtn.onclick = callback;
};

// 커스텀 알림 (콜백 포함)
window.showCustomAlert = (title, message, type = 'info', callback = null) => {
    const modal = modernModal;
    
    const icons = {
        info: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>',
        success: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>',
        error: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>',
        warning: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
    };
    
    modal.createModal();
    
    modal.container.innerHTML = `
        <div class="modal-header modal-type-${type}">
            <h3 class="modal-title">
                <span class="modal-icon">${icons[type] || icons.info}</span>
                ${title}
            </h3>
            <button class="modal-close" onclick="modernModal.close(false)">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-message">${message.replace(/\n/g, '<br>')}</div>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-primary" onclick="modernModal.close(true); ${callback ? `(${callback.toString()})()` : ''}">
                확인
            </button>
        </div>
    `;
    
    modal.show();
    
    // 확인 버튼 클릭 이벤트 수정
    if (callback) {
        const confirmBtn = modal.container.querySelector('.modal-btn-primary');
        confirmBtn.onclick = () => {
            modal.close(true);
            callback();
        };
    }
};

// 편의 함수 - 기존 alert/confirm을 자동으로 대체하려면 주석 해제
/*
window.alert = window.modernAlert;
window.confirm = window.modernConfirm;
*/

// 모듈 내보내기 (ES6 모듈 환경에서 사용 시)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModernModal;
}