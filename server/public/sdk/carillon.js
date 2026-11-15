/*!
 * Carillon web SDK 0.1.0 — push notifications for websites.
 * https://github.com/TelKine-Studio/carillon — MIT license
 *
 *   <script src="https://carillon.example.com/sdk/carillon.js" defer></script>
 *   <script>
 *     window.CarillonDeferred = window.CarillonDeferred || [];
 *     CarillonDeferred.push(async (Carillon) => {
 *       await Carillon.init({ appId: 'YOUR-APP-ID' });
 *     });
 *   </script>
 *
 * Your site also serves /carillon-sw.js with one line:
 *   importScripts('https://carillon.example.com/sdk/carillon-sw.js');
 */
(function () {
  'use strict';

  var SDK = 'carillon-web/0.1.0';
  var PREFIX = 'carillon.';
  var SESSION_GAP = 30 * 60 * 1000;
  var scriptOrigin = (function () {
    try {
      return new URL(document.currentScript.src).origin;
    } catch (e) {
      return null;
    }
  })();

  // ---------------------------------------------------------------- storage

  var store = {
    get: function (key) {
      try {
        var raw = localStorage.getItem(PREFIX + key);
        return raw === null ? null : JSON.parse(raw);
      } catch (e) {
        return null;
      }
    },
    set: function (key, value) {
      try {
        if (value === null || value === undefined) localStorage.removeItem(PREFIX + key);
        else localStorage.setItem(PREFIX + key, JSON.stringify(value));
      } catch (e) {}
    },
  };

  // ------------------------------------------------------------------ state

  var options = null;
  var config = null;
  var registration = null;
  var queue = Promise.resolve();
  var clickListeners = [];
  var pendingClicks = [];
  var permissionListeners = [];

  function supported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  function base() {
    return options.serverUrl.replace(/\/+$/, '') + '/api/v1/sdk/apps/' + options.appId;
  }

  function api(method, path, body) {
    return fetch(base() + path, {
      method: method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
      keepalive: method !== 'GET',
    }).then(function (response) {
      return response.text().then(function (text) {
        var json = text ? JSON.parse(text) : {};
        if (!response.ok) {
          var error = new Error((json && (json.message || (json.errors && json.errors[0]))) || 'HTTP ' + response.status);
          error.status = response.status;
          throw error;
        }
        return json;
      });
    });
  }

  /** Runs the calls to the server one after the other. */
  function serial(task) {
    queue = queue.then(task, task).catch(function (error) {
      console.warn('[carillon]', error);
    });
    return queue;
  }

  function permission() {
    if (!('Notification' in window)) return 'denied';
    return Notification.permission === 'default' ? 'not_determined' : Notification.permission;
  }

  function urlBase64ToUint8Array(value) {
    var padding = '='.repeat((4 - (value.length % 4)) % 4);
    var raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    var output = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
    return output;
  }

  function browser() {
    var ua = navigator.userAgent;
    var match = ua.match(/(Edg|OPR|Firefox|Chrome|Version)\/(\d+)/);
    var name = /Edg\//.test(ua) ? 'Edge' : /OPR\//.test(ua) ? 'Opera' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
    return name + (match ? ' ' + match[2] : '');
  }

  function device() {
    var locale = (navigator.language || 'en').split('-');
    var timezone = null;
    try {
      timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {}
    var os = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || null;
    return {
      language: locale[0].toLowerCase(),
      country: locale[1] && locale[1].length === 2 ? locale[1].toUpperCase() : undefined,
      timezone: timezone || undefined,
      device_model: browser(),
      device_os: os ? String(os).slice(0, 32) : undefined,
      sdk: SDK,
    };
  }

  // ------------------------------------------------------- push subscription

  function pushSubscription(create) {
    if (!registration || !config || !config.web) return Promise.resolve(null);
    return registration.pushManager.getSubscription().then(function (existing) {
      if (existing || !create) return existing;
      return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(config.web.vapid_public_key),
      });
    });
  }

  /** Registers or updates this browser on the server. */
  function sync(session) {
    return serial(function () {
      var granted = permission() === 'granted';
      return pushSubscription(granted).then(function (subscription) {
        if (!subscription) return;

        var now = Date.now();
        var last = store.get('last_session');
        var newSession = Boolean(session) && (!last || now - last > SESSION_GAP);
        var body = Object.assign(device(), {
          token: JSON.stringify(subscription.toJSON()),
          enabled: granted && !store.get('opted_out'),
        });
        var id = store.get('subscription');

        var update = id
          ? api('PATCH', '/subscriptions/' + id, Object.assign({}, body, newSession ? { session: true } : {})).catch(function (error) {
              if (error.status !== 404) throw error;
              store.set('subscription', null);
              return null;
            })
          : Promise.resolve(null);

        return update
          .then(function (result) {
            if (store.get('subscription')) return result;
            var tags = store.get('tags') || {};
            return api(
              'POST',
              '/subscriptions',
              Object.assign({ type: 'web' }, body, {
                external_id: store.get('external_id') || undefined,
                tags: Object.keys(tags).length ? tags : undefined,
              })
            ).then(function (created) {
              store.set('subscription', created.id);
              return newSession ? api('PATCH', '/subscriptions/' + created.id, { session: true }) : created;
            });
          })
          .then(function (result) {
            if (newSession) store.set('last_session', now);
            absorb(result);
            share();
          });
      });
    });
  }

  function absorb(result) {
    if (result && result.tags && typeof result.tags === 'object') store.set('tags', result.tags);
  }

  /** Gives the service worker what it needs to report receptions and clicks. */
  function share() {
    if (!registration || !registration.active) return;
    registration.active.postMessage({
      type: 'carillon:config',
      server: options.serverUrl,
      app: options.appId,
      subscription: store.get('subscription'),
      vapid: config && config.web ? config.web.vapid_public_key : null,
    });
  }

  function emitClick(event) {
    if (!clickListeners.length) {
      pendingClicks.push(event);
      return;
    }
    clickListeners.slice().forEach(function (listener) {
      try {
        listener(event);
      } catch (e) {
        console.error(e);
      }
    });
  }

  function onWorkerMessage(message) {
    var data = message.data || {};
    if (data.type === 'carillon:click') emitClick({ notification: data.notification, actionId: data.action || null });
  }

  /** Clicks that opened a new tab, kept by the worker until a page asks. */
  function collectPendingClicks() {
    if (!registration || !registration.active) return;
    var channel = new MessageChannel();
    channel.port1.onmessage = function (message) {
      (message.data || []).forEach(function (click) {
        emitClick({ notification: click.notification, actionId: click.action || null });
      });
    };
    registration.active.postMessage({ type: 'carillon:pending' }, [channel.port2]);
  }

  function withSubscription(task) {
    return serial(function () {
      var id = store.get('subscription');
      return id ? task(id) : null;
    });
  }

  // ------------------------------------------------------------------ API

  var Carillon = {
    version: SDK,

    /**
     * options: appId (required), serverUrl (defaults to where this script is
     * served from), serviceWorkerPath ('/carillon-sw.js'), serviceWorkerScope.
     */
    init: function (opts) {
      if (options) return Promise.resolve();
      if (!opts || !opts.appId) return Promise.reject(new Error('Carillon.init: appId is required.'));
      options = Object.assign({ serverUrl: scriptOrigin, serviceWorkerPath: '/carillon-sw.js' }, opts);
      if (!options.serverUrl) return Promise.reject(new Error('Carillon.init: serverUrl is required.'));

      if (!supported()) {
        console.info('[carillon] Push notifications are not supported by this browser.');
        return Promise.resolve();
      }

      navigator.serviceWorker.addEventListener('message', onWorkerMessage);

      return api('GET', '/config')
        .then(function (result) {
          config = result;
          if (!config.web) console.warn('[carillon] Web push keys are not configured for this project.');
          var swOptions = options.serviceWorkerScope ? { scope: options.serviceWorkerScope } : undefined;
          return navigator.serviceWorker.register(options.serviceWorkerPath, swOptions);
        })
        .then(function () {
          return navigator.serviceWorker.ready;
        })
        .then(function (ready) {
          registration = ready;
          share();
          collectPendingClicks();
          if ('permissions' in navigator) {
            navigator.permissions.query({ name: 'notifications' }).then(function (status) {
              status.onchange = function () {
                sync(false);
                permissionListeners.forEach(function (listener) {
                  listener(permission() === 'granted');
                });
              };
            }).catch(function () {});
          }
          return sync(true);
        });
    },

    login: function (externalId) {
      store.set('external_id', String(externalId));
      return withSubscription(function (id) {
        return api('POST', '/subscriptions/' + id + '/login', { external_id: String(externalId) }).then(absorb);
      });
    },

    logout: function () {
      store.set('external_id', null);
      return withSubscription(function (id) {
        return api('POST', '/subscriptions/' + id + '/logout', {}).then(absorb);
      });
    },

    notifications: {
      isSupported: supported,

      /** "granted", "denied" or "not_determined". */
      get permission() {
        return permission();
      },

      /** Asks the visitor; call it after a click (browsers require a gesture). */
      requestPermission: function () {
        if (!supported()) return Promise.resolve(false);
        return Notification.requestPermission().then(function (result) {
          return sync(false).then(function () {
            return result === 'granted';
          });
        });
      },

      addClickListener: function (listener) {
        clickListeners.push(listener);
        var pending = pendingClicks.splice(0);
        pending.forEach(emitClick);
      },

      removeClickListener: function (listener) {
        clickListeners = clickListeners.filter(function (item) {
          return item !== listener;
        });
      },

      addPermissionChangeListener: function (listener) {
        permissionListeners.push(listener);
      },
    },

    user: {
      get externalId() {
        return store.get('external_id');
      },

      get tags() {
        return Object.assign({}, store.get('tags') || {});
      },

      addTag: function (key, value) {
        var tags = {};
        tags[key] = value;
        return Carillon.user.addTags(tags);
      },

      addTags: function (tags) {
        var clean = {};
        Object.keys(tags).forEach(function (key) {
          clean[key] = String(tags[key]);
        });
        store.set('tags', Object.assign(store.get('tags') || {}, clean));
        return withSubscription(function (id) {
          return api('PUT', '/subscriptions/' + id + '/tags', { tags: clean }).then(absorb);
        });
      },

      removeTag: function (key) {
        return Carillon.user.removeTags([key]);
      },

      removeTags: function (keys) {
        var current = store.get('tags') || {};
        var removed = {};
        keys.forEach(function (key) {
          delete current[key];
          removed[key] = null;
        });
        store.set('tags', current);
        return withSubscription(function (id) {
          return api('PUT', '/subscriptions/' + id + '/tags', { tags: removed }).then(absorb);
        });
      },

      pushSubscription: {
        get id() {
          return store.get('subscription');
        },

        get optedIn() {
          return permission() === 'granted' && !store.get('opted_out');
        },

        optOut: function () {
          store.set('opted_out', true);
          return sync(false);
        },

        optIn: function () {
          store.set('opted_out', false);
          return permission() === 'granted' ? sync(false) : Carillon.notifications.requestPermission();
        },
      },
    },
  };

  window.Carillon = Carillon;

  // Calls queued before the script loaded (CarillonDeferred.push(fn)).
  var deferred = window.CarillonDeferred;
  window.CarillonDeferred = {
    push: function (fn) {
      Promise.resolve().then(function () {
        return fn(Carillon);
      }).catch(function (error) {
        console.error('[carillon]', error);
      });
    },
  };
  if (Array.isArray(deferred)) deferred.forEach(window.CarillonDeferred.push);
})();
