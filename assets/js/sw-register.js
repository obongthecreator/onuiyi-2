/**
 * 120 Stand Inventory - Service Worker Registration
 */

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        var swUrl = stand120_ajax.plugin_url + 'assets/js/sw.js';

        navigator.serviceWorker.register(swUrl, { updateViaCache: 'none' })
            .then(function(registration) {
                // Check for updates on every page load
                registration.update();

                registration.onupdatefound = function() {
                    var installingWorker = registration.installing;
                    installingWorker.onstatechange = function() {
                        if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New SW installed while an old one was controlling.
                            // The new SW calls skipWaiting() so it will activate immediately.
                            // Clear old caches from the main thread as a safety net.
                            if (window.caches) {
                                caches.keys().then(function(names) {
                                    names.forEach(function(name) {
                                        if (name.startsWith('stand120-')) {
                                            caches.delete(name);
                                        }
                                    });
                                });
                            }
                        }
                    };
                };
            })
            .catch(function() {
                // SW registration failed — app works fine without it
            });

        // When a new SW takes over, reload to get fresh assets
        var refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', function() {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    });
}
