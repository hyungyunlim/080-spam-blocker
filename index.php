<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>080 수신거부 자동화 시스템</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: none;
            overflow: hidden;
            min-height: 120px;
            max-height: 400px;
        }

        .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 14px;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.4);
        }

        .result-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            white-space: pre-wrap;
            min-height: 60px;
        }

        /* Dynamic Input Container */
        .dynamic-input-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
            transform: translateY(-10px);
            opacity: 0;
        }

        .dynamic-input-container.show {
            transform: translateY(0);
            opacity: 1;
        }

        .detected-info {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ID Selection UI */
        .id-selection-container {
            background: #fff3cd;
            color: #856404;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #ffeaa7;
        }

        .id-selection-header {
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .id-options {
            margin-bottom: 12px;
        }

        .id-option {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .id-option:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }

        .id-option input[type="radio"] {
            margin-right: 8px;
        }

        .id-option-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .id-custom-input {
            flex: 1;
            min-width: 200px;
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
        }

        .confirmation-container {
            background: #e7f3ff;
            border: 1px solid #b8d4ff;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            display: none;
        }

        .confirmation-container.show {
            display: block;
        }

        .confirmation-text {
            margin-bottom: 12px;
            font-weight: 600;
            color: #0c5460;
        }

        .confirmation-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-confirm:hover {
            background: #218838;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        /* Recording Grid */
        .recordings-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .recording-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .recording-item:hover {
            background: #f1f3f4;
            border-color: #667eea;
        }

        .recording-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .recording-name {
            font-weight: 600;
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recording-name:hover {
            color: #667eea;
        }

        .recording-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .recording-title {
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recording-datetime {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .call-icon, .date-icon {
            font-size: 14px;
        }

        /* Analysis Results */
        .analysis-result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
        }

        .result-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .result-failure {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .result-uncertain {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .result-unknown {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        /* Progress Bar */
        .progress-container {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            display: none;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            background: #28a745;
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-info {
            font-size: 14px;
            color: #495057;
        }

        .progress-stage {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .progress-details {
            font-size: 12px;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .recording-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .recording-title {
                font-size: 14px;
            }
            
            .recording-datetime {
                font-size: 12px;
            }

            .id-option-custom {
                flex-direction: column;
                align-items: flex-start;
            }

            .id-custom-input {
                width: 100%;
                min-width: auto;
            }
        }

        /* Fade-in Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚫 080 수신거부 자동화 시스템</h1>
            <p>스팸 문자의 080 번호를 자동으로 추출하여 수신거부 전화를 대신 걸어드립니다</p>
        </div>

        <!-- 메인 입력 카드 -->
        <div class="card">
            <div class="card-header">
                📱 스팸 문자 내용 입력
            </div>
            <div class="card-body">
                <form id="spamForm" method="post" action="process_v2.php">
                    <div class="form-group">
                        <label for="spamContent">스팸 문자 내용</label>
                        <textarea id="spamContent" name="spam_content" required placeholder="받은 스팸 문자 내용을 여기에 붙여넣으세요..."></textarea>
                        <div class="help-text">💡 광고문자에서 "080"으로 시작하는 수신거부 번호를 자동으로 찾아 전화를 걸어드립니다</div>
                    </div>

                    <!-- 동적 입력 컨테이너 -->
                    <div id="dynamicInputContainer" class="dynamic-input-container">
                        <!-- 식별번호가 하나만 감지된 경우 -->
                        <div id="detectedIdSection" style="display: none;">
                            <div class="detected-info">
                                ✅ <span id="detectedIdText">식별번호가 감지되었습니다</span>
                            </div>
                        </div>

                        <!-- 식별번호가 여러개 감지된 경우 -->
                        <div id="multipleIdSection" style="display: none;">
                            <div class="id-selection-container">
                                <div class="id-selection-header">
                                    ⚠️ 여러 개의 식별번호가 발견되었습니다. 올바른 것을 선택해주세요:
                                </div>
                                <div id="idOptions" class="id-options">
                                    <!-- 동적으로 생성됨 -->
                                </div>
                                <div class="id-option-custom">
                                    <label>
                                        <input type="radio" id="customId" name="selectedId" value="custom">
                                        직접 입력:
                                    </label>
                                    <input type="text" id="customIdInput" class="id-custom-input" placeholder="식별번호를 직접 입력하세요">
                                </div>
                            </div>
                            
                            <!-- 확인 컨테이너 -->
                            <div id="confirmationContainer" class="confirmation-container">
                                <div class="confirmation-text">
                                    선택한 식별번호: <strong id="selectedIdDisplay"></strong>
                                </div>
                                <div class="confirmation-buttons">
                                    <button type="button" id="confirmSelection" class="btn btn-small btn-confirm">확인</button>
                                    <button type="button" id="cancelSelection" class="btn btn-small btn-cancel">다시 선택</button>
                                </div>
                            </div>
                        </div>

                        <!-- 전화번호 입력이 필요한 경우 -->
                        <div id="phoneInputSection" style="display: none;">
                            <div class="form-group">
                                <label for="phoneNumber">전화번호 입력 (선택사항)</label>
                                <input type="tel" id="phoneNumber" name="phone_number" placeholder="예: 01012345678">
                                <div class="help-text">📞 일부 080 시스템에서 본인 전화번호가 필요한 경우 입력해주세요</div>
                            </div>
                        </div>
                    </div>

                    <!-- 알림 연락처 입력 (필수) -->
                    <div class="form-group">
                        <label for="notificationPhone">알림 받을 연락처 (필수) *</label>
                        <input type="tel" id="notificationPhone" name="notification_phone" required placeholder="예: 01012345678">
                        <div class="help-text">📱 처리 완료 후 결과를 알림 문자로 받을 연락처를 입력해주세요</div>
                    </div>

                    <button type="submit" class="btn">
                        📞 수신거부 전화 걸기
                    </button>
                </form>

                <!-- 결과 표시 영역 -->
                <div id="resultArea" class="result-box" style="display: none;"></div>
            </div>
        </div>

        <!-- 녹음 파일 목록 카드 -->
        <div class="card">
            <div class="card-header">
                🎙️ 녹음 파일 목록
                <button id="refreshBtn" class="btn btn-small btn-secondary" style="float: right;">
                    🔄 새로고침
                </button>
            </div>
            <div class="card-body">
                <div id="recordingsList" class="recordings-grid">
                    <div style="text-align: center; padding: 40px; color: #666;">
                        🎵 녹음 파일을 불러오는 중...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 식별번호 패턴 (숫자 5-8자리)
        const ID_PATTERNS = [
            /\b(\d{5,8})\b/g,
            /수신거부\s*(\d{5,8})/g,
            /해지\s*(\d{5,8})/g,
            /탈퇴\s*(\d{5,8})/g
        ];

        // 080 번호 패턴
        const PHONE_080_PATTERN = /080[0-9]{7,8}/g;

        document.addEventListener('DOMContentLoaded', function() {
            const spamContent = document.getElementById('spamContent');
            const dynamicContainer = document.getElementById('dynamicInputContainer');
            const detectedIdSection = document.getElementById('detectedIdSection');
            const multipleIdSection = document.getElementById('multipleIdSection');
            const phoneInputSection = document.getElementById('phoneInputSection');
            const detectedIdText = document.getElementById('detectedIdText');
            const idOptions = document.getElementById('idOptions');
            const confirmationContainer = document.getElementById('confirmationContainer');
            const selectedIdDisplay = document.getElementById('selectedIdDisplay');
            const confirmButton = document.getElementById('confirmSelection');
            const cancelButton = document.getElementById('cancelSelection');
            
            let selectedIds = [];
            let confirmedId = null;
            
            // 텍스트영역 자동 크기 조절
            function autoResize(textarea) {
                if (!textarea) return;
                
                // 최소 높이 설정
                const minHeight = 120;
                const maxHeight = 400;
                
                // 높이 초기화
                textarea.style.height = minHeight + 'px';
                
                // 스크롤 높이 기반으로 조정
                const scrollHeight = textarea.scrollHeight;
                const newHeight = Math.max(minHeight, Math.min(scrollHeight, maxHeight));
                
                textarea.style.height = newHeight + 'px';
                
                // 스크롤 표시 여부 결정
                if (scrollHeight > maxHeight) {
                    textarea.style.overflowY = 'scroll';
                } else {
                    textarea.style.overflowY = 'hidden';
                }
            }
            
            // 초기 텍스트영역 설정
            function initializeTextarea() {
                try {
                    console.log('initializeTextarea 시작');
                    
                    const spamContent = document.getElementById('spamContent');
                    if (spamContent) {
                        console.log('spamContent 요소 찾음');
                        
                        // 초기 크기 설정
                        if (typeof autoResize === 'function') {
                            autoResize(spamContent);
                        }
                        
                        // 기존 내용이 있으면 분석
                        const existingText = spamContent.value.trim();
                        if (existingText.length > 10) {
                            if (typeof analyzeText === 'function') {
                                analyzeText(existingText);
                            }
                        }
                        
                        console.log('initializeTextarea 완료');
                    } else {
                        console.warn('spamContent 요소를 찾을 수 없습니다');
                    }
                } catch (error) {
                    console.error('initializeTextarea 에러:', error);
                }
            }
            
            // 텍스트 입력 시 실시간 분석 및 자동 크기 조절
            if (spamContent) {
                // 모든 입력 이벤트에 리스너 추가
                spamContent.addEventListener('input', function() {
                    const text = this.value.trim();
                    
                    // 자동 크기 조절
                    autoResize(this);
                    
                    if (text.length > 10) {
                        analyzeText(text);
                    } else {
                        hideDynamicInput();
                    }
                });
                
                // 추가 이벤트들 (붙여넣기, 잘라내기 등)
                spamContent.addEventListener('paste', function() {
                    setTimeout(() => {
                        autoResize(this);
                        const text = this.value.trim();
                        if (text.length > 10) {
                            analyzeText(text);
                        }
                    }, 10);
                });
                
                spamContent.addEventListener('cut', function() {
                    setTimeout(() => {
                        autoResize(this);
                        const text = this.value.trim();
                        if (text.length > 10) {
                            analyzeText(text);
                        } else {
                            hideDynamicInput();
                        }
                    }, 10);
                });
                
                // 포커스/블러 이벤트
                spamContent.addEventListener('focus', function() {
                    autoResize(this);
                });
                
                spamContent.addEventListener('blur', function() {
                    autoResize(this);
                });
            }

            function analyzeText(text) {
                // 080 번호 찾기
                const phoneNumbers = text.match(PHONE_080_PATTERN) || [];
                
                if (phoneNumbers.length === 0) {
                    hideDynamicInput();
                    return;
                }

                // 식별번호 찾기 (더 정확한 패턴 적용)
                let foundIds = [];
                
                // 1. 명시적인 수신거부 패턴 우선 검색
                const explicitPatterns = [
                    /수신거부\s*:?\s*(\d{5,8})/gi,
                    /해지\s*:?\s*(\d{5,8})/gi,
                    /탈퇴\s*:?\s*(\d{5,8})/gi,
                    /식별번호\s*:?\s*(\d{5,8})/gi
                ];
                
                explicitPatterns.forEach(pattern => {
                    const matches = [...text.matchAll(pattern)];
                    matches.forEach(match => {
                        if (match[1] && !foundIds.includes(match[1])) {
                            foundIds.push(match[1]);
                        }
                    });
                });
                
                // 2. 명시적인 패턴이 없으면 괄호 안의 숫자 찾기
                if (foundIds.length === 0) {
                    const bracketPattern = /\(.*?(\d{5,8}).*?\)/g;
                    const matches = [...text.matchAll(bracketPattern)];
                    matches.forEach(match => {
                        if (match[1] && !foundIds.includes(match[1])) {
                            foundIds.push(match[1]);
                        }
                    });
                }
                
                // 3. 여전히 없으면 일반적인 숫자 패턴 (하지만 전화번호와 겹치지 않도록)
                if (foundIds.length === 0) {
                    const generalPattern = /\b(\d{5,8})\b/g;
                    const matches = [...text.matchAll(generalPattern)];
                    matches.forEach(match => {
                        // 080 번호와 겹치지 않는지 확인
                        if (match[1] && !foundIds.includes(match[1]) && 
                            !phoneNumbers.some(phone => phone.includes(match[1]))) {
                            foundIds.push(match[1]);
                        }
                    });
                }

                selectedIds = foundIds;
                showDynamicInput();

                if (foundIds.length === 1) {
                    // 식별번호가 하나만 발견된 경우
                    detectedIdText.textContent = `식별번호 발견: ${foundIds[0]} (080번호: ${phoneNumbers.join(', ')})`;
                    detectedIdSection.style.display = 'block';
                    multipleIdSection.style.display = 'none';
                    phoneInputSection.style.display = 'none';
                    confirmedId = foundIds[0];
                } else if (foundIds.length > 1) {
                    // 식별번호가 여러개 발견된 경우
                    showMultipleIdSelection(foundIds, phoneNumbers);
                } else {
                    // 식별번호가 없는 경우
                    detectedIdSection.style.display = 'none';
                    multipleIdSection.style.display = 'none';
                    phoneInputSection.style.display = 'block';
                    confirmedId = null;
                }
            }

            function showMultipleIdSelection(foundIds, phoneNumbers) {
                detectedIdSection.style.display = 'none';
                phoneInputSection.style.display = 'none';
                multipleIdSection.style.display = 'block';
                confirmationContainer.classList.remove('show');
                confirmedId = null;
                
                // 옵션 생성
                idOptions.innerHTML = '';
                foundIds.forEach((id, index) => {
                    const option = document.createElement('div');
                    option.className = 'id-option';
                    option.innerHTML = `
                        <input type="radio" id="id${index}" name="selectedId" value="${id}">
                        <label for="id${index}">${id}</label>
                    `;
                    idOptions.appendChild(option);
                    
                    // 첫 번째 옵션을 기본 선택
                    if (index === 0) {
                        option.querySelector('input').checked = true;
                    }
                });
                
                // 라디오 버튼 변경 이벤트 추가
                const radioButtons = idOptions.querySelectorAll('input[type="radio"]');
                radioButtons.forEach(radio => {
                    radio.addEventListener('change', showConfirmation);
                });
                
                // 커스텀 입력 이벤트 추가
                document.getElementById('customId').addEventListener('change', showConfirmation);
                document.getElementById('customIdInput').addEventListener('input', showConfirmation);
            }

            function showConfirmation() {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (!selectedRadio) return;
                
                let selectedValue = '';
                if (selectedRadio.value === 'custom') {
                    selectedValue = document.getElementById('customIdInput').value.trim();
                    if (!selectedValue) return;
                } else {
                    selectedValue = selectedRadio.value;
                }
                
                selectedIdDisplay.textContent = selectedValue;
                confirmationContainer.classList.add('show');
            }

            // 확인 버튼 이벤트
            confirmButton.addEventListener('click', function() {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (selectedRadio) {
                    if (selectedRadio.value === 'custom') {
                        confirmedId = document.getElementById('customIdInput').value.trim();
                    } else {
                        confirmedId = selectedRadio.value;
                    }
                    
                    // 확인된 ID로 단일 표시로 변경
                    detectedIdText.textContent = `선택된 식별번호: ${confirmedId}`;
                    detectedIdSection.style.display = 'block';
                    multipleIdSection.style.display = 'none';
                }
            });

            // 취소 버튼 이벤트
            cancelButton.addEventListener('click', function() {
                confirmationContainer.classList.remove('show');
                confirmedId = null;
            });

            function showDynamicInput() {
                dynamicContainer.style.display = 'block';
                setTimeout(() => {
                    dynamicContainer.classList.add('show');
                }, 10);
            }

            function hideDynamicInput() {
                dynamicContainer.classList.remove('show');
                setTimeout(() => {
                    dynamicContainer.style.display = 'none';
                    detectedIdSection.style.display = 'none';
                    multipleIdSection.style.display = 'none';
                    phoneInputSection.style.display = 'none';
                    confirmationContainer.classList.remove('show');
                    confirmedId = null;
                }, 300);
            }
            
            // 페이지 로드 시 초기 크기 설정
            autoResize(spamContent);
        });

        // 폼 제출 처리
        document.getElementById('spamForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultArea = document.getElementById('resultArea');
            
            // 확인된 식별번호가 있다면 추가
            const multipleIdSection = document.getElementById('multipleIdSection');
            if (multipleIdSection.style.display !== 'none' && confirmedId) {
                formData.append('selected_id', confirmedId);
            }
            
            // 결과 영역 표시
            resultArea.style.display = 'block';
            resultArea.innerHTML = '처리 중...';
            
            fetch('process_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                resultArea.innerHTML = data;
                resultArea.classList.add('fade-in');
                
                // 성공 시 녹음 목록 새로고침
                setTimeout(() => {
                    loadRecordings();
                }, 2000);
            })
            .catch(error => {
                resultArea.innerHTML = '오류가 발생했습니다: ' + error.message;
            });
        });

        // 파일명에서 정보 추출
        function parseFilename(filename) {
            try {
                console.log('Parsing filename:', filename);
                
                // 예: 20250609-235131-FROM_SYSTEM-TO_0800121900.wav
                const match = filename.match(/(\d{8})-(\d{6})-FROM_(.+?)-TO_(.+?)\.wav$/);
                
                if (match) {
                    const [, date, time, from, to] = match;
                    console.log('Matched parts:', { date, time, from, to });
                    
                    // 날짜 파싱 (YYYYMMDD)
                    const year = date.substr(0, 4);
                    const month = date.substr(4, 2);
                    const day = date.substr(6, 2);
                    
                    // 시간 파싱 (HHMMSS)
                    const hour = time.substr(0, 2);
                    const minute = time.substr(2, 2);
                    const second = time.substr(4, 2);
                    
                    const result = {
                        date: `${year}년 ${parseInt(month)}월 ${parseInt(day)}일`,
                        time: `${hour}:${minute}:${second}`,
                        from: from,
                        to: to,
                        formatted: true
                    };
                    
                    console.log('Parsed result:', result);
                    return result;
                }
                
                console.log('No match found, returning original');
                return {
                    original: filename,
                    formatted: false
                };
            } catch (error) {
                console.error('Error parsing filename:', filename, error);
                return {
                    original: filename,
                    formatted: false
                };
            }
        }

        // 녹음 파일 목록 로드
        function loadRecordings() {
            const recordingsList = document.getElementById('recordingsList');
            
            // 로딩 상태 표시
            recordingsList.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #666;">
                    🔄 녹음 파일을 불러오는 중...
                </div>
            `;
            
            console.log('녹음 파일 목록 로드 시작...');
            
            fetch('get_recordings.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(files => {
                    console.log('받은 파일 목록:', files);
                    
                    if (!Array.isArray(files)) {
                        throw new Error('응답이 배열 형태가 아닙니다: ' + typeof files);
                    }
                    
                    if (files.length === 0) {
                        recordingsList.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #666;">
                                🎵 아직 녹음 파일이 없습니다
                            </div>
                        `;
                        return;
                    }

                    let html = '';
                    files.forEach((file, index) => {
                        console.log(`Processing file ${index + 1}:`, file);
                        
                        const safeId = file.replace(/[^a-zA-Z0-9]/g, '_');
                        
                        // 파일명 파싱 개선
                        const fileInfo = parseFilename(file);
                        console.log('Parsed file info:', fileInfo);
                        
                        let displayHeader;
                        if (fileInfo.formatted) {
                            displayHeader = `
                                <div class="recording-info">
                                    <div class="recording-title">
                                        <span class="call-icon">📞</span>
                                        <strong>${fileInfo.to}</strong>
                                    </div>
                                    <div class="recording-datetime">
                                        <span class="date-icon">📅</span> ${fileInfo.date} ${fileInfo.time}
                                    </div>
                                </div>
                            `;
                        } else {
                            displayHeader = `
                                <div class="recording-info">
                                    <div class="recording-title">
                                        <span class="call-icon">🎵</span> ${fileInfo.original}
                                    </div>
                                </div>
                            `;
                        }
                        
                        html += `
                            <div class="recording-item fade-in">
                                <div class="recording-header">
                                    <a href="player.php?file=${encodeURIComponent(file)}" target="_blank" class="recording-name">
                                        ${displayHeader}
                                    </a>
                                    <button class="btn btn-small" onclick="analyzeRecording('${file}', this)" data-filename="${file}">
                                        🎤 음성분석
                                    </button>
                                </div>
                                <div id="progress-${safeId}" class="progress-container"></div>
                                <div id="analysis-${safeId}" class="analysis-result"></div>
                            </div>
                        `;
                    });
                    
                    console.log('HTML 생성 완료, 항목 수:', files.length);
                    recordingsList.innerHTML = html;
                    
                    // 기존 분석 결과 로드
                    setTimeout(() => {
                        console.log('기존 분석 결과 로드 시작');
                        loadExistingAnalysis();
                    }, 500);
                })
                .catch(error => {
                    console.error('녹음 파일 로드 실패:', error);
                    recordingsList.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            ❌ 녹음 파일을 불러올 수 없습니다<br>
                            <small style="color: #666;">오류: ${error.message}</small>
                        </div>
                    `;
                });
        }

        // 음성 분석 함수
        function analyzeRecording(filename, button) {
            const safeId = filename.replace(/[^a-zA-Z0-9]/g, '_');
            const progressContainer = document.getElementById(`progress-${safeId}`);
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = '⏳ 분석 중...';
            
            progressContainer.style.display = 'block';
            progressContainer.innerHTML = `
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-info">
                    <div class="progress-stage">🔄 분석 시작</div>
                    <div class="progress-message">분석을 준비하고 있습니다...</div>
                </div>
            `;
            
            // 비동기 분석 시작
            fetch('analyze_recording_async.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'filename=' + encodeURIComponent(filename)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 진행 상황 모니터링 시작
                    monitorProgress(data.job_id, safeId, button, originalText);
                } else {
                    showAnalysisError(safeId, button, originalText, data.error || '분석 시작 실패');
                }
            })
            .catch(error => {
                showAnalysisError(safeId, button, originalText, error.message);
            });
        }

        // 진행 상황 모니터링
        function monitorProgress(jobId, safeId, button, originalText) {
            const checkProgress = () => {
                fetch(`analyze_recording_async.php?job_id=${jobId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateProgress(safeId, data);
                            
                            if (data.stage === 'completed') {
                                showAnalysisComplete(safeId, button, originalText);
                            } else if (data.stage === 'error') {
                                showAnalysisError(safeId, button, originalText, data.message);
                            } else {
                                // 계속 모니터링
                                setTimeout(checkProgress, 2000);
                            }
                        } else {
                            showAnalysisError(safeId, button, originalText, '진행 상황 확인 실패');
                        }
                    })
                    .catch(error => {
                        showAnalysisError(safeId, button, originalText, error.message);
                    });
            };
            
            // 첫 번째 체크를 1초 후에 시작
            setTimeout(checkProgress, 1000);
        }

        // 진행 상황 업데이트
        function updateProgress(safeId, progressData) {
            const progressContainer = document.getElementById(`progress-${safeId}`);
            
            const stageTexts = {
                'starting': '🔄 시작 중',
                'file_check': '📁 파일 확인',
                'loading_model': '🤖 모델 로딩',
                'model_loaded': '✅ 모델 준비',
                'transcribing': '🎙️ 음성 변환',
                'transcription_done': '📝 변환 완료',
                'analyzing': '🔍 패턴 분석',
                'saving': '💾 결과 저장',
                'completed': '✅ 완료',
                'error': '❌ 오류'
            };
            
            progressContainer.innerHTML = `
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${progressData.progress}%"></div>
                </div>
                <div class="progress-info">
                    <div class="progress-stage">${stageTexts[progressData.stage] || progressData.stage}</div>
                    <div class="progress-message">${progressData.message}</div>
                    <div class="progress-details">
                        <div>작업 ID: ${progressData.job_id}</div>
                        <div>진행률: ${progressData.progress}%</div>
                    </div>
                </div>
            `;
        }

        // 분석 완료 처리
        function showAnalysisComplete(safeId, button, originalText) {
            const progressContainer = document.getElementById(`progress-${safeId}`);
            
            progressContainer.style.display = 'none';
            button.disabled = false;
            button.textContent = originalText;
            
            // 최종 결과 로드
            const originalFilename = button.getAttribute('data-filename');
            setTimeout(() => {
                loadAnalysisResult(safeId, originalFilename);
            }, 1000);
        }

        // 분석 오류 처리
        function showAnalysisError(safeId, button, originalText, errorMessage) {
            const progressContainer = document.getElementById(`progress-${safeId}`);
            const resultDiv = document.getElementById(`analysis-${safeId}`);
            
            progressContainer.style.display = 'none';
            button.disabled = false;
            button.textContent = originalText;
            
            resultDiv.className = 'analysis-result result-failure';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `❌ 분석 실패: ${errorMessage}`;
        }

        // 분석 결과 로드
        function loadAnalysisResult(safeId, filename) {
            const resultDiv = document.getElementById(`analysis-${safeId}`);
            
            fetch(`analyze_recording.php?filename=${encodeURIComponent(filename)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.analysis) {
                        const analysis = data.analysis.analysis;
                        const transcription = data.analysis.transcription || '텍스트 변환 실패';
                        
                        let statusClass = 'result-unknown';
                        let statusIcon = '❓';
                        
                        switch(analysis.status) {
                            case 'success':
                                statusClass = 'result-success';
                                statusIcon = '✅';
                                break;
                            case 'failed':
                                statusClass = 'result-failure';
                                statusIcon = '❌';
                                break;
                            case 'attempted':
                                statusClass = 'result-uncertain';
                                statusIcon = '⚠️';
                                break;
                            default:
                                statusClass = 'result-unknown';
                                statusIcon = '❓';
                        }
                        
                        resultDiv.className = `analysis-result ${statusClass}`;
                        resultDiv.style.display = 'block';
                        resultDiv.innerHTML = `
                            <div style="margin-bottom: 10px;">
                                <strong>${statusIcon} ${analysis.status.toUpperCase()}</strong> 
                                <span style="opacity: 0.8;">(신뢰도: ${analysis.confidence}%)</span>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <strong>📝 인식된 텍스트:</strong><br>
                                <span style="font-size: 13px; opacity: 0.9;">${transcription}</span>
                            </div>
                            <div>
                                <strong>💭 판단 근거:</strong><br>
                                <span style="font-size: 13px; opacity: 0.9;">${analysis.reason}</span>
                            </div>
                        `;
                    } else {
                        // 재시도 로직
                        const retryCount = resultDiv.getAttribute('data-retry-count') || 0;
                        if (retryCount < 5) {
                            resultDiv.setAttribute('data-retry-count', parseInt(retryCount) + 1);
                            
                            resultDiv.className = 'analysis-result';
                            resultDiv.style.display = 'block';
                            resultDiv.innerHTML = `🔄 분석 결과 로딩 중... (${parseInt(retryCount) + 1}/5)`;
                            
                            setTimeout(() => {
                                loadAnalysisResult(safeId, filename);
                            }, 3000);
                        } else {
                            resultDiv.className = 'analysis-result result-failure';
                            resultDiv.style.display = 'block';
                            resultDiv.innerHTML = `❌ 분석 결과를 불러올 수 없습니다. 페이지를 새로고침해보세요.`;
                        }
                    }
                })
                .catch(error => {
                    resultDiv.className = 'analysis-result result-failure';
                    resultDiv.style.display = 'block';
                    resultDiv.innerHTML = `❌ 결과 로드 실패: ${error.message}`;
                });
        }

        // 기존 분석 결과 로드
        function loadExistingAnalysis() {
            fetch('analyze_recording.php?list=true')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.results) {
                        data.results.forEach(result => {
                            const safeId = result.filename.replace(/[^a-zA-Z0-9]/g, '_');
                            const resultDiv = document.getElementById(`analysis-${safeId}`);
                            
                            if (resultDiv) {
                                loadAnalysisResult(safeId, result.filename);
                            }
                        });
                    }
                })
                .catch(error => {
                    console.log('기존 분석 결과 로드 실패:', error);
                });
        }

        // 새로고침 버튼
        document.getElementById('refreshBtn').addEventListener('click', function() {
            loadRecordings();
        });

        // 페이지 로드 시 초기화
        window.addEventListener('load', function() {
            // DOM이 완전히 로드된 후 함수들 실행
            if (typeof initializeTextarea === 'function') {
                initializeTextarea();
            } else {
                console.error('initializeTextarea function is not defined');
            }
            
            if (typeof loadRecordings === 'function') {
                loadRecordings();
            } else {
                console.error('loadRecordings function is not defined');
            }
        });
    </script>
</body>
</html>
