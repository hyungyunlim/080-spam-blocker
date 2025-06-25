<?php
/**
 * CallProcessor
 *
 * Thin wrapper around process_v2.php so that other PHP classes can trigger
 * an auto-unsubscribe call in a consistent way (both CLI and web context).
 *
 * Usage:
 *   $cp = new CallProcessor();
 *   $resultText = $cp->makeCall($identificationNumber, $target080, $notificationPhone);
 */
class CallProcessor
{
    /**
     * Launches the unsubscribe call via process_v2.php in CLI mode and returns
     * the captured output (stdout + stderr).
     *
     * @param string $identificationNumber  식별 번호(ID) 또는 010 번호 – DTMF에 삽입될 값
     * @param string $target080Number       080 차단 대상 번호 (하이픈 없이)
     * @param string $notificationPhone     완료/실패 알림을 받을 휴대폰 번호
     * @return string                       실행 결과 메시지
     */
    public function makeCall(string $identificationNumber, string $target080Number, string $notificationPhone = '01000000000'): string
    {
        $id        = preg_replace('/[^0-9]/', '', $identificationNumber);
        $target080 = preg_replace('/[^0-9]/', '', $target080Number);
        $notify    = preg_replace('/[^0-9]/', '', $notificationPhone);

        if (!$target080) {
            return '오류: 대상 080 번호가 유효하지 않습니다.';
        }

        // Build CLI command for process_v2.php
        $cmd = sprintf(
            'php %s/process_v2.php --auto --phone=%s --id=%s --notification=%s 2>&1',
            __DIR__,  // same directory as this file & process_v2.php
            escapeshellarg($target080),
            escapeshellarg($id),
            escapeshellarg($notify)
        );

        // Execute and capture output
        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);
        return ($exitCode === 0 ? $output : "실패(exit {$exitCode}):\n" . $output);
    }
} 