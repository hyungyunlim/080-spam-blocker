        document.addEventListener('DOMContentLoaded', function() {
                        // URL에서 analysis_id 파라미터 확인
            const urlParams = new URLSearchParams(window.location.search);
            const analysisId = urlParams.get('analysis_id');
            
            console.log('Page loaded, analysis_id:', analysisId);
            
            if (analysisId) {
                checkPatternAnalysisProgress(analysisId);
            }
            
            // 초기 녹음 목록 로드
            getRecordings();
            
            // localStorage에서 진행 중인 분석 복원
            const persistedAnalyses = JSON.parse(localStorage.getItem('activeAnalyses') || '[]');
            persistedAnalyses.forEach(([filename, analysisId]) => {
                activeAnalysisMap.set(filename, analysisId);
            });
            
            // 5초 주기로 녹음 목록 자동 갱신 (로그인된 상태이고 탭이 활성화된 경우에만)
            setInterval(() => {
                if (!document.hidden && window.IS_LOGGED) {
                    getRecordings();
                }
            }, 5000);

            // 전역 progressContainer는 숨김 처리
            const globalProgress = document.getElementById('progressContainer');
            if (globalProgress) globalProgress.style.display = 'none';
            
            });

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
            const spamForm = document.getElementById('spamForm');
            const resultArea = document.getElementById('resultArea');
            const recordingsList = document.getElementById('recordingsList');
            const refreshBtn = document.getElementById('refreshBtn');
            
            let confirmedId = null;
            let lastRecordingsUpdate = null;
            
            // 텍스트영역 자동 크기 조절
            function autoResize(textarea) {
                if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
                }

                if (spamContent) {
                    spamContent.addEventListener('input', function() {
                        autoResize(this);
                        // 새 입력이 시작되면 이전 결과 박스를 숨긴다
                        if (resultArea) {
                            resultArea.style.display = 'none';
                            resultArea.innerHTML = '';
                        }
                        const text = this.value.trim();
                        // Mobile progressive disclosure - show/hide sections based on content
                        handleProgressiveDisclosure(text);
                        if (text.length > 10) {
                            analyzeText(text);
                        } else {
                            hideDynamicInput();
                        }
                    });

                    spamContent.addEventListener('keydown', function(e){
                        // Enter 키 단독 입력으로 폼이 제출되는 것을 방지 (Shift+Enter 는 줄바꿈 허용)
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.stopPropagation();
                            e.preventDefault();
                            // 문단 구분을 위해 줄바꿈만 삽입
                            const start = this.selectionStart;
                            const end = this.selectionEnd;
                            const value = this.value;
                            this.value = value.substring(0, start) + '\n' + value.substring(end);
                            this.selectionStart = this.selectionEnd = start + 1;
                            autoResize(this);
                        }
                    });
                }

            function analyzeText(text) {
            // 080 번호: 하이픈이 섞여 있어도 인식 (예: 080-8888-5050)
            const phone_080_pattern = /080[-0-9]{7,12}/g;
            const rawPhones = text.match(phone_080_pattern) || [];
            // 하이픈 제거 후 중복 제거
            const phoneNumbers = [...new Set(rawPhones.map(p => p.replace(/[^0-9]/g, '')))];
                
                if (phoneNumbers.length === 0) {
                    hideDynamicInput();
                    return;
                }

            const id_patterns = [
                // 명시적인 키워드 기반 패턴 (인증번호/식별번호/고객번호/등록번호/확인번호 뒤에 숫자 4~8자리)
                /(?:인증번호|식별번호|고객번호|등록번호|확인번호)\s*[:\-]?\s*(\d{4,8})/gi,
                // "번호는 123456" 같은 형태 지원
                /번호(?:는|:)?\s*(\d{4,8})/gi
                ];
                
            let foundIds = [];
            id_patterns.forEach(pattern => {
                let match;
                while ((match = pattern.exec(text)) !== null) {
                    if (!phoneNumbers.some(p => p.includes(match[1]))) {
                            foundIds.push(match[1]);
                        }
                }
            });
            
            foundIds = [...new Set(foundIds)]; // 중복 제거

            showDynamicInput(foundIds, phoneNumbers);
        }

        function showDynamicInput(foundIds, phoneNumbers) {
            dynamicContainer.classList.add('show');
                detectedIdSection.style.display = 'none';
            multipleIdSection.style.display = 'none';
                phoneInputSection.style.display = 'none';
                confirmationContainer.classList.remove('show');
                confirmedId = null;
                
            if (foundIds.length === 1) {
                confirmedId = foundIds[0];
                detectedIdText.innerHTML = `080번호: <strong>${phoneNumbers.join(', ')}</strong><br>식별번호: <strong>${confirmedId}</strong>`;
                detectedIdSection.style.display = 'block';
            } else if (foundIds.length > 1) {
                multipleIdSection.style.display = 'block';
                idOptions.innerHTML = '';
                foundIds.forEach((id, index) => {
                    idOptions.innerHTML += `
                        <div class="id-option">
                        <input type="radio" id="id${index}" name="selectedId" value="${id}">
                        <label for="id${index}">${id}</label>
                        </div>
                    `;
                });
                idOptions.innerHTML += `
                    <div class="id-option-custom">
                        <input type="radio" id="customId" name="selectedId" value="custom">
                        <label for="customId">직접 입력:</label>
                        <input type="text" id="customIdInput" class="id-custom-input">
                    </div>
                `;
            } else {
                // 식별번호는 없지만 080 수신거부 번호는 파싱됨 – 사용자에게 번호만 안내
                phoneInputSection.style.display = 'none';
                detectedIdText.innerHTML = `080번호: <strong>${phoneNumbers.join(', ')}</strong>`;
                detectedIdSection.style.display = 'block';
            }
        }
        
        idOptions.addEventListener('change', (e) => {
            if (e.target.name === 'selectedId') {
                showConfirmation();
            }
        });
        
        document.getElementById('customIdInput')?.addEventListener('input', showConfirmation);

            function showConfirmation() {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (!selectedRadio) return;
                
            let selectedValue = (selectedRadio.value === 'custom') 
                ? document.getElementById('customIdInput').value.trim() 
                : selectedRadio.value;

            if(selectedValue) {
                selectedIdDisplay.textContent = selectedValue;
                confirmationContainer.classList.add('show');
            } else {
                confirmationContainer.classList.remove('show');
            }
            }

        // 식별번호 선택/취소 버튼 이벤트 (버튼이 있을 때만)
        if (confirmButton) {
            confirmButton.addEventListener('click', () => {
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (!selectedRadio) return;

                confirmedId = (selectedRadio.value === 'custom')
                    ? document.getElementById('customIdInput').value.trim()
                    : selectedRadio.value;

                if (confirmedId) {
                    detectedIdText.innerHTML = `✅ 선택된 식별번호: <strong>${confirmedId}</strong>`;
                    detectedIdSection.style.display = 'block';
                    multipleIdSection.style.display = 'none';
                    confirmationContainer.classList.remove('show');
                }
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', () => {
                confirmationContainer.classList.remove('show');
                const selectedRadio = document.querySelector('input[name="selectedId"]:checked');
                if (selectedRadio) selectedRadio.checked = false;
                confirmedId = null;
            });
        }

            // Mobile progressive disclosure handler
            function handleProgressiveDisclosure(text) {
                const notificationSection = document.getElementById('notificationSection');
                const verificationSection = document.getElementById('verificationSection');
                const submitSection = document.getElementById('submitSection');
                
                console.log('Progressive disclosure called:', {
                    width: window.innerWidth,
                    isMobile: window.innerWidth <= 768,
                    textLength: text.length,
                    verificationExists: !!verificationSection
                });
                
                // Only apply progressive disclosure on mobile (screen width <= 768px)
                if (window.innerWidth <= 768) {
                    if (text.length > 0) {
                        // Show sections with staggered animations when content is entered
                        if (notificationSection && !notificationSection.classList.contains('show')) {
                            setTimeout(() => {
                                notificationSection.classList.add('show');
                            }, 200);
                        }
                        // Verification section visibility controlled by login_flow.js
                        // Don't automatically show it here
                        // Adjust submit section timing 
                        const submitDelay = 400;
                        if (submitSection && !submitSection.classList.contains('show')) {
                            setTimeout(() => {
                                submitSection.classList.add('show');
                            }, submitDelay);
                        }
                    } else {
                        // Hide sections when content is cleared
                        if (notificationSection) {
                            notificationSection.classList.remove('show');
                        }
                        // Don't remove verification section - controlled by login_flow.js
                        if (submitSection) {
                            submitSection.classList.remove('show');
                        }
                    }
                } else {
                    // On desktop, ensure notification section is always visible
                    if (notificationSection) {
                        notificationSection.classList.add('show');
                    }
                    // Verification section: only show when triggered by user input
                    // (removed auto-show for non-logged users)
                    if (submitSection) {
                        submitSection.classList.add('show');
                    }
                }
            }
            
            // Force initial mobile state
            function initializeMobileState() {
                if (window.innerWidth <= 768) {
                    const notificationSection = document.getElementById('notificationSection');
                    const verificationSection = document.getElementById('verificationSection');
                    const submitSection = document.getElementById('submitSection');
                    
                    // For logged-in users, keep sections visible
                    if (window.IS_LOGGED) {
                        if (notificationSection) {
                            notificationSection.classList.add('show');
                        }
                        if (submitSection) {
                            submitSection.classList.add('show');
                        }
                    } else {
                        // Force remove show class on mobile - CSS handles display
                        if (notificationSection) {
                            notificationSection.classList.remove('show');
                        }
                        if (submitSection) {
                            submitSection.classList.remove('show');
                        }
                    }
                    // Don't remove verification section - controlled by login_flow.js
                }
            }
            
            // Initialize mobile state immediately
            initializeMobileState();
            
            // Initialize progressive disclosure on page load and handle window resize
            // For logged-in users, ensure all sections are visible
            if (window.IS_LOGGED) {
                const notificationSection = document.getElementById('notificationSection');
                const submitSection = document.getElementById('submitSection');
                if (notificationSection) notificationSection.classList.add('show');
                if (submitSection) submitSection.classList.add('show');
            } else {
                // For non-logged users on desktop, show verification section
                if (window.innerWidth > 768) {
                    const verificationSection = document.getElementById('verificationSection');
                    if (verificationSection) verificationSection.classList.add('show');
                }
            }
            
            // Check for pending form data after authentication
            checkAndProcessPendingForm();
            handleProgressiveDisclosure(spamContent ? spamContent.value.trim() : '');
            
            window.addEventListener('resize', function() {
                initializeMobileState();
                handleProgressiveDisclosure(spamContent ? spamContent.value.trim() : '');
            });

            function hideDynamicInput() {
                dynamicContainer.classList.remove('show');
        }

        // Check for pending form data after authentication and process automatically
        function checkAndProcessPendingForm() {
            const pendingData = sessionStorage.getItem('pending_spam_form');
            if (pendingData) {
                try {
                    const formData = JSON.parse(pendingData);
                    // Check if data is recent (within 10 minutes)
                    if (Date.now() - formData.timestamp < 600000) {
                        console.log('Restoring and processing pending form data:', formData);
                        
                        // Restore form data
                        const spamContentField = document.getElementById('spamContent');
                        const notificationPhoneField = document.getElementById('notificationPhone');
                        const phoneNumberField = document.getElementById('phoneNumber');
                        
                        if (spamContentField) spamContentField.value = formData.spam_content;
                        if (notificationPhoneField) notificationPhoneField.value = formData.notification_phone;
                        if (phoneNumberField && formData.phone_number) phoneNumberField.value = formData.phone_number;
                        
                        // Clear pending data
                        sessionStorage.removeItem('pending_spam_form');
                        
                        // Auto-submit form after a short delay
                        setTimeout(() => {
                            console.log('Auto-submitting restored form...');
                            const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                            spamForm.dispatchEvent(submitEvent);
                        }, 1000);
                    } else {
                        // Data too old, remove it
                        sessionStorage.removeItem('pending_spam_form');
                    }
                } catch (e) {
                    console.error('Error processing pending form data:', e);
                    sessionStorage.removeItem('pending_spam_form');
                }
            }
        }

        spamForm.addEventListener('submit', function(e) {
            e.preventDefault();
            resultArea.style.display = 'block';
            resultArea.innerHTML = '처리 중...';
            
            const formData = new FormData(this);
            if (confirmedId) {
                formData.append('id', confirmedId);
            }
            // 폼 액션(process_v2.php)으로 전송
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // 서버에서 HTML이 넘어와도 태그를 제거하고 텍스트만 표시
                const safeText = typeof data === 'string' ? data.replace(/(<([^>]+)>)/gi, '').trimStart() : data;
                resultArea.textContent = safeText;
                
                // 패턴탐색이 시작된 경우 감지
                if (safeText.includes('패턴 디스커버리를 시작합니다') || safeText.includes('패턴 학습 중입니다')) {
                    // 패턴탐색 시작 후 즉시 녹음 상태 추적 시작
                    setTimeout(() => {
                        startMonitoringPatternDiscovery();
                    }, 3000); // 3초 후 모니터링 시작
                }
                
                getRecordings();
            })
            .catch(error => {
                resultArea.textContent = '오류 발생: ' + error;
            });
        });

        let autoAnalysisSet = new Set();

        // 진행 중인 analysis_id를 추적 (filename -> analysis_id)
        const persistedAnalyses = JSON.parse(localStorage.getItem('activeAnalyses') || '[]');
        const activeAnalysisMap = new Map(persistedAnalyses);

        function persistActiveAnalyses() {
            localStorage.setItem('activeAnalyses', JSON.stringify([...activeAnalysisMap]));
        }


        // 기존 getRecordings 함수 내부에서, 진행 중인 analysis_id가 있으면 해당 항목에 프로그레스바 추가
        function getRecordings() {
            // 로그인 상태가 아니면 요청하지 않음 (로그인 플로우가 실행 중일 때)
            if (!window.IS_LOGGED) {
                console.log('Not logged in, skipping getRecordings');
                return;
            }
            
            // 캐시 무효화를 위한 타임스탬프 추가 (브라우저별 일관성 확보)
            const timestamp = Date.now();
            fetch(`get_recordings.php?_t=${timestamp}`, {
                cache: 'no-cache',
                credentials: 'same-origin', // 세션 쿠키 포함
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 401) {
                            // 401 오류 시 로그인 상태 변수 업데이트
                            window.IS_LOGGED = false;
                            
                            // 즉시 새로고침하지 말고 잠시 대기 (로그인 진행 중일 수 있음)
                            setTimeout(() => {
                                if (!window.IS_LOGGED) {
                                    console.log('Session expired, reloading page');
                                    window.location.reload();
                                }
                            }, 2000);
                            
                            throw new Error('로그인이 필요합니다');
                        }
                        throw new Error(`서버 오류: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        recordingsList.innerHTML = `<div class="analysis-result result-failure">${data.error || '오류 발생'}</div>`;
                        return;
                    }

                    // DOM 업데이트 필요 여부와 관계없이 자동 분석 및 진행 상태 체크는 항상 수행
                    if (data.recordings && data.recordings.length > 0) {
                        // 1. 자동 분석 트리거 (DOM 업데이트 전에 먼저 체크)
                        data.recordings.forEach(rec => {
                            if (rec.ready_for_analysis && !autoAnalysisSet.has(rec.filename)) {
                                // DOM에서 버튼 찾기 - 특수문자 이스케이프 처리
                                const escapedFilename = CSS.escape(rec.filename);
                                const btn = document.querySelector(`button.analyze-btn[data-file="${escapedFilename}"]`);
                                const recordingItem = btn ? btn.closest('.recording-item') : null;
                                
                                // 엄격한 중복 방지 체크
                                const hasCallProgress = recordingItem && recordingItem.querySelector('.call-progress');
                                const hasAnalysisProgress = recordingItem && recordingItem.querySelector('.analysis-progress');
                                const isAlreadyInActiveMap = activeAnalysisMap.has(rec.filename);
                                
                                if (btn && !btn.disabled && recordingItem && !hasCallProgress && !hasAnalysisProgress && !isAlreadyInActiveMap) {
                                    autoAnalysisSet.add(rec.filename);
                                    
                                    console.log('Auto-triggering analysis for:', rec.filename, 'after delay');
                                    
                                    // 브라우저 타입에 관계없이 통일된 지연시간 적용 (중복 실행 방지)
                                    setTimeout(() => {
                                        // 다시 한번 엄격한 상태 확인
                                        const currentItem = btn.closest('.recording-item');
                                        const currentCallProgress = currentItem && currentItem.querySelector('.call-progress');
                                        const currentAnalysisProgress = currentItem && currentItem.querySelector('.analysis-progress');
                                        
                                        if (btn && !btn.disabled && currentItem && !currentCallProgress && !currentAnalysisProgress) {
                                            console.log('Executing auto-analysis for:', rec.filename);
                                            handleAnalysisClick(btn);
                                        } else {
                                            console.log('Analysis conditions changed, skipping:', rec.filename);
                                            autoAnalysisSet.delete(rec.filename); // Set에서 제거
                                        }
                                    }, 1000); // 1초로 증가하여 Call progress 완료 대기
                                } else {
                                    console.log('Auto-analysis skipped for:', rec.filename, {
                                        hasBtn: !!btn,
                                        btnDisabled: btn ? btn.disabled : 'no btn',
                                        hasItem: !!recordingItem,
                                        hasCallProgress: !!hasCallProgress,
                                        hasAnalysisProgress: !!hasAnalysisProgress,
                                        isInActiveMap: isAlreadyInActiveMap
                                    });
                                }
                            }
                        });

                        // 2. 통화 진행바 트리거 (DOM 업데이트 전에 체크) - 중복 방지 강화
                        data.recordings.forEach(rec => {
                            if (rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.' && !rec.ready_for_analysis) {
                                const escapedFilename = CSS.escape(rec.filename);
                                const btnEl = document.querySelector(`button.analyze-btn[data-file="${escapedFilename}"]`);
                                const recordingItem = btnEl ? btnEl.closest('.recording-item') : null;
                                
                                // 엄격한 중복 방지: Call Progress와 Analysis Progress 모두 체크
                                const hasCallProgress = recordingItem && recordingItem.querySelector('.call-progress');
                                const hasAnalysisProgress = recordingItem && recordingItem.querySelector('.analysis-progress');
                                
                                if (recordingItem && !hasCallProgress && !hasAnalysisProgress) {
                                    console.log('Triggering call progress for:', rec.filename);
                                    trackCallProgress(recordingItem, rec.filename);
                                } else if (hasCallProgress) {
                                    console.log('Call progress already exists for:', rec.filename);
                                } else if (hasAnalysisProgress) {
                                    console.log('Analysis progress already exists for:', rec.filename);
                                }
                            }
                        });

                        // 3. 진행 중인 분석 재개 (localStorage에서 복원)
                        activeAnalysisMap.forEach((analysisId, filename) => {
                            const rec = data.recordings.find(r => r.filename === filename);
                            if (rec && rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.') {
                                const escapedFilename = CSS.escape(filename);
                                const btnEl = document.querySelector(`button.analyze-btn[data-file="${escapedFilename}"]`);
                                const recordingItem = btnEl ? btnEl.closest('.recording-item') : null;
                                if (recordingItem && !recordingItem.querySelector('.analysis-progress')) {
                                    const progressContainer = createProgressUI(recordingItem);
                                    const button = recordingItem.querySelector('.analyze-btn');
                                    if (rec.call_type === 'discovery') {
                                        // 전화번호 추출
                                        let phoneNumber = '';
                                        if (rec.filename.match(/discovery-(\d+)/)) {
                                            phoneNumber = rec.filename.match(/discovery-(\d+)/)[1];
                                        }
                                        trackPatternAnalysisProgress(analysisId, progressContainer, button, button.innerHTML, phoneNumber, filename);
                                    } else {
                                        trackAnalysisProgress(analysisId, progressContainer, button, button.innerHTML);
                                    }
                                }
                            }
                        });

                        // 4. DOM 업데이트 - Progress UI 상태에 관계없이 항상 신중하게 처리
                        const shouldUpdateDOM = lastRecordingsUpdate === null || data.updated > lastRecordingsUpdate;
                        const hasProgressUI = document.querySelector('.call-progress') || document.querySelector('.analysis-progress');
                        
                        if (shouldUpdateDOM) {
                            lastRecordingsUpdate = data.updated;

                            // 스마트 DOM 업데이트: 기존 요소 재사용 + 새 요소만 추가/제거
                            const existingItems = new Map();
                            const existingProgressItems = new Map(); // Progress UI가 있는 항목들 별도 추적
                            
                            recordingsList.querySelectorAll('.recording-item').forEach(item => {
                                const audio = item.querySelector('audio');
                                if (audio) {
                                    const src = audio.getAttribute('src');
                                    const match = src.match(/file=([^&]+)/);
                                    if (match) {
                                        const filename = decodeURIComponent(match[1]);
                                        existingItems.set(filename, item);
                                        
                                        // Progress UI가 있는 항목인지 확인 (더 정확한 감지)
                                        const hasCallProgress = item.querySelector('.call-progress');
                                        const hasAnalysisProgress = item.querySelector('.analysis-progress');
                                        if (hasCallProgress || hasAnalysisProgress) {
                                            existingProgressItems.set(filename, item);
                                            console.log('Preserving progress UI for:', filename, {
                                                hasCallProgress: !!hasCallProgress,
                                                hasAnalysisProgress: !!hasAnalysisProgress
                                            });
                                        }
                                    }
                                }
                            });

                            const newItems = [];
                            data.recordings.forEach(rec => {
                                let item = existingItems.get(rec.filename);
                                if (item) {
                                    // 기존 항목 재사용 - Progress UI가 있으면 그대로 유지
                                    if (existingProgressItems.has(rec.filename)) {
                                        // Progress UI가 있는 항목은 건드리지 않음
                                        newItems.push(item);
                                    } else {
                                        // Progress UI가 없는 항목은 정보만 업데이트
                                        updateRecordingItemData(item, rec);
                                        newItems.push(item);
                                    }
                                    existingItems.delete(rec.filename);
                                } else {
                                    // 새 항목 생성
                                    item = createRecordingItem(rec);
                                    newItems.push(item);
                                }
                            });

                            // 존재하지 않는 항목들만 제거 (Progress UI가 없는 것들만)
                            existingItems.forEach((item, filename) => {
                                if (!existingProgressItems.has(filename)) {
                                    item.remove();
                                }
                            });
                            
                            // DOM 재구성 - 삭제 중인 버튼 상태 보존
                            const disabledDeleteButtons = new Map();
                            recordingsList.querySelectorAll('.delete-btn[disabled]').forEach(btn => {
                                const recordingFile = btn.dataset.file;
                                if (recordingFile) {
                                    disabledDeleteButtons.set(recordingFile, {
                                        originalContent: btn.innerHTML,
                                        disabled: true
                                    });
                                }
                            });
                            
                            recordingsList.innerHTML = '';
                            newItems.forEach(item => {
                                recordingsList.appendChild(item);
                                
                                // 삭제 중인 버튼 상태 복원
                                const deleteBtn = item.querySelector('.delete-btn');
                                if (deleteBtn && deleteBtn.dataset.file) {
                                    const savedState = disabledDeleteButtons.get(deleteBtn.dataset.file);
                                    if (savedState) {
                                        deleteBtn.disabled = savedState.disabled;
                                        deleteBtn.innerHTML = savedState.originalContent;
                                    }
                                }
                            });
                        }
                    } else {
                        recordingsList.innerHTML = '<div style="text-align: center; padding: 20px; color: #888;">표시할 녹음 파일이 없습니다.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching recordings:', error);
                    // 401 오류의 경우 세션 만료로 간주하고 새로고침 유도
                    if (error.message.includes('401') || error.message.includes('Unauthorized')) {
                        recordingsList.innerHTML = `<div class="analysis-result result-failure">세션이 만료되었습니다. 페이지를 새로고침하여 다시 로그인해주세요.</div>`;
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        recordingsList.innerHTML = `<div class="analysis-result result-failure">녹음 목록을 불러오는 데 실패했습니다: ${error.message}</div>`;
                    }
                });
        }

        function startMonitoringPatternDiscovery() {
            const checkInterval = setInterval(() => {
                const timestamp = Date.now();
                fetch(`get_recordings.php?_t=${timestamp}`, {
                    cache: 'no-cache'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.recordings) {
                            // 최신 discovery 녹음 찾기
                            const discoveryRecording = data.recordings.find(rec => 
                                rec.call_type === 'discovery' && 
                                rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.' &&
                                (Date.now() - rec.file_mtime * 1000) < 60000 // 1분 이내 생성
                            );
                            
                            if (discoveryRecording) {
                                const escapedFilename = CSS.escape(discoveryRecording.filename);
                                
                                // 통화 진행 상태 추적
                                const recordingItem = document.querySelector(`[data-file="${escapedFilename}"]`)?.closest('.recording-item');
                                if (recordingItem && !recordingItem.querySelector('.call-progress')) {
                                    trackCallProgress(recordingItem, discoveryRecording.filename);
                                }
                                
                                // ready_for_analysis가 true가 되면 자동 분석 시작
                                if (discoveryRecording.ready_for_analysis && !autoAnalysisSet.has(discoveryRecording.filename)) {
                                    const btn = document.querySelector(`button.analyze-btn[data-file="${escapedFilename}"]`);
                                    if (btn && !btn.disabled) {
                                        autoAnalysisSet.add(discoveryRecording.filename);
                                        handleAnalysisClick(btn);
                                    }
                                }
                                
                                clearInterval(checkInterval); // 녹음 찾으면 모니터링 중지
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error monitoring pattern discovery:', error);
                    });
            }, 2000); // 2초마다 체크
            
            // 5분 후 자동으로 모니터링 중지
            setTimeout(() => {
                clearInterval(checkInterval);
            }, 300000);
        }

        // 데이터 객체를 받아 녹음 항목 DOM 요소를 생성하는 함수
        function createRecordingItem(rec) {
            const item = document.createElement('div');
            item.className = 'recording-item';
            
            // Encode spam content to avoid attribute truncation/HTML issues
            const spamContentEncoded = rec.spam_content ? btoa(unescape(encodeURIComponent(rec.spam_content))) : '';
            
            const statusColor = rec.analysis_result === '성공' ? 'result-success' : 'result-failure';
            
            const callTypeLabel = rec.call_type === 'discovery' 
                ? '<span class="label label-discovery">패턴탐색</span>' 
                : '<span class="label label-unsubscribe">수신거부</span>';

            const autoLabel = rec.trigger === 'auto' ? '<span class="label label-auto">자동</span>' : '';

            // 패턴 소스 라벨
            let patternSourceLabel = '';
            if (rec.pattern_source) {
                switch (rec.pattern_source) {
                    case 'community':
                        patternSourceLabel = '<span class="label label-community">커뮤니티</span>';
                        break;
                    case 'default':
                        patternSourceLabel = '<span class="label label-default">기본패턴</span>';
                        break;
                    // 'user'인 경우는 라벨 표시 안함 (기본값)
                }
            }

            let patternTypeBadge = '';
            if (rec.pattern_data) {
                if (rec.pattern_data.auto_supported === false) {
                    patternTypeBadge = '<span class="label label-unverified">확인 번호만 필요</span>';
                } else if (rec.pattern_data.pattern_type === 'id_only') {
                    patternTypeBadge = '<span class="label label-id-only">식별번호만 필요</span>';
                } else if (rec.pattern_data.pattern_type === 'confirm_only') {
                    patternTypeBadge = '<span class="label label-unverified">확인 번호만 필요</span>';
                }
            }
            const registrationBadge = rec.pattern_registered ? '<span class="label label-registered">패턴등록</span>' : '';

            let analysisDetailsHtml = '';
            let showAnalyzeButton = false;
            let showReanalyzeButton = false;
            const isConfirmOnly = rec.pattern_data && (rec.pattern_data.auto_supported === false || rec.pattern_data.pattern_type === 'confirm_only');
            let showRetryCallButton = false;
            if (rec.call_type === 'unsubscribe' && rec.analysis_result === '실패') {
                showRetryCallButton = true;
            }
                    
            if (rec.analysis_result && !(rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.')) {
                const completedAt = rec.completed_at ? new Date(rec.completed_at).toLocaleString('ko-KR') : '';
                const confidenceText = rec.confidence ? ` (신뢰도: ${rec.confidence}%)` : '';
                
                // 패턴 탐색 결과인 경우 특별 처리
                if (rec.call_type === 'discovery' && rec.pattern_data) {
                    analysisDetailsHtml = `
                        <strong>패턴 분석 완료</strong>${confidenceText}${completedAt ? ` <span style="color:#666;">(${completedAt})</span>` : ''}
                        <p><strong>패턴명:</strong> ${rec.pattern_data.name}</p>
                        <p><strong>DTMF 타이밍:</strong> ${rec.pattern_data.dtmf_timing}초</p>
                        <p><strong>DTMF 패턴:</strong> ${rec.pattern_data.dtmf_pattern}</p>
                    `;
                } else {
                    // 일반 분석 결과
                    analysisDetailsHtml = `
                        <strong>분석 결과:</strong> ${rec.analysis_result}${confidenceText}${completedAt ? ` <span style="color:#666;">(${completedAt})</span>` : ''}
                        <p>${rec.analysis_text || ''}</p>
                    `;
                }
                
                if (rec.transcription) {
                    const transText = rec.transcription.trim() ? rec.transcription : '변환된 텍스트를 가져올 수 없습니다.';
                    analysisDetailsHtml += `
                        <div class="transcription-container">
                            <button class="btn btn-small btn-secondary toggle-transcription">전체 내용 보기</button>
                            <div class="transcription-text" style="display: none;">
                                <p><strong>변환된 텍스트:</strong></p>
                                <pre>${transText}</pre>
                            </div>
                            </div>
                        `;
                }
                showReanalyzeButton = true; // 분석 완료된 파일에 다시 분석 버튼 표시
            } else if (rec.call_type === 'discovery' && rec.pattern_registered) {
                // 패턴이 이미 등록된 탐색 녹음
                if (rec.pattern_data) {
                    const pat = rec.pattern_data;
                    analysisDetailsHtml = `
                        <strong>패턴 등록 완료</strong><br/>
                        <p><strong>패턴명:</strong> ${pat.name || '자동 생성 패턴'}</p>
                        <p><strong>DTMF 패턴:</strong> ${pat.dtmf_pattern}</p>
                        <p><strong>DTMF 타이밍:</strong> ${pat.dtmf_timing}초</p>
                        <p><strong>초기 대기:</strong> ${pat.initial_wait}초</p>
                        <p><strong>확인 DTMF:</strong> ${pat.confirmation_dtmf} (지연 ${pat.confirm_delay}s x ${pat.confirm_repeat}회)</p>
                    `;
                } else {
                    analysisDetailsHtml = '<strong>패턴 등록 완료</strong><br/>이미 자동 생성된 패턴이 등록되어 있습니다.';
                }
            } else {
                // 미분석 + 패턴 미등록 -> 결과 영역 숨김
                analysisDetailsHtml = '';
                showAnalyzeButton = true;
            }

            // 스팸 문자 원본 보기 버튼 (수신거부 통화이고 스팸 내용이 있는 경우)
            const showSpamContentButton = rec.call_type === 'unsubscribe' && rec.spam_content;
            
            // 분석 결과 섹션 (없을 경우 display:none)
            const analysisResultSection = `
                <div class="analysis-result ${statusColor}" style="display: ${analysisDetailsHtml ? 'block' : 'none'};">
                    ${analysisDetailsHtml}
                </div>`;

            // auto-analysis 로직은 filename 으로 버튼을 찾으므로 data-file 은 순수 파일명만 사용
            const fileForAnalysis = rec.filename;

            item.innerHTML = `
                <div class="recording-header">
                                <div class="recording-info">
                                    <div class="recording-title">
                            📞 ${rec.title}
                                    </div>
                                    <div class="recording-datetime">
                            <span class="date-icon">📅</span> ${rec.datetime}
                                    </div>
                                </div>
                    <div class="recording-tags">${callTypeLabel} ${autoLabel} ${patternSourceLabel} ${registrationBadge} ${patternTypeBadge}</div>
                                    </div>
                ${rec.ready_for_analysis || !(rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.') ? 
                    `<audio controls preload="metadata" src="player.php?file=${encodeURIComponent(rec.filename)}&v=${rec.file_mtime}" style="width: 100%; margin-top: 10px;" crossorigin="anonymous" onloadeddata="this.currentTime=0;"></audio>` : 
                    `<div class="audio-placeholder" style="width: 100%; margin-top: 10px; padding: 15px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 8px; text-align: center; color: #6c757d;">
                        <div style="font-size: 14px;">🎙️ 녹음 진행 중...</div>
                        <div style="font-size: 12px; margin-top: 5px;">통화 완료 후 재생 가능합니다</div>
                    </div>`
                }
                ${analysisResultSection}
                ${showAnalyzeButton ? `
                <div class="recording-actions" style="margin-top: 10px; display: flex; justify-content: flex-end; width: 100%;">
                    ${showSpamContentButton ? `<button data-spam-content='${spamContentEncoded}' data-spam-date="${rec.spam_received_at || ''}" class="btn btn-small spam-content-btn"><span class="btn-mobile-text">📱</span><span class="btn-desktop-text">스팸문자 원본</span></button>` : ''}
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small analyze-btn">
                        <span class="btn-mobile-text">✨</span><span class="btn-desktop-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic" viewBox="0 0 16 16" style="margin-right: 4px;">
                            <path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 4a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8.707 4a.5.5 0 0 0 0-.707Zm-3.5 1.65A.5.5 0 0 0 3.293 6L2 7.293a.5.5 0 1 0 .707.707L4 6.707a.5.5 0 0 0 0-.707l-.707-.707a.5.5 0 0 0-.707 0ZM10 8a2 2 0 1 0-4 0 2 2 0 0 0 4 0Z"/>
                            <path d="M6.25 10.5c.065.14.12.29.18.445l.08.18a.5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495Zm3.5 0c.065.14.12.29.18.445l.08.18a .5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495ZM1.625 13.5A.5.5 0 0 0 1 14h14a.5.5 0 0 0-.625-.5h-12.75Z"/>
                        </svg>
                        분석하기</span>
                    </button>
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small delete-btn">
                        <span class="btn-mobile-text">🗑</span><span class="btn-desktop-text">삭제</span>
                    </button>
                </div>
                ` : ''}
                ${showReanalyzeButton ? `
                <div class="recording-actions" style="margin-top: 10px; display: flex; justify-content: flex-end; width: 100%;">
                    ${showSpamContentButton ? `<button data-spam-content='${spamContentEncoded}' data-spam-date="${rec.spam_received_at || ''}" class="btn btn-small spam-content-btn"><span class="btn-mobile-text">📱</span><span class="btn-desktop-text">스팸문자 원본</span></button>` : ''}
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small reanalyze-btn analyze-btn">
                        <span class="btn-mobile-text">🔄</span><span class="btn-desktop-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16" style="margin-right: 4px;">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        ${rec.call_type === 'discovery' ? '패턴 다시 분석하기' : '다시 분석하기'}</span>
                    </button>
                    ${showRetryCallButton ? `<button data-file="${fileForAnalysis}" data-phone="${rec.title}" data-id="${rec.identification_number || rec.id || ''}" data-notify="${rec.notification_phone || ''}" class="btn btn-small retry-call-btn" ${isConfirmOnly?'disabled title="자동 수신거부가 불가능합니다."':''}><span class="btn-mobile-text">${isConfirmOnly?'☎️':'📞'}</span><span class="btn-desktop-text">${isConfirmOnly?'직접 전화 필요':'다시 시도하기'}</span></button>` : ''}
                    <button data-file="${fileForAnalysis}" data-type="${rec.call_type}" class="btn btn-small delete-btn">
                        <span class="btn-mobile-text">🗑</span><span class="btn-desktop-text">삭제</span>
                    </button>
                </div>
                ` : ''}
            `;

            // The recording-actions class is now applied directly in the HTML template above
            
            
            // 이벤트 리스너 추가 (이벤트 위임 대신 직접 추가)
            const transcriptionToggle = item.querySelector('.toggle-transcription');
            if (transcriptionToggle) {
                transcriptionToggle.addEventListener('click', function() {
                    const textDiv = item.querySelector('.transcription-text');
                    const isVisible = textDiv.style.display === 'block';
                    textDiv.style.display = isVisible ? 'none' : 'block';
                    this.textContent = isVisible ? '전체 내용 보기' : '숨기기';
                    // 파일명 기준으로 펼침 상태 저장/제거
                    if (!isVisible) {
                        openTranscriptions.add(rec.filename);
                    } else {
                        openTranscriptions.delete(rec.filename);
                    }
                    localStorage.setItem('openTranscriptions', JSON.stringify([...openTranscriptions]));
                });
            }
            // 목록 갱신 시 펼침 상태 복원
            if (openTranscriptions.has(rec.filename)) {
                const textDiv = item.querySelector('.transcription-text');
                if (textDiv) {
                    textDiv.style.display = 'block';
                    if (transcriptionToggle) transcriptionToggle.textContent = '숨기기';
                }
            }
            
            // 통화 진행 상태 즉시 트리거 (녹음중일 때)
            if (rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.' && !rec.ready_for_analysis && !item.querySelector('.call-progress')) {
                trackCallProgress(item, rec.filename);
            }
            
            const retryBtn = item.querySelector('.retry-call-btn');
            if (retryBtn && !retryBtn.disabled) {
                retryBtn.addEventListener('click', function(){
                    const phone = this.dataset.phone;
                    const idVal = this.dataset.id || '';
                    const notifyVal = this.dataset.notify || '';
                    if(!phone){ showToast('전화번호를 확인할 수 없습니다.',true); return; }
                    if (rec.pattern_data && rec.pattern_data.auto_supported === false) {
                        showToast('이 번호는 자동 수신거부가 불가능합니다. 안내에 따라 수동으로 진행해주세요.', true);
                        return;
                    }
                    // confirm 제거 – 바로 재시도 실행
                    const params = `phone=${encodeURIComponent(phone)}&id=${encodeURIComponent(idVal)}${notifyVal?`&notify=${encodeURIComponent(notifyVal)}`:''}`;
                    fetch('retry_call.php',{
                        method:'POST',
                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body:params
                    })
                    .then(r=>r.text())
                    .then(txt=>{ const msg = txt.trim()?txt:'자동 수신거부가 불가능한 번호입니다.'; showToast(msg); getRecordings(); })
                    .catch(()=>showToast('다시 시도 요청 중 오류가 발생했습니다.',true));
                });
            }
            
            return item;
        }

        // 기존 DOM 요소의 데이터만 업데이트 (Progress UI 보존용)
        function updateRecordingItemData(item, rec) {
            // 분석 결과가 변경된 경우에만 업데이트
            const currentAnalysisSection = item.querySelector('.analysis-section');
            const hasProgressUI = item.querySelector('.call-progress') || item.querySelector('.analysis-progress');
            
            // Progress UI가 활성화된 상태면 건드리지 않음
            if (hasProgressUI) {
                console.log('Skipping update for item with active progress UI:', rec.filename);
                return;
            }
            
            // 분석 결과 업데이트
            if (currentAnalysisSection && !(rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.')) {
                const newAnalysisSection = createAnalysisSection(rec);
                if (newAnalysisSection !== currentAnalysisSection.outerHTML) {
                    currentAnalysisSection.outerHTML = newAnalysisSection;
                }
            }
            
            // 버튼 상태 업데이트
            const analyzeBtn = item.querySelector('.analyze-btn');
            if (analyzeBtn && rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.' && rec.ready_for_analysis) {
                analyzeBtn.disabled = false;
                analyzeBtn.innerHTML = `
                    <span class="btn-mobile-text">✨</span><span class="btn-desktop-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-magic" viewBox="0 0 16 16" style="margin-right: 4px;">
                        <path d="M9.5 2.672a.5.5 0 1 0 1 0V.843a.5.5 0 0 0-1 0v1.829Zm4.5.035A.5.5 0 0 0 13.293 2L12 3.293a.5.5 0 1 0 .707.707L14 2.707a.5.5 0 0 0 0-.707ZM7.293 4L8 3.293a.5.5 0 1 0-.707-.707L6.586 4a.5.5 0 0 0 0 .707l.707.707a.5.5 0 0 0 .707 0L8.707 4a.5.5 0 0 0 0-.707Zm-3.5 1.65A.5.5 0 0 0 3.293 6L2 7.293a.5.5 0 1 0 .707.707L4 6.707a.5.5 0 0 0 0-.707l-.707-.707a.5.5 0 0 0-.707 0ZM10 8a2 2 0 1 0-4 0 2 2 0 0 0 4 0Z"/>
                        <path d="M6.25 10.5c.065.14.12.29.18.445l.08.18a.5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495Zm3.5 0c.065.14.12.29.18.445l.08.18a .5.5 0 0 0 .868.036l.338-.676a.5.5 0 0 0-.16-.672l-.354-.354a.5.5 0 0 0-.85-.043l-.248.495ZM1.625 13.5A.5.5 0 0 0 1 14h14a.5.5 0 0 0-.625-.5h-12.75Z"/>
                    </svg>
                    분석하기</span>
                `;
            }
        }

        // 분석 섹션 HTML 생성 헬퍼 함수
        function createAnalysisSection(rec) {
            if (rec.analysis_result === '실패' && rec.analysis_text === '아직 분석되지 않았습니다.') {
                return '';
            }
            
            // 실제 분석 결과 HTML 생성 로직을 여기에 추가
            // 기존 createRecordingItem의 analysisResultSection 로직을 재사용
            return `<div class="analysis-section">${rec.analysis_result}</div>`;
        }

        // 수동 분석 버튼 클릭 처리 함수
        function handleAnalysisClick(button) {
            const recordingFile = button.dataset.file;
            const callType = button.dataset.type || 'unsubscribe';
            console.log('Analyze button clicked, file:', recordingFile, 'type:', callType);
            console.log('Button dataset:', button.dataset);
            console.log('Button HTML:', button.outerHTML);
            
            if (!recordingFile) {
                showToast('분석할 파일 경로를 찾을 수 없습니다.', true);
                return;
            }

            // 버튼이 있는 recording-item 찾기
            const recordingItem = button.closest('.recording-item');
            if (!recordingItem) {
                showToast('녹음 항목을 찾을 수 없습니다.', true);
                return;
            }
            
            // 이미 분석이 진행 중인지 확인
            if (recordingItem.querySelector('.analysis-progress')) {
                showToast('이미 분석이 진행 중입니다.', true);
                return;
            }
            
            // 버튼 상태 변경
            button.disabled = true;
            const originalContent = button.innerHTML;
            button.innerHTML = '<span class="spinner" style="width: 14px; height: 14px; margin-right: 5px;"></span> 분석 시작중...';

            // 전체 경로가 아닌 파일명만 전송
            const filename = recordingFile.includes('/') ? recordingFile.split('/').pop() : recordingFile;
            const fullPath = recordingFile.includes('/') ? recordingFile : '/var/spool/asterisk/monitor/' + recordingFile;

            console.log('Sending request with file:', fullPath);
            
            // call_type에 따라 다른 API 호출
            const apiUrl = callType === 'discovery' ? 'analyze_pattern_recording.php' : 'analyze_recording.php';

            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'file=' + encodeURIComponent(fullPath)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Response body:', text);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Analysis response:', data);
                if (data.success && data.analysis_id) {
                    // 기존 분석이 진행 중인 경우 처리
                    if (data.existing) {
                        console.log('Using existing analysis:', data.analysis_id);
                        showToast('이미 분석이 진행 중입니다. 기존 분석을 추적합니다.', false);
                    }
                    
                    // 진행 상황 표시 UI 생성 (기존 것이 없을 때만)
                    let progressContainer = recordingItem.querySelector('.analysis-progress');
                    if (!progressContainer) {
                        progressContainer = createProgressUI(recordingItem);
                        if (!progressContainer) {
                            console.error('Failed to create progress UI for:', recordingItem);
                            showToast('프로그레스 UI 생성 실패', true);
                            button.disabled = false;
                            button.innerHTML = originalContent;
                            return;
                        }
                    }
                    
                    // 진행 중인 analysis_id를 추적
                    activeAnalysisMap.set(filename, data.analysis_id);
                    persistActiveAnalyses();
                    
                    // call_type에 따라 다른 진행 상황 추적
                    if (callType === 'discovery') {
                        trackPatternAnalysisProgress(data.analysis_id, progressContainer, button, originalContent, data.phone_number, filename);
                    } else {
                        trackAnalysisProgress(data.analysis_id, progressContainer, button, originalContent);
                    }
                } else {
                    showToast('분석 시작 실패: ' + (data.message || '알 수 없는 오류'), true);
                    button.disabled = false;
                    button.innerHTML = originalContent;
                    autoAnalysisSet.delete(filename);
                    activeAnalysisMap.delete(filename);
                }
            })
            .catch(error => {
                showToast('분석 스크립트 실행 중 오류가 발생했습니다.', true);
                console.error('Fetch Error:', error);
                button.disabled = false;
                button.innerHTML = originalContent;
                autoAnalysisSet.delete(filename);
                activeAnalysisMap.delete(filename);
                
                // 오류 발생 시 progress UI도 제거
                const progressContainer = recordingItem.querySelector('.analysis-progress');
                if (progressContainer) {
                    progressContainer.remove();
                }
            });
        }

        // 진행 상황 UI 생성
        function createProgressUI(recordingItem) {
            if (!recordingItem) {
                console.error('createProgressUI: recordingItem is null');
                return null;
            }
            
            // 기존 진행 상황 UI가 있으면 제거
            const existingProgress = recordingItem.querySelector('.analysis-progress');
            if (existingProgress) {
                existingProgress.remove();
            }

            const progressHTML = `
                <div class="analysis-progress" style="margin-top: 15px; padding: 15px; background: #f0f4f8; border-radius: 8px; border: 1px solid #d1d9e6;">
                    <div class="progress-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span class="progress-stage" style="font-weight: 600; color: #4a5568;">🧠 M1 AI 분석 준비중...</span>
                        <span class="progress-percentage" style="font-weight: 600; color: #667eea;">0%</span>
                    </div>
                    <div class="progress-bar" style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div class="progress-fill" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <div class="progress-message" style="margin-top: 8px; font-size: 13px; color: #718096;">
                        <span class="analysis-location" style="font-weight: 600; color: #667eea;">🔗 M1 맥미니</span>에서 Whisper Medium 모델로 분석 중...
                    </div>
                </div>
            `;

            try {
                recordingItem.insertAdjacentHTML('beforeend', progressHTML);
                const progressContainer = recordingItem.querySelector('.analysis-progress');
                
                if (!progressContainer) {
                    console.error('createProgressUI: Failed to create progress container');
                    return null;
                }
                
                console.log('Progress UI created successfully');
                return progressContainer;
            } catch (error) {
                console.error('createProgressUI: Error inserting HTML:', error);
                return null;
            }
        }

        // 진행 상황 추적 (수신거부 분석용)
        function trackAnalysisProgress(analysisId, progressContainer, button, originalButtonContent) {
            const stageElement = progressContainer.querySelector('.progress-stage');
            const percentageElement = progressContainer.querySelector('.progress-percentage');
            const fillElement = progressContainer.querySelector('.progress-fill');
            const messageElement = progressContainer.querySelector('.progress-message');
            const recordingItem = progressContainer.closest('.recording-item');
            
            let pollCount = 0;
            const maxPollCount = 300; // 최대 5분 (400ms * 300 = 2분) -> 300 * 400ms = 2분

            const stageNames = {
                'queued': '🔄 M1 분석 대기중',
                'starting': '🚀 M1 분석 시작',
                'file_check': '📁 M1 파일 확인',
                'loading_model': '🧠 Whisper Medium 모델 로딩',
                'model_loaded': '✅ 모델 로드 완료',
                'transcribing': '🎤 M1 음성 변환 중',
                'transcription_done': '📝 STT 완료',
                'analyzing_keywords': '🔍 키워드 분석',
                'analyzing': '🤖 AI 텍스트 분석',
                'saving': '💾 결과 저장',
                'completed': '✅ M1 분석 완료',
                'error': '❌ 분석 오류',
                'timeout': '⏰ 시간 초과'
            };

            // 진행 상황 확인 함수
            const POLL_INTERVAL = 400; // ms – 더 짧은 주기로 폴링하여 빠른 단계 변화를 포착

            const checkProgress = () => {
                pollCount++;
                
                // 타임아웃 체크
                if (pollCount > maxPollCount) {
                    console.warn('Analysis polling timeout for:', analysisId);
                    progressContainer.style.background = '#fef3c7';
                    progressContainer.style.borderColor = '#fbbf24';
                    stageElement.textContent = '타임아웃';
                    messageElement.textContent = '분석 시간이 초과되었습니다. 결과를 확인해주세요.';
                    
                    setTimeout(() => {
                        progressContainer.remove();
                        button.disabled = false;
                        button.innerHTML = originalButtonContent;
                        getRecordings();
                    }, 3000);
                    return;
                }
                
                fetch(`get_analysis_progress.php?analysis_id=${analysisId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                            const stage = data.stage || 'unknown';
                            const percentage = data.percentage || 0;
                            const message = data.message || '';

                            // UI 업데이트
                            stageElement.textContent = stageNames[stage] || stage;
                            percentageElement.textContent = percentage + '%';
                            fillElement.style.width = percentage + '%';
                            messageElement.textContent = message;

                            if (data.completed || stage === 'completed') {
                                // 분석 완료
                                progressContainer.style.background = '#d1fae5';
                                progressContainer.style.borderColor = '#a7f3d0';
                                stageElement.style.color = '#065f46';
                                
                                // localStorage에서 해당 분석 제거
                                const audioElement = recordingItem.querySelector('audio');
                                if (audioElement) {
                                    const src = audioElement.getAttribute('src');
                                    const match = src.match(/file=([^&]+)/);
                                    if (match) {
                                        const filename = decodeURIComponent(match[1]);
                                        activeAnalysisMap.delete(filename);
                                        persistActiveAnalyses();
                                    }
                                }
                                
                                // M1 분석 완료 즉시 결과 갱신
                                showToast('🎉 M1 분석 완료! 결과를 확인하세요');
                                
                                // 즉시 전체 목록 갱신하여 최신 결과 반영
                                getRecordings();
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    button.disabled = false;
                                    button.innerHTML = originalButtonContent;
                                    
                                    // 한번 더 갱신하여 완전한 동기화 보장
                                    getRecordings();
                                }, 1500);
                            } else if (stage === 'error' || stage === 'timeout') {
                                // 오류 발생
                                progressContainer.style.background = '#fee2e2';
                                progressContainer.style.borderColor = '#fecaca';
                                stageElement.style.color = '#991b1b';
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    button.disabled = false;
                                    button.innerHTML = originalButtonContent;
                                }, 3000);
                } else {
                                // 계속 진행중 – 지정 주기 후 다시 확인
                                setTimeout(checkProgress, POLL_INTERVAL);
                            }
                        } else if (data.success === false) {
                            // API 에러 발생 시 분석 완료로 간주하고 결과 확인
                            console.warn('Analysis API error, checking results:', data);
                            progressContainer.style.background = '#fef3c7';
                            progressContainer.style.borderColor = '#fbbf24';
                            stageElement.textContent = '결과 확인 중...';
                            messageElement.textContent = '분석 상태를 확인하고 있습니다...';
                            
                            setTimeout(() => {
                                progressContainer.remove();
                                button.disabled = false;
                                button.innerHTML = originalButtonContent;
                                updateSingleRecordingItem(recordingItem);
                                getRecordings();
                            }, 3000);
                        } else {
                            // API 오류
                            console.error('Progress check failed:', data);
                            progressContainer.remove();
                            button.disabled = false;
                            button.innerHTML = originalButtonContent;
                }
            })
            .catch(error => {
                        console.error('Progress check error:', error);
                        progressContainer.remove();
                        button.disabled = false;
                        button.innerHTML = originalButtonContent;
                    });
            };
            
            // 첫 번째 확인은 250ms 후에 시작 – 빠른 초기 단계 포착
            setTimeout(checkProgress, 250);
        }

        // 단일 녹음 항목 업데이트 함수
        function updateSingleRecordingItem(recordingItem) {
            // 오디오 요소에서 파일명 추출
            const audioElement = recordingItem.querySelector('audio');
            if (!audioElement) return;
            
            const src = audioElement.getAttribute('src');
            const match = src.match(/file=([^&]+)/);
            if (!match) return;
            
            const filename = decodeURIComponent(match[1]);
            
            // 서버에서 최신 데이터 가져오기
            fetch('get_recordings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.recordings) {
                        // 해당 파일의 최신 정보 찾기
                        const updatedRec = data.recordings.find(rec => rec.filename === filename);
                        if (updatedRec) {
                            // 새로운 항목으로 교체
                            const newItem = createRecordingItem(updatedRec);
                            recordingItem.replaceWith(newItem);
                            
                            // 애니메이션 효과
                            newItem.style.animation = 'fadeIn 0.5s ease-in';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating recording item:', error);
            });
        }

        // 패턴 분석 진행 상황 추적
        function trackPatternAnalysisProgress(analysisId, progressContainer, button, originalButtonContent, phoneNumber, filename) {
            const stageElement = progressContainer.querySelector('.progress-stage');
            const percentageElement = progressContainer.querySelector('.progress-percentage');
            const fillElement = progressContainer.querySelector('.progress-fill');
            const messageElement = progressContainer.querySelector('.progress-message');
            const recordingItem = progressContainer.closest('.recording-item');

            let pollCount = 0;
            const maxPollCount = 180; // 최대 3분 (800ms * 180 = 2.4분)

            const stageNames = {
                'queued': '대기중',
                'starting': '시작중',
                'loading_model': '모델 로딩',
                'model_loaded': '모델 로드 완료',
                'transcribing': '음성 변환',
                'transcribed': '음성 변환 완료',
                'analyzing_keywords': '키워드 분석',
                'analyzing': '텍스트 분석',
                'saving': '결과 저장',
                'completed': '완료',
                'error': '오류',
                'timeout': '시간 초과'
            };

            // 폴링 주기 (ms)
            const POLL_INTERVAL = 800;

            // 진행 상황 확인 함수
            const checkProgress = () => {
                pollCount++;
                
                // 타임아웃 체크
                if (pollCount > maxPollCount) {
                    console.warn('Pattern analysis polling timeout for:', analysisId);
                    progressContainer.style.background = '#fef3c7';
                    progressContainer.style.borderColor = '#fbbf24';
                    stageElement.textContent = '타임아웃';
                    messageElement.textContent = '패턴 분석 시간이 초과되었습니다. 결과를 확인해주세요.';
                    
                    setTimeout(() => {
                        progressContainer.remove();
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = originalButtonContent;
                        }
                        if (filename && activeAnalysisMap.has(filename)) {
                            activeAnalysisMap.delete(filename);
                            persistActiveAnalyses();
                        }
                        getRecordings();
                    }, 3000);
                    return;
                }
                fetch(`get_pattern_analysis_progress.php?analysis_id=${analysisId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && !data.prevent_refresh) {
                            const stage = data.stage || 'unknown';
                            const percentage = data.percentage || 0;
                            const message = data.message || '';

                            // UI 업데이트
                            stageElement.textContent = stageNames[stage] || stage;
                            percentageElement.textContent = percentage + '%';
                            fillElement.style.width = percentage + '%';
                            messageElement.textContent = message;
                            
                            if (data.completed || stage === 'completed') {
                                // 분석 완료
                                progressContainer.style.background = '#d1fae5';
                                progressContainer.style.borderColor = '#a7f3d0';
                                stageElement.style.color = '#065f46';
                                
                                let successMessage = '패턴 분석이 완료되었습니다!';
                                successMessage += ` ${phoneNumber} 번호의 패턴이 저장되었습니다.`;
                                if (data.pattern_saved) {
                                    successMessage += ` ${phoneNumber} 번호의 패턴이 저장되었습니다.`;
                                }
                                if (filename && activeAnalysisMap.has(filename)) {
                                    activeAnalysisMap.delete(filename);
                                    persistActiveAnalyses();
                                }
                                
                                setTimeout(() => {
                                    progressContainer.remove();
                                    if (button) {
                                        button.disabled = false;
                                        button.innerHTML = originalButtonContent;
                                    }
                                    showToast(successMessage);
                                    
                                    // 패턴 분석 결과 표시
                                    if (data.result) {
                                        displayPatternAnalysisResult(recordingItem, data.result);
                                    }
                                    // 패턴 저장에 따른 태그 갱신
                                    updateSingleRecordingItem(recordingItem);
                                }, 2000);
                            } else if (stage === 'error' || stage === 'timeout') {
                                // 오류 발생
                                progressContainer.style.background = '#fee2e2';
                                progressContainer.style.borderColor = '#fecaca';
                                stageElement.style.color = '#991b1b';
                            
                            setTimeout(() => {
                                    progressContainer.remove();
                                    if (button) {
                                        button.disabled = false;
                                        button.innerHTML = originalButtonContent;
                                    }
                            }, 3000);
                            } else {
                                // 계속 진행중 – 지정 주기 후 다시 확인
                                setTimeout(checkProgress, POLL_INTERVAL);
                            }
                        } else {
                            // 아직 progress 파일이 생성되지 않았거나 서버가 준비 중
                            stageElement.textContent = '대기중';
                            messageElement.textContent = '서버 준비중...';
                            setTimeout(checkProgress, 1500); // 재시도
                         }
                    })
                    .catch(error => {
                        console.error('Progress check error:', error);
                        progressContainer.remove();
                        if (button) {
                            button.disabled = false;
                            button.innerHTML = originalButtonContent;
                        }
                    });
            };
            
            // 첫 번째 확인은 500ms 후에 시작
            setTimeout(checkProgress, 500);
        }

        // 패턴 분석 결과 표시
        function displayPatternAnalysisResult(recordingItem, result) {
            const analysisResultDiv = recordingItem.querySelector('.analysis-result');
            if (!analysisResultDiv) return;
            
            const pattern = result.pattern;
            const confidence = result.confidence || 0;
            
            analysisResultDiv.className = 'analysis-result result-success';
            analysisResultDiv.style.display = 'block';
            analysisResultDiv.innerHTML = `
                <strong>패턴 분석 완료</strong> (신뢰도: ${confidence}%)
                <p><strong>패턴명:</strong> ${pattern.name}</p>
                <p><strong>DTMF 타이밍:</strong> ${pattern.dtmf_timing}초</p>
                <p><strong>DTMF 패턴:</strong> ${pattern.dtmf_pattern}</p>
                ${result.transcription ? `
                <div class="transcription-container">
                    <button class="btn btn-small btn-secondary toggle-transcription">전체 내용 보기</button>
                    <div class="transcription-text" style="display: none;">
                        <p><strong>변환된 텍스트:</strong></p>
                        <pre>${result.transcription}</pre>
                </div>
                    </div>
                ` : ''}
            `;

            // 토글 버튼에 이벤트 리스너 추가
            const transcriptionToggle = analysisResultDiv.querySelector('.toggle-transcription');
            if (transcriptionToggle) {
                transcriptionToggle.addEventListener('click', function() {
                    const textDiv = analysisResultDiv.querySelector('.transcription-text');
                    const isVisible = textDiv.style.display === 'block';
                    textDiv.style.display = isVisible ? 'none' : 'block';
                    this.textContent = isVisible ? '전체 내용 보기' : '숨기기';
                });
            }

            // 버튼 영역 업데이트 - 다시 분석하기 버튼만 표시
            const analyzeBtn = recordingItem.querySelector('.analyze-btn');
            const fileForAnalysis = analyzeBtn ? analyzeBtn.dataset.file : '';
            const buttonContainer = analyzeBtn ? analyzeBtn.parentElement : null;
            
            if (buttonContainer) {
                buttonContainer.innerHTML = `
                    <button data-file="${fileForAnalysis}" data-type="discovery" class="btn btn-small reanalyze-btn analyze-btn">
                        <span class="btn-mobile-text">🔄</span><span class="btn-desktop-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16" style="margin-right: 4px;">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        패턴 다시 분석하기</span>
                    </button>
                `;
            }
            
            // 분석 완료 후 전체 목록 새로고침하여 결과가 유지되도록 함
            setTimeout(() => {
                getRecordings();
            }, 1000);
        }

        // 녹음 목록이 있을 때만 이벤트 위임 및 오디오 이벤트 설정
        if (recordingsList) {
            // 수동 분석 버튼 클릭 처리 - 이벤트 위임 수정
            recordingsList.addEventListener('click', function(event) {
                // 삭제 버튼 처리
                const delBtn = event.target.closest('.delete-btn');
                if (delBtn && !delBtn.disabled) {
                    event.preventDefault();
                    handleDeleteClick(delBtn);
                    return;
                }

                // 스팸 문자 원본 보기 버튼 처리
                const spamBtn = event.target.closest('.spam-content-btn');
                if (spamBtn) {
                    event.preventDefault();
                    showSpamContentModal(spamBtn);
                    return;
                }

                // 분석(재분석) 버튼 처리
                const analyzeBtn = event.target.closest('.analyze-btn');
                if (analyzeBtn && !analyzeBtn.disabled) {
                    event.preventDefault();
                    handleAnalysisClick(analyzeBtn);
                }
            });

            // 오디오 플레이어 로드 시 시간 초기화 (버그 수정)
            recordingsList.addEventListener('loadedmetadata', function(e) {
                if (e.target.tagName === 'AUDIO') {
                    e.target.currentTime = 0;
                    // 메타데이터 로드 후 duration 재확인
                    if (isNaN(e.target.duration) || e.target.duration === 0) {
                        // 강제로 메타데이터 재로드
                        setTimeout(() => {
                            e.target.load();
                        }, 100);
                    }
                    updateAudioTimeDisplay(e.target);
                }
            }, true);

            // 오디오 시간 업데이트 이벤트
            recordingsList.addEventListener('timeupdate', function(e) {
                if (e.target.tagName === 'AUDIO') {
                    updateAudioTimeDisplay(e.target);
                }
            }, true);
        }

        // 오디오 시간 표시 업데이트 함수
        function updateAudioTimeDisplay(audio) {
            // 브라우저의 기본 컨트롤을 사용하므로 별도 처리 불필요
            // 하지만 NaN 문제를 방지하기 위한 체크 추가
            if (isNaN(audio.duration) || audio.duration === 0) {
                // 오디오 재로드 시도
                audio.load();
                
                // 모바일에서 사용자 상호작용 후 재시도
                if (window.innerWidth <= 768) {
                    audio.addEventListener('canplaythrough', function onCanPlay() {
                        audio.removeEventListener('canplaythrough', onCanPlay);
                        if (isNaN(audio.duration)) {
                            console.warn('Audio duration still NaN after reload:', audio.src);
                        }
                    }, { once: true });
                }
            }
        }

        // 시간 포맷팅 함수
        function formatTime(seconds) {
            if (isNaN(seconds) || seconds === Infinity) return '0:00';
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }

        // 토스트 알림 함수
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast-notification ' + (isError ? 'error' : 'success');
            toast.style.display = 'block';
                            
                            setTimeout(() => {
                toast.style.display = 'none';
                            }, 3000);
        }

        // 새로고침 버튼 이벤트 리스너 (버튼이 존재할 때만)
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                this.blur(); // 버튼에서 포커스 제거하여 pressed 상태 해제
                getRecordings();
            });
        }

        // 삭제 버튼 클릭 처리 함수
        async function handleDeleteClick(button) {
            const recordingFile = button.dataset.file;
            const callType = button.dataset.type || 'unsubscribe';
            if (!recordingFile) return;

            // 이미 삭제 중인 경우 중복 실행 방지
            if (button.disabled) {
                console.log('Delete already in progress for:', recordingFile);
                return;
            }

            const confirmed = await modernConfirm({
                message: '정말 이 녹음과 분석 결과를 삭제하시겠습니까?',
                title: '삭제 확인',
                confirmText: '삭제',
                cancelText: '취소',
                dangerConfirm: true
            });
            
            if (!confirmed) {
                return;
            }

            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '삭제중...';

            fetch('delete_recording.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'file=' + encodeURIComponent(recordingFile) + '&type=' + encodeURIComponent(callType)
            })
                .then(response => response.json())
                .then(data => {
                if (data.success) {
                    showToast('삭제되었습니다.');
                    const item = button.closest('.recording-item');
                    if (item) {
                        // DOM에서 즉시 제거하여 추가 요청 방지
                        item.remove();
                    }
                    // 삭제 성공 후 목록 새로고침
                    setTimeout(() => {
                        getRecordings();
                    }, 500);
                } else {
                    showToast('삭제 실패: ' + (data.errors ? data.errors.join(', ') : '알 수 없는 오류'), true);
                    button.disabled = false;
                    button.innerHTML = originalContent;
                    }
                })
                .catch(error => {
                console.error('Delete error:', error);
                showToast('삭제 중 오류가 발생했습니다.', true);
                button.disabled = false;
                button.innerHTML = originalContent;
                });
        }

        function createCallProgressUI(recordingItem) {
            // 기존 Call Progress UI가 있으면 제거
            const existingCallProgress = recordingItem.querySelector('.call-progress');
            if (existingCallProgress) {
                console.log('Removing existing call progress UI');
                existingCallProgress.remove();
            }
            
            const html = `
            <div class="call-progress" style="margin-top:10px;padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="call-status" style="color:#0369a1;font-weight:600;">통화 연결중...</span>
                    <span class="call-duration" style="color:#0369a1;font-weight:600;">0s</span>
                </div>
                <div class="progress-bar" style="background:#e0f2fe;height:6px;border-radius:4px;margin-top:8px;overflow:hidden;">
                    <div class="progress-fill" style="background:#0ea5e9;width:0;height:100%;transition:width 0.3s;"></div>
                </div>
                <div class="call-log"></div>
            </div>`;
            recordingItem.insertAdjacentHTML('beforeend', html);
            return recordingItem.querySelector('.call-progress');
        }

        function trackCallProgress(recordingItem, filename) {
            let progressEl = recordingItem.querySelector('.call-progress');
            if (!progressEl) {
                progressEl = createCallProgressUI(recordingItem);
            }
            
            // 실제 녹음 파일명 추출 (audio src에서)
            const audioEl = recordingItem.querySelector('audio');
            let actualFilename = filename;
            if (audioEl && audioEl.src) {
                const srcMatch = audioEl.src.match(/file=([^&]+)/);
                if (srcMatch) {
                    actualFilename = decodeURIComponent(srcMatch[1]);
                    console.log('Using actual filename from audio src:', actualFilename);
                }
            }

            // 로그 메시지를 친절한 한국어로 변환하는 헬퍼
            function translateCallLog(msg){
                if(!msg) return '';
                msg = msg.trim();
                if(msg.startsWith('RECORDING_START')) return '녹음 시작';
                if(msg.startsWith('RECORDING_END'))   return '녹음 종료';
                if(msg.startsWith('SENDING FIRST DTMF'))  return '식별번호 전송 중';
                if(msg.startsWith('SENDING SECOND DTMF')) return '확인 DTMF 전송 중';
                if(msg.startsWith('DTMF_CONFIRMED'))      return 'DTMF 확인됨';
                if(msg.includes('STT'))                   return '음성 인식 중';
                if(msg.includes('TRANSCRIBE')||msg.includes('TRANSCRIPTION')) return '음성 텍스트 변환 중';
                if(msg.includes('ANALYSIS'))              return '분석 중';
                if(msg.includes('TRIGGER'))               return '분석 트리거';
                if(msg.includes('WAITING') || msg.includes('IVR')) return '음성 안내 대기 중';
                if(msg.startsWith('CALL_FINISHED')||msg.startsWith('HANGUP')) return '통화 종료';
                if(msg.startsWith('FIRST_DTMF_SENT'))  return '식별번호 전송 완료';
                if(msg.startsWith('SECOND_DTMF_SENT')) return '확인 DTMF 전송 완료';
                if(msg.startsWith('UNSUB_success'))     return '수신거부 성공';
                if(msg.startsWith('UNSUB_failed'))      return '수신거부 실패';
                if(msg.startsWith('STT_START'))         return '음성 인식 시작';
                if(msg.startsWith('STT_DONE'))          return '음성 인식 완료';
                return msg; // 기본: 원본 유지
            }

            const statusEl = progressEl.querySelector('.call-status');
            const durEl = progressEl.querySelector('.call-duration');
            const fillEl = progressEl.querySelector('.progress-fill');
            const logEl  = progressEl.querySelector('.call-log');

            const poll = () => {
                console.log('Polling call progress for filename:', actualFilename, '(original:', filename, ')');
                fetch(`get_call_progress.php?file=${encodeURIComponent(actualFilename)}`)
                    .then(r=>r.json())
                    .then(data=>{
                        console.log('Call progress response:', data);
                        if(!data.exists){
                            statusEl.textContent='녹음 대기중...';
                            
                            // 대안: call detail로 상태 확인 (녹음 파일이 없어도)
                            // ID는 원본 filename에서 추출 (actualFilename에는 ID가 없을 수 있음)
                            const m = filename.match(/-ID_([A-Za-z0-9]+)/);
                            if (m) {
                                fetch(`get_call_detail.php?id=${m[1]}&lines=5`)
                                .then(r => r.json())
                                .then(d => {
                                    if (d.success && d.lines && d.lines.length > 0) {
                                        // 로그가 있으면 통화 진행 중
                                        const lastRaw = d.lines[d.lines.length-1];
                                        const lastMsg = lastRaw.substring(lastRaw.indexOf(']')+2);
                                        statusEl.textContent = translateCallLog(lastMsg) || '통화 진행 중...';
                                        
                                        // 진행률도 시간 기반으로 추정 (시작 시각이 있는 경우에만)
                                        if (d.lines.length > 0) {
                                            const firstLine = d.lines[0];
                                            const timeMatch = firstLine.match(/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/);
                                            if (timeMatch) {
                                                const startTime = new Date(timeMatch[1]);
                                                const elapsed = Math.max((Date.now() - startTime.getTime()) / 1000, 1);
                                                const estimatedPercent = Math.min((elapsed / 60) * 100, 90); // 최대 90%
                                                fillEl.style.width = estimatedPercent + '%';
                                                durEl.textContent = Math.round(elapsed) + 's';
                                            }
                                        }
                                    } else {
                                        // 로그가 없으면 통화 시작 전
                                        statusEl.textContent='통화 준비중...';
                                        fillEl.style.width = '5%';
                                        durEl.textContent = '0s';
                                    }
                                }).catch(() => {
                                    // 로그 파일이 없으면 통화 시작 전
                                    statusEl.textContent='통화 준비중...';
                                    fillEl.style.width = '2%';
                                    durEl.textContent = '0s';
                                });
                            } else {
                                // ID가 없으면 기본 상태
                                statusEl.textContent='녹음 대기중...';
                                fillEl.style.width = '1%';
                                durEl.textContent = '0s';
                            }
                            
                            setTimeout(poll,2000);
                            return;
                        }
                        durEl.textContent=`${data.duration_est}s`;
                        const percent=Math.min((data.duration_est/40)*100,99);
                        fillEl.style.width=percent+'%';
                        // 통합된 call detail 체크 (상태 업데이트 + STT_DONE 감지)
                        (function checkCallDetailAndCompletion(){
                            const m = filename.match(/-ID_([A-Za-z0-9]+)/);
                            if(!m) {
                                // ID가 없으면 기본 폴링 계속
                                if (!data.finished) {
                                    setTimeout(poll, 2000);
                                }
                                return;
                            }
                            
                            fetch(`get_call_detail.php?id=${m[1]}&lines=20`)
                            .then(r=>r.json())
                            .then(d=>{
                                if(d.success && d.lines && d.lines.length){
                                    // 상태(마지막 줄) 업데이트
                                    const lastRaw = d.lines[d.lines.length-1];
                                    const lastMsg = lastRaw.substring(lastRaw.indexOf(']')+2);
                                    statusEl.textContent = translateCallLog(lastMsg);
                                    
                                    // 전체 로그 표시
                                    if(logEl){
                                        const text = d.lines.map(l=>l.substring(l.indexOf(']')+2)).join('\n');
                                        logEl.textContent = text;
                                        logEl.scrollTop = logEl.scrollHeight;
                                    }
                                    
                                    // STT_DONE 완료 상태 체크
                                    const hasSTTDone = d.lines.some(line => line.includes('STT_DONE'));
                                    if (hasSTTDone || data.finished) {
                                        console.log('Call finished detected - STT_DONE:', hasSTTDone, 'finished flag:', data.finished);
                                        statusEl.textContent = '통화 종료';
                                        fillEl.style.width = '100%';
                                        
                                        // Progress UI 제거 및 자동 분석 트리거
                                        setTimeout(() => {
                                            if (progressEl && progressEl.parentNode) {
                                                progressEl.remove();
                                            }
                                            autoAnalysisSet.delete(filename);
                                            
                                            // 녹음 목록 갱신으로 분석 UI 전환
                                            getRecordings();
                                        }, 2000);
                                        return; // 폴링 중단
                                    }
                                }
                                
                                // 완료되지 않았으면 계속 폴링
                                if (!data.finished) {
                                    setTimeout(poll, 2000);
                                }
                            }).catch(() => {
                                // 에러 발생시 기본 로직 사용
                                if (!data.finished) {
                                    setTimeout(poll, 2000);
                                }
                            });
                        })();
                    })
                    .catch(()=>setTimeout(poll,3000));
            };
            poll();
        }

        function updateProgressDisplay(progressData) {
            const progressBar = document.getElementById('analysisProgress');
            const progressText = document.getElementById('progressText');
            const progressMessage = document.getElementById('progressMessage');
            
            if (!progressBar || !progressText || !progressMessage) return;
            
            // 진행률 업데이트
            progressBar.style.width = progressData.percentage + '%';
            progressText.textContent = progressData.percentage + '%';
            
            // 진행 상태 메시지 업데이트
            progressMessage.textContent = progressData.message;
            
            // 단계별 진행상황 표시
            if (progressData.steps) {
                const stepsContainer = document.getElementById('analysisSteps');
                if (stepsContainer) {
                    let stepsHtml = '';
                    for (const [step, progress] of Object.entries(progressData.steps)) {
                        const stepName = {
                            'audio_processing': '오디오 처리',
                            'pattern_detection': '패턴 감지',
                            'pattern_analysis': '패턴 분석',
                            'saving': '결과 저장'
                        }[step] || step;
                        
                        stepsHtml += `
                            <div class="step-progress">
                                <div class="step-name">${stepName}</div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: ${progress}%" 
                                         aria-valuenow="${progress}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        ${progress}%
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    stepsContainer.innerHTML = stepsHtml;
                }
            }
            
            // 분석이 완료되면 프로그레스 바 숨기기
            if (progressData.completed) {
                setTimeout(() => {
                    const progressContainer = document.getElementById('progressContainer');
                    if (progressContainer) {
                        progressContainer.style.display = 'none';
                    }
                }, 2000);
            }
        }

        // 진행상황 체크 함수
        function checkPatternAnalysisProgress(analysisId) {
            if (!analysisId) {
                console.error('No analysis ID provided');
                return;
            }
            
            console.log('Checking progress for analysis:', analysisId);
            
            // 진행상황 컨테이너 표시
            const progressContainer = document.getElementById('progressContainer');
            if (progressContainer) {
                progressContainer.style.display = 'block';
            }
            
            // 진행상황 체크
            fetch(`get_pattern_analysis_progress.php?analysis_id=${analysisId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Progress data:', data);
                    
                    if (data.success) {
                        updateProgressDisplay(data);
                        
                        // 분석이 완료되지 않았으면 계속 체크
                        if (!data.completed) {
                            setTimeout(() => checkPatternAnalysisProgress(analysisId), 1000);
                        } else {
                            // 분석이 완료되면 3초 후에 페이지 새로고침
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    } else {
                        console.error('Progress check failed:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Progress check error:', error);
                });
        }

        // 진행상황 표시 업데이트
        function updateProgressDisplay(progressData) {
            console.log('Updating progress display:', progressData);
            
            const progressBar = document.getElementById('analysisProgress');
            const progressText = document.getElementById('progressText');
            const progressMessage = document.getElementById('progressMessage');
            
            if (!progressBar || !progressText || !progressMessage) {
                console.error('Progress display elements not found');
                return;
            }
            
            // 진행률 업데이트
            progressBar.style.width = progressData.percentage + '%';
            progressBar.setAttribute('aria-valuenow', progressData.percentage);
            progressText.textContent = progressData.percentage + '%';
            
            // 진행 상태 메시지 업데이트
            progressMessage.textContent = progressData.message;
            
            // 단계별 진행상황 표시
            if (progressData.steps) {
                const stepsContainer = document.getElementById('analysisSteps');
                if (stepsContainer) {
                    let stepsHtml = '';
                    for (const [step, progress] of Object.entries(progressData.steps)) {
                        const stepName = {
                            'audio_processing': '오디오 처리',
                            'pattern_detection': '패턴 감지',
                            'pattern_analysis': '패턴 분석',
                            'saving': '결과 저장'
                        }[step] || step;
                        
                        stepsHtml += `
                            <div class="step-progress" style="margin-bottom: 10px;">
                                <div class="step-name" style="margin-bottom: 5px;">${stepName}</div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: ${progress}%" 
                                         aria-valuenow="${progress}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        ${progress}%
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    stepsContainer.innerHTML = stepsHtml;
                }
            }
        }

        // 페이지 로드 시 진행상황 체크 시작
        document.addEventListener('DOMContentLoaded', function() {
            // 로그인 여부에 따라 녹음 목록 로드
            if (window.IS_LOGGED) {
                getRecordings();
            }
            
            // 인증 관련 이벤트 리스너 설정
            setupVerificationFlow();
        });

        // 패턴 분석 시작 함수
        function startPatternAnalysis(recordingFile) {
            console.log('Starting pattern analysis for file:', recordingFile);
            
            const formData = new FormData();
            formData.append('file', recordingFile);
            
            fetch('analyze_pattern_recording.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Analysis start response:', data);
                
                if (data.success) {
                    // 분석 ID를 URL에 추가하고 진행상황 체크 시작
                    const url = new URL(window.location.href);
                    url.searchParams.set('analysis_id', data.analysis_id);
                    window.history.pushState({}, '', url);
                    
                    checkPatternAnalysisProgress(data.analysis_id);
                } else {
                    console.error('Analysis start failed:', data.message);
                    modernError('패턴 분석 시작 실패: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Analysis start error:', error);
                modernError('패턴 분석 시작 중 오류가 발생했습니다.');
            });
        }

        // 펼침 상태 관리용 Set (localStorage 활용)
        const openTranscriptions = new Set(JSON.parse(localStorage.getItem('openTranscriptions') || '[]'));

        // 인증 플로우 설정
        function setupVerificationFlow() {
            const spamContent = document.getElementById('spamContent');
            const notificationPhone = document.getElementById('notificationPhone');
            const verificationSection = document.getElementById('verificationSection');
            const verificationCode = document.getElementById('verificationCode');
            const verifyMsg = document.getElementById('verifyMsg');
            const spamForm = document.getElementById('spamForm');
            
            // Guard: if verification elements not present (already logged in / desktop no section), skip setup
            if (!verificationSection || !verificationCode || !verifyMsg) {
                return;
            }

            let verificationCodeSent = false;
            let countdownTimer = null;
            
            
            // 인증번호 발송
            function sendVerificationCode(phoneNumber = null) {
                if (verificationCodeSent) return;
                
                const phone = phoneNumber || notificationPhone.value.trim();
                if (!phone) return;
                
                verifyMsg.className = 'verification-message verify-msg sending';
                verifyMsg.textContent = '인증번호를 발송하고 있습니다...';
                
                fetch('/api/send_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        verificationCodeSent = true;
                        verifyMsg.className = 'verification-message verify-msg success';
                        verifyMsg.textContent = '인증번호가 발송되었습니다. (유효시간: 10분)';
                        startCountdown(600); // 10분
                        verificationCode.focus();
                    } else {
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = data.message || '인증번호 발송에 실패했습니다.';
                    }
                })
                .catch(error => {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '오류가 발생했습니다: ' + error.message;
                });
            }
            
            // 카운트다운 타이머
            function startCountdown(seconds) {
                const countdownElement = document.getElementById('verifyCountdown');
                let remaining = seconds;
                
                countdownTimer = setInterval(() => {
                    const minutes = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    countdownElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
                    
                    if (remaining <= 0) {
                        clearInterval(countdownTimer);
                        countdownElement.textContent = '시간 만료';
                        verificationCodeSent = false;
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = '인증번호가 만료되었습니다. 다시 시도해주세요.';
                    }
                    remaining--;
                }, 1000);
            }
            
            // 인증번호 확인
            function verifyCode() {
                const code = verificationCode.value.trim();
                const phone = notificationPhone.value.trim();
                
                if (!code || !phone) {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '인증번호를 입력해주세요.';
                    return;
                }
                
                verifyMsg.className = 'verification-message verify-msg checking';
                verifyMsg.textContent = '인증번호를 확인하고 있습니다...';
                
                fetch('/api/verify_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone: phone, code: code })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 인증 성공 - 로그인 상태로 변경
                        window.IS_LOGGED = true;
                        window.CUR_PHONE = phone;
                        
                        verifyMsg.className = 'verification-message verify-msg success';
                        verifyMsg.textContent = '인증이 완료되었습니다!';
                        
                        // 인증 섹션 숨기기
                        setTimeout(() => {
                            verificationSection.style.display = 'none';
                        }, 2000);
                        
                        // 녹음 목록 새로고침
                        getRecordings();
                        
                        // 카운트다운 타이머 정리
                        if (countdownTimer) {
                            clearInterval(countdownTimer);
                        }
                        
                        // 자동으로 메인 폼 제출 (인증 완료 후)
                        setTimeout(() => {
                            verifyMsg.textContent = '인증 완료! 수신거부 처리를 시작합니다...';
                            // 메인 폼 제출
                            const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                            spamForm.dispatchEvent(submitEvent);
                        }, 1000);
                    } else {
                        verifyMsg.className = 'verification-message verify-msg error';
                        verifyMsg.textContent = data.message || '인증번호가 올바르지 않습니다.';
                    }
                })
                .catch(error => {
                    verifyMsg.className = 'verification-message verify-msg error';
                    verifyMsg.textContent = '오류가 발생했습니다: ' + error.message;
                });
            }
            
            // 이벤트 리스너 등록
            verificationCode.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    verifyCode();
                }
            });
            
        }

    // 페이지 언로드 시 진행 중인 분석 저장
    window.addEventListener('beforeunload', function() {
        if (typeof persistActiveAnalyses === 'function') {
            persistActiveAnalyses();
        }
    });

    // 디버그용 전역 함수 (개발 환경에서만 사용)
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.debugRecordings = function() {
            fetch('get_recordings.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Current recordings:', data);
                    if (data.recordings) {
                        console.log('Ready for analysis:', data.recordings.filter(r => r.ready_for_analysis));
                        console.log('In progress:', data.recordings.filter(r => r.analysis_result === '실패' && r.analysis_text === '아직 분석되지 않았습니다.'));
                    }
                });
        };
        
        window.debugActiveAnalyses = function() {
            console.log('Active analyses:', [...activeAnalysisMap]);
            console.log('Auto analysis set:', [...autoAnalysisSet]);
        };
    }

    // 회원 탈퇴 확인 함수
    window.confirmAccountDeletion = function() {
        showCustomConfirm(
            '⚠️ 회원 탈퇴 확인',
            `정말로 탈퇴하시겠습니까?
            
• 계정 정보와 통화 기록이 모두 삭제됩니다
• 생성한 패턴은 익명화되어 다른 사용자를 위해 보존됩니다
• 이 작업은 되돌릴 수 없습니다

계속하려면 아래 입력란에 'DELETE_MY_ACCOUNT'를 입력하세요:`,
            '탈퇴하기',
            function() {
                const confirmInput = document.getElementById('confirm-input');
                if (confirmInput && confirmInput.value === 'DELETE_MY_ACCOUNT') {
                    deleteAccount();
                } else {
                    showCustomAlert('오류', '확인 문구가 일치하지 않습니다.', 'error');
                }
            },
            true // 텍스트 입력 필드 포함
        );
    };

    // 회원 탈퇴 API 호출
    function deleteAccount() {
        showCustomAlert('처리 중', '탈퇴 처리 중입니다...', 'info');
        
        fetch('api/delete_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'confirm_token=DELETE_MY_ACCOUNT'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showCustomAlert(
                    '탈퇴 완료', 
                    data.message + '\n\n잠시 후 로그인 페이지로 이동합니다.',
                    'success',
                    function() {
                        window.location.href = 'index.php';
                    }
                );
            } else {
                showCustomAlert('탈퇴 실패', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Account deletion error:', error);
            showCustomAlert('오류', '탈퇴 처리 중 오류가 발생했습니다.', 'error');
        });
    }

    // 스팸 문자 원본 모달 표시 함수
    function showSpamContentModal(button) {
        const spamContentEncoded = button.dataset.spamContent;
        const spamDate = button.dataset.spamDate;
        
        let content = '';
        if (spamContentEncoded) {
            try {
                content = decodeURIComponent(escape(atob(spamContentEncoded)));
            } catch (e) {
                content = '내용을 불러올 수 없습니다.';
            }
        }

        // Sanitize to prevent HTML injection & tag truncation
        const escapeHtml = (str) => str.replace(/&/g,'&amp;')
                                        .replace(/</g,'&lt;')
                                        .replace(/>/g,'&gt;')
                                        .replace(/"/g,'&quot;')
                                        .replace(/'/g,'&#39;');
        const safeContent = escapeHtml(content).replace(/\n/g,'<br>');

        const formattedDate = spamDate ? new Date(spamDate).toLocaleString('ko-KR') : '날짜 정보 없음';

        showCustomAlert(
            '📱 스팸문자 원본',
            `**수신 시간:** ${formattedDate}<br><br>**내용:**<br>${safeContent}`,
            'info'
        );
    }
