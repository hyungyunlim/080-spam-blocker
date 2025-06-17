<?php
/**
 * Performance optimization utilities
 */

class PerformanceOptimizer {
    
    /**
     * Set optimal cache headers for static resources
     */
    public static function setCacheHeaders($fileType = 'static') {
        $maxAge = 3600; // 1 hour default
        
        switch ($fileType) {
            case 'css':
            case 'js':
                $maxAge = 2592000; // 30 days
                header('Cache-Control: public, max-age=' . $maxAge);
                break;
            case 'image':
                $maxAge = 2592000; // 30 days
                header('Cache-Control: public, max-age=' . $maxAge);
                break;
            case 'api':
                $maxAge = 300; // 5 minutes
                header('Cache-Control: public, max-age=' . $maxAge);
                break;
            case 'dynamic':
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                break;
            default:
                header('Cache-Control: public, max-age=' . $maxAge);
        }
        
        // Add ETag for better caching
        if ($fileType !== 'dynamic') {
            $etag = '"' . md5($_SERVER['REQUEST_URI'] . filemtime(__FILE__)) . '"';
            header('ETag: ' . $etag);
            
            // Check if browser has cached version
            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNoneMatch === $etag) {
                http_response_code(304);
                exit;
            }
        }
    }
    
    /**
     * Enable output compression
     */
    public static function enableCompression() {
        if (extension_loaded('zlib') && !ob_get_level()) {
            ob_start('ob_gzhandler');
        }
    }
    
    /**
     * Minify HTML output
     */
    public static function minifyHTML($html) {
        // Remove comments (but keep IE conditionals)
        $html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);
        
        // Remove whitespace between tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Remove extra whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        return trim($html);
    }
    
    /**
     * Add performance monitoring script
     */
    public static function addPerformanceMonitoring() {
        return '
        <script>
        // Performance monitoring
        window.addEventListener("load", function() {
            if ("performance" in window) {
                const timing = performance.timing;
                const loadTime = timing.loadEventEnd - timing.navigationStart;
                const domReady = timing.domContentLoadedEventEnd - timing.navigationStart;
                
                // Send to analytics if available
                if (typeof gtag !== "undefined") {
                    gtag("event", "timing_complete", {
                        name: "load",
                        value: Math.round(loadTime)
                    });
                }
                
                // Log to console in development
                console.log("Page load time:", loadTime + "ms");
                console.log("DOM ready time:", domReady + "ms");
            }
        });
        </script>';
    }
}

// Auto-enable compression for all requests
PerformanceOptimizer::enableCompression();

?>