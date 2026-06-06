import './bootstrap';

// PWA: register the service worker (Stage 3.2). Failures are non-fatal — the app
// works identically without it; the SW only adds installability + offline shell.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
