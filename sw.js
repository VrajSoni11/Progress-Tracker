// Service Worker - Simple & Working
console.log('[SW] Loading...');

// Install
self.addEventListener('install', event => {
    console.log('[SW] Installed');
    self.skipWaiting();
});

// Activate
self.addEventListener('activate', event => {
    console.log('[SW] Activated');
    return self.clients.claim();
});

// Show notification when received
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
    
    let data = {
        title: 'Progress Tracker',
        body: 'You have a notification!',
        icon: 'icon.png'
    };
    
    try {
        data = event.data.json();
    } catch (e) {
        console.log('[SW] Using default notification');
    }
    
    const options = {
        body: data.body,
        icon: data.icon || 'icon.png',
        badge: 'badge.png',
        vibrate: [200, 100, 200],
        data: { url: data.url || 'dashboard.php' }
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked');
    event.notification.close();
    
    const url = event.notification.data.url || 'dashboard.php';
    
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(windowClients => {
            for (let client of windowClients) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
