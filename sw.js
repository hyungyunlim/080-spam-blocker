// Service Worker for 080 수신거부 자동화 시스템
const CACHE_NAME = 'spam-blocker-v2';
const urlsToCache = [
    '/',
    'assets/style.css?v=2',
    'assets/modal.css?v=2',
    'assets/app.js?v=2',
    'assets/modal.js?v=2',
    'login_flow.js?v=2'
];

// Install event - cache resources
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event - serve from cache
self.addEventListener('fetch', event => {
    // Only cache GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Don't cache API calls that need fresh data
    if (event.request.url.includes('get_recordings.php') || 
        event.request.url.includes('api/') ||
        event.request.url.includes('.php')) {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});