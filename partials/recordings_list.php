<?php /* Recordings list card extracted from index.php */ ?>
<!-- 녹음 파일 목록 카드 -->
<div class="card">
    <div class="card-header">
        🎙️ 녹음 파일 목록
        <div style="display: flex; gap: 8px;">
            <button id="refreshBtn" class="btn btn-small btn-secondary">
                🔄 새로고침
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="recordingsList" class="recordings-grid">
            <?php if (!$IS_LOGGED): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                🔐 로그인 후 녹음 파일을 확인할 수 있습니다
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                🎵 녹음 파일을 불러오는 중...
            </div>
            <?php endif; ?>
        </div>
    </div>
</div> 