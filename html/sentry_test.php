<?php
require_once __DIR__.'/vendor/autoload.php';

\Sentry\init([
    'dsn' => 'https://99bfc49828a970db5b55592d413e1562@o4509563438366720.ingest.us.sentry.io/4509563477164032',
]);

try {
    // Test error capture
    $this->functionFailsForSure();
} catch (\Throwable $exception) {
    \Sentry\captureException($exception);
    echo "Error captured and sent to Sentry!\n";
}

// Test manual message
\Sentry\captureMessage("Sentry PHP SDK test message", \Sentry\Severity::info());
echo "Test message sent to Sentry!\n";
?>