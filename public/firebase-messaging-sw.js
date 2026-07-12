// Firebase Cloud Messaging Service Worker
// Urban Goodz - Background Push Notification Handler

importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

// Firebase config is injected at runtime from business_settings
// This file serves as the base service worker template
firebase.initializeApp({
  messagingSenderId: self.__FIREBASE_MESSAGING_SENDER_ID__ || '',
  projectId: self.__FIREBASE_PROJECT_ID__ || '',
  appId: self.__FIREBASE_APP_ID__ || '',
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
  const title = payload.data?.title || payload.notification?.title || 'Urban Goodz';
  const body = payload.data?.body || payload.notification?.body || '';
  const url = payload.data?.url || '/';

  self.registration.showNotification(title, {
    body: body,
    icon: payload.data?.image || '/public/assets/admin/img/logo/fav.png',
    badge: '/public/assets/admin/img/logo/fav.png',
    data: { url: url },
    tag: payload.data?.type || 'urban-goodz-notification',
  });
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
      for (let i = 0; i < windowClients.length; i++) {
        if (windowClients[i].url.includes(url) && 'focus' in windowClients[i]) {
          return windowClients[i].focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
