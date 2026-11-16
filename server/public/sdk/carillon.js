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
            if (newSession) refreshInApp(true);
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

  // ------------------------------------------------------- In-app messages

  var inAppMessages = [];
  var showingInApp = false;
  var inAppListeners = [];

  function refreshInApp(showOnOpen) {
    var id = store.get('subscription');
    if (!id) return Promise.resolve();
    return api('GET', '/subscriptions/' + id + '/in-app-messages')
      .then(function (result) {
        inAppMessages = (result && result.in_app_messages) || [];
        if (showOnOpen) presentInApp('app_open');
      })
      .catch(function () {});
  }

  /** Shows the first message due for this trigger, one at a time. */
  function presentInApp(trigger, eventName) {
    var id = store.get('subscription');
    if (!id || showingInApp || Carillon.inAppMessages.paused || !document.body) return;
    var shown = store.get('in_app') || {};
    var session = store.get('last_session') || 0;
    var now = Date.now();
    var message = inAppMessages.find(function (candidate) {
      var type = candidate.trigger && candidate.trigger.type;
      if (type !== trigger || (trigger === 'event' && candidate.trigger.event !== eventName)) return false;
      var last = shown[candidate.id];
      if (candidate.frequency === 'every_session') return !last || last.session !== session;
      if (candidate.frequency === 'every_days') return !last || now - last.at >= (candidate.frequency_days || 1) * 86400000;
      return !last;
    });
    if (!message) return;

    showingInApp = true;
    shown[message.id] = { at: now, session: session };
    store.set('in_app', shown);
    // Shown after a while on the page (the first screen stays visible).
    var delay = (message.trigger && message.trigger.delay_seconds) || 0;
    setTimeout(function () {
      if (Carillon.inAppMessages.paused) {
        showingInApp = false;
        return;
      }
      display();
    }, delay * 1000);

    function display() {
    report('shown');
    renderInApp(message, function (outcome, button) {
      showingInApp = false;
      report(outcome, button);
      if (outcome !== 'clicked' || !button) return;
      inAppListeners.forEach(function (listener) {
        listener(message.id, button);
      });
      if (button.action === 'url' && button.url) window.location.href = button.url;
    });
    }

    function report(event, button) {
      api('POST', '/in-app-messages/' + message.id + '/events', {
        subscription_id: id,
        event: event,
        button_id: button && button.id ? button.id : undefined,
        variant: message.variant || undefined,
      }).catch(function () {});
    }
  }

  /** Banner or dialog in a shadow root, so the styles of the site do not leak in. */
  function renderInApp(message, done) {
    var host = document.createElement('div');
    host.style.cssText = 'position:fixed;inset:0;z-index:2147483000;pointer-events:none';
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var bg = dark ? '#18181b' : '#ffffff';
    var fg = dark ? '#fafafa' : '#18181b';
    var muted = dark ? '#a1a1aa' : '#52525b';
    var banner = message.layout === 'banner';
    var fullscreen = message.layout === 'fullscreen';
    var style = document.createElement('style');
    style.textContent =
      '*{box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}' +
      '.backdrop{position:fixed;inset:0;background:rgba(9,9,11,.5);display:flex;align-items:center;justify-content:center;padding:24px;pointer-events:auto;animation:fade .2s ease-out}' +
      '.card{background:' + bg + ';color:' + fg + ';border-radius:14px;overflow:hidden;width:100%;max-width:380px;box-shadow:0 24px 48px -12px rgba(0,0,0,.35);animation:pop .28s cubic-bezier(.34,1.56,.64,1)}' +
      '.full{position:fixed;inset:0;max-width:none;border-radius:0;display:flex;flex-direction:column;justify-content:center}' +
      '.banner{position:fixed;top:12px;left:50%;transform:translateX(-50%);width:calc(100% - 24px);max-width:420px;background:' + bg + ';color:' + fg + ';border-radius:12px;padding:12px 40px 12px 16px;box-shadow:0 12px 32px -8px rgba(0,0,0,.3);pointer-events:auto;cursor:pointer;animation:slide .3s ease-out}' +
      'img{display:block;width:100%;max-height:220px;object-fit:cover}' +
      '.text{padding:20px 22px 6px;text-align:center}.banner .text{padding:0;text-align:left}' +
      'h2{margin:0 0 6px;font-size:18px;font-weight:700}.banner h2{font-size:14px;margin:0 0 2px}' +
      'p{margin:0;font-size:14px;line-height:1.45;color:' + muted + '}.banner p{font-size:13px}' +
      '.buttons{padding:14px 18px 18px;display:grid;gap:8px}' +
      'button.action{border:0;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;background:#E4531E;color:#fff}' +
      'button.action.secondary{background:transparent;color:' + muted + '}' +
      '.close{position:absolute;top:8px;right:8px;border:0;background:transparent;color:' + muted + ';font-size:20px;line-height:1;cursor:pointer;padding:4px 8px}' +
      '@keyframes fade{from{opacity:0}}@keyframes pop{from{opacity:0;transform:scale(.92)}}@keyframes slide{from{opacity:0;transform:translate(-50%,-120%)}}' +
      '@media (prefers-reduced-motion:reduce){*{animation:none!important}}';
    root.appendChild(style);

    function el(tag, className, text) {
      var node = document.createElement(tag);
      if (className) node.className = className;
      if (text) node.textContent = text;
      return node;
    }
    var finished = false;
    function close(outcome, button) {
      if (finished) return;
      finished = true;
      host.remove();
      done(outcome, button);
    }

    var text = el('div', 'text');
    if (message.title) text.appendChild(el('h2', '', message.title));
    text.appendChild(el('p', '', message.body || ''));

    if (banner) {
      var bar = el('div', 'banner');
      var x = el('button', 'close', '\u00d7');
      x.setAttribute('aria-label', 'Close');
      x.addEventListener('click', function (event) {
        event.stopPropagation();
        close('dismissed');
      });
      bar.appendChild(text);
      bar.appendChild(x);
      bar.addEventListener('click', function () {
        close('clicked', { id: 'banner' });
      });
      root.appendChild(bar);
      setTimeout(function () {
        close('dismissed');
      }, 7000);
    } else {
      var backdrop = el('div', 'backdrop');
      var card = el('div', fullscreen ? 'card full' : 'card');
      card.style.position = 'relative';
      if (message.image_url) {
        var img = el('img');
        img.src = message.image_url;
        img.alt = '';
        card.appendChild(img);
      }
      card.appendChild(text);
      var buttons = el('div', 'buttons');
      var list = message.buttons && message.buttons.length ? message.buttons : [{ id: 'close', text: 'OK', action: 'dismiss' }];
      list.forEach(function (button, index) {
        var node = el('button', index === 0 ? 'action' : 'action secondary', button.text);
        node.addEventListener('click', function () {
          close('clicked', button);
        });
        buttons.appendChild(node);
      });
      card.appendChild(buttons);
      var x2 = el('button', 'close', '\u00d7');
      x2.setAttribute('aria-label', 'Close');
      x2.addEventListener('click', function () {
        close('dismissed');
      });
      card.appendChild(x2);
      backdrop.appendChild(card);
      if (!fullscreen) {
        backdrop.addEventListener('click', function (event) {
          if (event.target === backdrop) close('dismissed');
        });
      }
      root.appendChild(backdrop);
    }
    document.body.appendChild(host);
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

    /** Banners and pop-ups designed in Carillon. */
    inAppMessages: {
      paused: false,
      addClickListener: function (listener) {
        inAppListeners.push(listener);
      },
    },

    /** Outcomes, as OneSignal.Session: conversions attributed to notifications. */
    session: {
      addOutcome: function (name) {
        return Carillon.user.trackEvent(name);
      },
      addOutcomeWithValue: function (name, value) {
        return withSubscription(function (id) {
          return api('POST', '/subscriptions/' + id + '/custom-events', { name: String(name), value: Number(value) });
        });
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

      /** Adds an email address to this person, for the email channel. */
      addEmail: function (email) {
        return withSubscription(function (id) {
          return api('POST', '/subscriptions/' + id + '/email', { email: String(email).trim().toLowerCase() });
        });
      },

      removeEmail: function (email) {
        return withSubscription(function (id) {
          return api('DELETE', '/subscriptions/' + id + '/email', { email: String(email).trim().toLowerCase() });
        });
      },

      /** Adds a phone number (international format, +2250700000000), for SMS. */
      addSms: function (phoneNumber) {
        return withSubscription(function (id) {
          return api('POST', '/subscriptions/' + id + '/sms', { phone_number: String(phoneNumber).trim() });
        });
      },

      /** Adds a WhatsApp number, once the visitor agreed to receive messages there. */
      addWhatsApp: function (phoneNumber) {
        return withSubscription(function (id) {
          return api('POST', '/subscriptions/' + id + '/whatsapp', { phone_number: String(phoneNumber).trim() });
        });
      },

      removeWhatsApp: function (phoneNumber) {
        return withSubscription(function (id) {
          return api('DELETE', '/subscriptions/' + id + '/whatsapp', { phone_number: String(phoneNumber).trim() });
        });
      },

      removeSms: function (phoneNumber) {
        return withSubscription(function (id) {
          return api('DELETE', '/subscriptions/' + id + '/sms', { phone_number: String(phoneNumber).trim() });
        });
      },

      /** Reports something the visitor did (purchase…): starts journeys, counted as a conversion. */
      trackEvent: function (name, properties) {
        return withSubscription(function (id) {
          return api('POST', '/subscriptions/' + id + '/custom-events', { name: String(name), properties: properties || undefined }).then(function () {
            presentInApp('event', String(name));
          });
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
