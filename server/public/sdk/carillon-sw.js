/*!
 * Carillon web SDK service worker 0.1.0 — https://github.com/TelKine-Studio/carillon
 *
 * Load it from the /carillon-sw.js file of your site:
 *   importScripts('https://carillon.example.com/sdk/carillon-sw.js');
 */
(function () {
  'use strict';

  // ------------------------------------------------- tiny key-value store

  function db() {
    return new Promise(function (resolve, reject) {
      var request = indexedDB.open('carillon', 1);
      request.onupgradeneeded = function () {
        request.result.createObjectStore('kv');
      };
      request.onsuccess = function () {
        resolve(request.result);
      };
      request.onerror = function () {
        reject(request.error);
      };
    });
  }

  function get(key) {
    return db().then(function (database) {
      return new Promise(function (resolve) {
        var request = database.transaction('kv').objectStore('kv').get(key);
        request.onsuccess = function () {
          resolve(request.result === undefined ? null : request.result);
        };
        request.onerror = function () {
          resolve(null);
        };
      });
    });
  }

  function set(key, value) {
    return db().then(function (database) {
      return new Promise(function (resolve) {
        var transaction = database.transaction('kv', 'readwrite');
        transaction.objectStore('kv').put(value, key);
        transaction.oncomplete = resolve;
        transaction.onerror = resolve;
      });
    });
  }

  // ------------------------------------------------------------ reporting

  function report(messageId, event, action) {
    if (!messageId) return Promise.resolve();
    return get('config').then(function (config) {
      if (!config || !config.server || !config.app || !config.subscription) return;
      var url = config.server.replace(/\/+$/, '') + '/api/v1/sdk/apps/' + config.app + '/subscriptions/' + config.subscription + '/events';
      var body = { message_id: messageId, event: event };
      if (action) body.action_id = action;
      return fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      }).catch(function () {});
    });
  }

  // ---------------------------------------------------------------- events

  self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'carillon:config') {
      event.waitUntil(set('config', { server: data.server, app: data.app, subscription: data.subscription, vapid: data.vapid }));
    } else if (data.type === 'carillon:pending' && event.ports && event.ports[0]) {
      event.waitUntil(
        get('pending').then(function (pending) {
          event.ports[0].postMessage(pending || []);
          return set('pending', []);
        })
      );
    }
  });

  self.addEventListener('push', function (event) {
    var payload;
    try {
      payload = event.data ? event.data.json() : null;
    } catch (e) {
      payload = null;
    }
    // Not a Carillon notification: left to the rest of the worker.
    if (!payload || !payload.id) return;

    var options = {
      body: payload.body || '',
      icon: payload.icon,
      image: payload.image,
      badge: payload.badge,
      tag: payload.tag,
      renotify: Boolean(payload.tag),
      requireInteraction: Boolean(payload.requireInteraction),
      data: { carillon: payload },
      actions: (payload.actions || []).slice(0, 2).map(function (button) {
        return { action: button.id, title: button.text, icon: button.icon };
      }),
    };

    event.waitUntil(Promise.all([self.registration.showNotification(payload.title || '', options), report(payload.id, 'received')]));
  });

  self.addEventListener('notificationclick', function (event) {
    var payload = event.notification.data && event.notification.data.carillon;
    if (!payload) return;
    event.notification.close();

    var action = event.action || null;
    var button = action && (payload.actions || []).filter(function (item) {
      return item.id === action;
    })[0];
    var target = (button && button.url) || payload.url || self.registration.scope;
    var click = {
      notification: { id: payload.id, title: payload.title, body: payload.body, url: payload.url, data: payload.data || {} },
      action: action,
    };

    event.waitUntil(
      Promise.all([
        report(payload.id, 'clicked', action),
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
          var url = new URL(target, self.registration.scope).href;
          var same = windows.filter(function (client) {
            return client.url === url;
          })[0];
          var mine = same || windows.filter(function (client) {
            return client.url.indexOf(self.registration.scope) === 0;
          })[0];

          // An open page of the site receives the click right away...
          if (mine && (same || !payload.url)) {
            mine.postMessage({ type: 'carillon:click', notification: click.notification, action: action });
            return mine.focus();
          }

          // ...a new tab gets it when the SDK starts there.
          return get('pending').then(function (pending) {
            return set('pending', (pending || []).concat([click]).slice(-10));
          }).then(function () {
            return self.clients.openWindow(url);
          });
        }),
      ])
    );
  });

  // The browser renewed the subscription: the server gets the new one.
  self.addEventListener('pushsubscriptionchange', function (event) {
    event.waitUntil(
      get('config').then(function (config) {
        if (!config || !config.vapid || !config.subscription) return;
        var key = config.vapid.replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(key + '='.repeat((4 - (key.length % 4)) % 4));
        var bytes = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
        return self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes }).then(function (subscription) {
          return fetch(config.server.replace(/\/+$/, '') + '/api/v1/sdk/apps/' + config.app + '/subscriptions/' + config.subscription, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: JSON.stringify(subscription.toJSON()) }),
          });
        });
      })
    );
  });
})();
