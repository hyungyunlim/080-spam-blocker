<?php /* Pattern summary card for main page */ ?>
<?php
// 패턴 매니저 로드
require_once __DIR__ . '/../pattern_manager.php';
$patternManager = new PatternManager();
if ($IS_ADMIN) {
    // 어드민은 모든 패턴 보기
    $userPatterns = $patternManager->getPatterns();
    $stats = $patternManager->getPatternStats();
} else {
    // 일반 사용자는 자신의 패턴만 보기
    $userPatterns = $patternManager->getUserPatterns($CUR_PHONE);
    $stats = $patternManager->getPatternStats($CUR_PHONE);
}
?>

<!-- 패턴 요약 카드 -->
<div class="card">
    <div class="card-header">
        🧠 <?php echo $IS_ADMIN ? '전체 패턴 요약' : '내 패턴 요약'; ?>
        <div style="display: flex; gap: 8px;">
            <a href="pattern_manager.php" class="btn btn-small btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                패턴 관리
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- 통계 요약 -->
        <div class="pattern-stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['total_patterns']; ?></div>
                <div class="stat-label">전체 패턴</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['verified']; ?></div>
                <div class="stat-label">검증 완료</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['auto_generated']; ?></div>
                <div class="stat-label">자동 생성</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['needs_verification']; ?></div>
                <div class="stat-label">검증 필요</div>
            </div>
        </div>

        <!-- 최근 패턴 목록 -->
        <?php if (!empty($userPatterns['patterns'])): ?>
        <div class="recent-patterns">
            <h4 style="margin: 24px 0 16px 0; color: #4a5568; font-size: 1rem;">최근 패턴</h4>
            <div class="pattern-list">
                <?php 
                $recentPatterns = array_slice($userPatterns['patterns'], 0, 5);
                foreach ($recentPatterns as $number => $pattern): 
                    if ($number === 'default') continue;
                ?>
                <div class="pattern-item">
                    <div class="pattern-info">
                        <div class="pattern-number"><?php echo htmlspecialchars($number); ?></div>
                        <div class="pattern-name"><?php echo htmlspecialchars($pattern['name']); ?></div>
                        <?php if ($IS_ADMIN && isset($pattern['owner_phone']) && $pattern['owner_phone']): ?>
                        <div class="pattern-owner">👤 <?php echo htmlspecialchars($pattern['owner_phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="pattern-details">
                        <code class="pattern-dtmf"><?php echo htmlspecialchars($pattern['dtmf_pattern']); ?></code>
                        <div class="pattern-labels">
                            <?php if (isset($pattern['auto_generated']) && $pattern['auto_generated']): ?>
                                <span class="mini-label mini-label-auto">자동</span>
                            <?php endif; ?>
                            <?php if (isset($pattern['needs_verification']) && $pattern['needs_verification']): ?>
                                <span class="mini-label mini-label-unverified">검증필요</span>
                            <?php else: ?>
                                <span class="mini-label mini-label-verified">검증됨</span>
                            <?php endif; ?>
                            <?php if ($IS_ADMIN && !isset($pattern['owner_phone'])): ?>
                                <span class="mini-label mini-label-system">시스템</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <div class="empty-text">
                <h4>아직 등록된 패턴이 없습니다</h4>
                <p>스팸 수신거부 전화를 걸면 자동으로 패턴이 학습되거나, 직접 패턴을 추가할 수 있습니다.</p>
            </div>
            <a href="pattern_manager.php" class="btn" style="margin-top: 16px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                첫 패턴 추가하기
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* 패턴 요약 스타일 */
.pattern-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    padding: 16px 12px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.75rem;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.pattern-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pattern-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.pattern-item:hover {
    border-color: #cbd5e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.pattern-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pattern-number {
    font-weight: 700;
    color: #2d3748;
    font-size: 0.95rem;
}

.pattern-name {
    font-size: 0.85rem;
    color: #718096;
}

.pattern-owner {
    font-size: 0.75rem;
    color: #f59e0b;
    font-weight: 500;
}

.pattern-details {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.pattern-dtmf {
    background: #e2e8f0;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    color: #4a5568;
    font-family: 'Consolas', 'Monaco', monospace;
}

.pattern-labels {
    display: flex;
    gap: 4px;
}

.mini-label {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mini-label-auto {
    background: #dbeafe;
    color: #1e40af;
}

.mini-label-verified {
    background: #d1fae5;
    color: #065f46;
}

.mini-label-unverified {
    background: #fee2e2;
    color: #991b1b;
}

.mini-label-system {
    background: #f3f4f6;
    color: #6b7280;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.7;
}

.empty-text h4 {
    margin: 0 0 8px 0;
    color: #4a5568;
    font-size: 1.1rem;
}

.empty-text p {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

/* 모바일 반응형 */
@media (max-width: 768px) {
    .pattern-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .pattern-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .pattern-details {
        align-items: flex-start;
        width: 100%;
    }
    
    .pattern-labels {
        justify-content: flex-start;
    }
}
</style>