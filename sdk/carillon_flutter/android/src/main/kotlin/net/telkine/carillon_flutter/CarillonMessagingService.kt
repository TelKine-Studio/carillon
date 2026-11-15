package net.telkine.carillon_flutter

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import org.json.JSONObject
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Receives the FCM data messages of Carillon and shows them (with their
 * image and buttons). Other messages are ignored.
 */
class CarillonMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        CarillonFlutterPlugin.instance?.onToken(token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val raw = message.data["carillon"] ?: return
        val payload = try {
            JSONObject(raw)
        } catch (_: Exception) {
            return
        }
        if (payload.optInt("silent") == 1) return

        val plugin = CarillonFlutterPlugin.instance
        if (plugin != null && plugin.isForeground) {
            // The app decides (foreground listener); it reports the reception.
            var show = true
            val latch = CountDownLatch(1)
            plugin.askForeground(raw) {
                show = it
                latch.countDown()
            }
            latch.await(5, TimeUnit.SECONDS)
            if (show) CarillonNotifications.show(this, payload)
            return
        }

        CarillonNotifications.show(this, payload)
        CarillonNotifications.reportReceived(this, payload.optString("i"))
    }
}
