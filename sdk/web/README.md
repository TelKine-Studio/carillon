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
await Carillon.user.addEmail('ada@example.com'); // email channel (once subscribed)
await Carillon.user.addSms('+2250700000000');     // SMS channel
await Carillon.user.addWhatsApp('+2250700000000'); // WhatsApp, once the visitor agreed

// Events: start journeys, show in-app messages, count as conversions.
await Carillon.user.trackEvent('purchase', { value: 5000 });
await Carillon.session.addOutcomeWithValue('purchase', 5000);

// In-app messages designed in Carillon (banners, pop-ups).
Carillon.inAppMessages.paused = true; // e.g. during a checkout
Carillon.inAppMessages.addClickListener((messageId, button) => {});

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
- Shows the in-app messages of the project (when the page opens or after an
  event), with their delay and frequency, isolated from the styles of the
  site.
- Sends a sign of life every minute while the page is visible, for "online
  now" in the dashboard.

See [example](example) for a page using every feature.
