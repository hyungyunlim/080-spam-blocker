<?php /* System admin card extracted from index.php */ ?>
<!-- 시스템 관리 카드 -->
<div class="card">
    <div class="card-header">
        🛠️ 시스템 관리
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <a href="pattern_manager.php" class="btn" style="text-decoration: none; text-align: center;">
                🧠 패턴 매니저
            </a>
            <a href="sms_test.php" class="btn" style="text-decoration: none; text-align: center;">
                📱 SMS 보내기
            </a>
            <a href="patterns.json" target="_blank" class="btn btn-secondary" style="text-decoration: none; text-align: center;">
                📝 패턴 설정 보기
            </a>
        </div>
        <div style="margin-top: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; font-size: 14px; color: #666;">
            💡 <strong>새로운 기능:</strong> 이제 시스템이 새로운 080번호를 자동으로 학습합니다! 
            처음 보는 번호의 경우 먼저 패턴 파악 전화를 걸어 음성 시스템을 분석하고, 
            자동으로 최적화된 DTMF 패턴을 생성합니다.
        </div>
    </div>
</div> 