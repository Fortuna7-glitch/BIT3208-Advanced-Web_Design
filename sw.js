// sw.js - Service Worker for Salon Pro PWA

const CACHE_NAME = 'salon-pro-v1';
const urlsToCache = [
'/saloon_system/',
'/saloon_system/index.php',
'/saloon_system/assets/css/style.css',
'/saloon_system/assets/images/logo.jpg',
'/saloon_system/assets/images/icon-192.jpg',
'/saloon_system/assets/images/icon-512.jpg',
'/saloon_system/assets/images/splash.jpg'
];

// Install event - cache assets
self.addEventListener('install', event => {
event.waitUntil(
    caches.open(CACHE_NAME)
    .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
    })
);
});

// Activate event - clean old caches
self.addEventListener('activate', event => {
const cacheWhitelist = [CACHE_NAME];
event.waitUntil(
    caches.keys().then(cacheNames => {
    return Promise.all(
        cacheNames.map(cacheName => {
        if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
        }
        })
    );
    })
);
});

// Fetch event - serve from cache if available, else fetch from network
self.addEventListener('fetch', event => {
event.respondWith(
    caches.match(event.request)
    .then(response => {
        // Cache hit - return response
        if (response) {
        return response;
        }
        return fetch(event.request);
    })
);
});