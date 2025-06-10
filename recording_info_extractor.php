<?php
/**
 * 녹음 파일명에서 알림 전송에 필요한 정보를 추출하는 유틸리티
 */

class RecordingInfoExtractor {
    
    /**
     * 녹음 파일명에서 080 번호 추출
     * 예: 20240115-153025-FROM_SYSTEM-TO_0801234567.wav -> 080-1234-567
     */
    public function extract080Number($filename) {
        // TO_ 뒤의 080 번호 추출
        if (preg_match('/TO_080(\d{7,8})/', $filename, $matches)) {
            $digits = $matches[1];
            if (strlen($digits) === 7) {
                return "080-{$digits}";
            } elseif (strlen($digits) === 8) {
                return "080-" . substr($digits, 0, 4) . "-" . substr($digits, 4);
            }
        }
        
        // 직접 080 번호가 있는 경우
        if (preg_match('/080[0-9]{7,8}/', $filename, $matches)) {
            $number = $matches[0];
            if (strlen($number) === 10) {
                return substr($number, 0, 3) . "-" . substr($number, 3);
            } elseif (strlen($number) === 11) {
                return substr($number, 0, 3) . "-" . substr($number, 3, 4) . "-" . substr($number, 7);
            }
        }
        
        return null;
    }
    
    /**
     * AstDB에서 녹음과 관련된 알림 정보 조회
     * 타임스탬프를 기반으로 매칭 시도
     */
    public function getNotificationInfoFromDB($filename) {
        // 파일명에서 타임스탬프 추출
        if (preg_match('/^(\d{8}-\d{6})/', $filename, $matches)) {
            $timestamp = $matches[1];
            
            // AstDB에서 해당 시간대의 정보 조회
            $astDbCommand = "/usr/sbin/asterisk -rx \"database show CallFile\"";
            $output = shell_exec($astDbCommand);
            
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    // CallFile 항목에서 알림 정보 찾기
                    if (strpos($line, 'notification_phone') !== false) {
                        preg_match('/CallFile\/([^\/]+)\/notification_phone.*?:\s*(.+)/', $line, $phoneMatches);
                        if (isset($phoneMatches[1], $phoneMatches[2])) {
                            $callFileId = $phoneMatches[1];
                            $phone = trim($phoneMatches[2]);
                            
                            // 같은 CallFile ID의 다른 정보들 조회
                            $identificationNumber = $this->getFromAstDB("CallFile/{$callFileId}/identification_number");
                            
                            if (!empty($phone)) {
                                return [
                                    'notification_phone' => $phone,
                                    'identification_number' => $identificationNumber
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * AstDB에서 특정 키 값 조회
     */
    private function getFromAstDB($key) {
        $command = "/usr/sbin/asterisk -rx \"database get {$key}\"";
        $output = shell_exec($command);
        
        if ($output && preg_match('/Value:\s*(.+)/', $output, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * 녹음 파일명에서 모든 알림 정보 추출
     */
    public function extractAllInfo($filename) {
        $info = [
            'target_number' => $this->extract080Number($filename),
            'notification_phone' => null,
            'identification_number' => null,
            'recording_file' => $filename
        ];
        
        // AstDB에서 알림 정보 조회
        $dbInfo = $this->getNotificationInfoFromDB($filename);
        if ($dbInfo) {
            $info['notification_phone'] = $dbInfo['notification_phone'];
            $info['identification_number'] = $dbInfo['identification_number'];
        }
        
        return $info;
    }
}
?> 