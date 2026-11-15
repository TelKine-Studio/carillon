# Carillon web SDK

Push notifications for websites (Chrome, Edge, Firefox, Safari 16.4+), with
the API of the OneSignal web SDK. The files are served by your Carillon
server: nothing to install.

## Set up

1. In *Project → Settings → Platforms → Web*, generate the VAPID keys.
2. Serve a `/carillon-sw.js` file at the root of your site:

   ```js
   importScripts('https://carillon.example.com/sdk/carillon-sw.js');
   ```

   If your site already has a service worker, add this line to it instead
   and pass its path as `serviceWorkerPath`.
3. Load the SDK:

   ```html
   <script src="https://carillon.example.com/sdk/carillon.js" defer></script>
   <script>
     window.CarillonDeferred = window.CarillonDeferred || [];
     CarillonDeferred.push(async (Carillon) => {
       await Carillon.init({ appId: 'YOUR-APP-ID' });
     });
   </script>
   ```

Your site must use HTTPS (`localhost` works for development).

## Use

```js
// After a click (browsers refuse prompts without a gesture).
button.onclick = () => Carillon.notifications.requestPermission();

Carillon.notifications.permission;      // "granted", "denied" or "not_determined"
Carillon.notifications.isSupported();

await Carillon.login('user-42');        // your id for the person
await Carillon.logout();

await Carillon.user.addTags({ plan: 'pro' });
await Carillon.user.removeTag('plan');

Carillon.notifications.addClickListener(({ notification, actionId }) => {
  // notification.data, notification.url…
});

await Carillon.user.pushSubscription.optOut(); // and optIn()
Carillon.user.pushSubscription.id;             // to send a test from the dashboard
```

`init` options: `appId` (required), `serverUrl` (defaults to the server that
serves `carillon.js`), `serviceWorkerPath` (`/carillon-sw.js`),
`serviceWorkerScope`.

## What it does

- Registers the browser with its push subscription, language, country, time
  zone and browser name; renews it when the browser changes it.
- Shows notifications with their icon (the project icon by default), image,
  buttons (`web_buttons`) and link, and reports receptions and clicks
  (statistics and webhooks).
- A click opens the link (or focuses an open page of the site) and reaches
  the click listeners, also when it opens a new tab.

See [example](example) for a page using every feature.
