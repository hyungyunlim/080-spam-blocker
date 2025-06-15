<?php /* Spam input form card extracted from index.php */ ?>
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

            <!-- 인증 단계 (비로그인시 노출) -->
            <div id="verificationSection" class="verification-container">
                <div class="verification-header">
                    <div class="verification-icon">📱</div>
                    <div class="verification-title">
                        <h3>휴대폰 인증</h3>
                        <p>서비스 이용을 위해 휴대폰 인증이 필요합니다</p>
                    </div>
                </div>
                
                <div class="verification-content">
                    <div class="verification-input-group">
                        <label for="verificationCode">인증번호 입력</label>
                        <div class="verification-input-wrapper">
                            <input id="verificationCode" 
                                   type="text" 
                                   maxlength="6" 
                                   placeholder="6자리 인증번호를 입력하세요"
                                   autocomplete="one-time-code">
                            <span id="verifyCountdown" class="countdown-timer"></span>
                        </div>
                        <div class="verification-help">
                            <span>📞 입력하신 연락처로 인증번호가 전송됩니다</span>
                        </div>
                    </div>
                    
                    <div class="verification-actions">
                        <button type="button" id="verifyBtn" class="btn verification-btn">
                            <span class="btn-icon">✓</span>
                            <span class="btn-text">인증하기</span>
                        </button>
                    </div>
                    
                    <div id="verifyMsg" class="verification-message"></div>
                </div>
            </div>

            <button type="submit" class="btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone-outbound" viewBox="0 0 16 16">
                    <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459l-4.682-4.682a1.75 1.75 0 0 1-.459-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.28 1.494l-.547 2.19a.5.5 0 0 0 .178.643l2.457 2.457a.5.5 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.28l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.363-1.03-.038-2.137.703-2.877zM11 .5a.5.5 0 0 1 .5.5V3h2.5a.5.5 0 0 1 0 1H11.5v2.5a.5.5 0 0 1-1 0V4H8a.5.5 0 0 1 0-1h2.5V1a.5.5 0 0 1 .5-.5"/>
                </svg>
                수신거부 전화 걸기
            </button>
        </form>

        <!-- 결과 표시 영역 -->
        <div id="resultArea" class="result-box" style="display: none;"></div>

        <!-- 실시간 상태 표시 영역 -->
        <div id="discovery-status-container" style="margin-top: 15px;"></div>
    </div>
</div> 