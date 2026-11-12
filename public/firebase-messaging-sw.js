importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyBpyoDbDmub-c_Y99fuILDCYzC_uy9sSlk",
    authDomain: "urbaneatz.firebaseapp.com",
    projectId: "urbaneatz",
    storageBucket: "urbaneatz.firebasestorage.app",
    messagingSenderId: "709013709032",
    appId: "1:709013709032:web:005e6ba3a9b138b041a95d",
    measurementId: "G-HP67NW7Q9G"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});