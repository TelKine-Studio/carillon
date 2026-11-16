package net.telkine.carillon_flutter

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/** Builds and shows the notifications, and reports their reception. */
internal object CarillonNotifications {
    const val PREFS = "carillon"
    const val EXTRA_PAYLOAD = "carillon.payload"
    const val EXTRA_ACTION = "carillon.action"
    const val EXTRA_ID = "carillon.id"
    private const val DEFAULT_CHANNEL = "carillon_default"
    private const val TAG = "carillon"

    fun show(context: Context, payload: JSONObject) {
        val manager = NotificationManagerCompat.from(context)
        if (!manager.areNotificationsEnabled()) return

        val id = payload.optString("i").hashCode() and 0x7fffffff
        val title = payload.optString("title").ifEmpty { null }
        val body = payload.optString("body").ifEmpty { null }
        val picture = payload.optString("img").ifEmpty { null }?.let { download(it) }
        // Logo next to the text: the message's, else the project's (sent by the server).
        val largeIcon = payload.optString("li").ifEmpty { null }
            ?.takeIf { it.startsWith("http") }
            ?.let { download(it) }
            ?.let { circle(it) }

        val builder = NotificationCompat.Builder(context, channel(context, payload.optString("ch").ifEmpty { null }))
            .setSmallIcon(smallIcon(context, payload.optString("si").ifEmpty { null }))
            .setContentTitle(title)
            .setContentText(body)
            .setSubText(payload.optString("subtitle").ifEmpty { null })
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(open(context, payload, id, null))

        largeIcon?.let { builder.setLargeIcon(it) }
        if (picture != null) {
            // Collapsed: the logo (or the picture); expanded: the picture alone.
            if (largeIcon == null) builder.setLargeIcon(picture)
            builder.setStyle(
                NotificationCompat.BigPictureStyle().bigPicture(picture).bigLargeIcon(null as Bitmap?).setSummaryText(body),
            )
        } else if (body != null) {
            builder.setStyle(NotificationCompat.BigTextStyle().bigText(body))
        }

        payload.optString("color").ifEmpty { null }?.let { color ->
            runCatching { Color.parseColor(if (color.startsWith("#")) color else "#${color.takeLast(6)}") }
                .onSuccess { builder.setColor(it) }
        }
        payload.optString("g").ifEmpty { null }?.let { builder.setGroup(it) }
        when (payload.optInt("v", 1)) {
            0 -> builder.setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            -1 -> builder.setVisibility(NotificationCompat.VISIBILITY_SECRET)
            else -> builder.setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
        }

        val buttons = payload.optJSONArray("b")
        if (buttons != null) {
            for (index in 0 until minOf(buttons.length(), 3)) {
                val button = buttons.optJSONObject(index) ?: continue
                val action = button.optString("id").ifEmpty { continue }
                builder.addAction(0, button.optString("text"), open(context, payload, id, action))
            }
        }

        try {
            manager.notify(id, builder.build())
        } catch (error: SecurityException) {
            Log.w(TAG, "Notification not shown", error)
        }
    }

    /** Opens the app (its launcher activity) with the payload. */
    private fun open(context: Context, payload: JSONObject, id: Int, action: String?): PendingIntent {
        val intent = (context.packageManager.getLaunchIntentForPackage(context.packageName) ?: Intent()).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra(EXTRA_PAYLOAD, payload.toString())
            putExtra(EXTRA_ID, id)
            if (action != null) putExtra(EXTRA_ACTION, action)
        }
        val request = (id * 31 + (action?.hashCode() ?: 0)) and 0x7fffffff
        return PendingIntent.getActivity(context, request, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }

    /** The channel of the message if the app created it, else the default one. */
    private fun channel(context: Context, requested: String?): String {
        if (Build.VERSION.SDK_INT < 26) return requested ?: DEFAULT_CHANNEL
        val manager = context.getSystemService(NotificationManager::class.java)
        if (requested != null && manager.getNotificationChannel(requested) != null) return requested
        if (manager.getNotificationChannel(DEFAULT_CHANNEL) == null) {
            manager.createNotificationChannel(NotificationChannel(DEFAULT_CHANNEL, "Notifications", NotificationManager.IMPORTANCE_HIGH))
        }
        return DEFAULT_CHANNEL
    }

    /**
     * Small icon: the drawable named in the message, else "ic_stat_carillon"
     * if the app has one, else the app icon.
     */
    private fun smallIcon(context: Context, name: String?): Int {
        val resources = context.resources
        for (candidate in listOfNotNull(name, "ic_stat_carillon")) {
            val id = resources.getIdentifier(candidate, "drawable", context.packageName)
            if (id != 0) return id
        }
        return context.applicationInfo.icon
    }

    /** Round crop, like the avatars of messaging apps. */
    private fun circle(source: Bitmap): Bitmap {
        val size = minOf(source.width, source.height)
        val output = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        val canvas = android.graphics.Canvas(output)
        val paint = android.graphics.Paint(android.graphics.Paint.ANTI_ALIAS_FLAG)
        canvas.drawCircle(size / 2f, size / 2f, size / 2f, paint)
        paint.xfermode = android.graphics.PorterDuffXfermode(android.graphics.PorterDuff.Mode.SRC_IN)
        canvas.drawBitmap(source, ((size - source.width) / 2).toFloat(), ((size - source.height) / 2).toFloat(), paint)
        return output
    }

    private fun download(url: String): Bitmap? = try {
        (URL(url).openConnection() as HttpURLConnection).run {
            connectTimeout = 5000
            readTimeout = 10000
            try {
                if (responseCode == 200) inputStream.use { BitmapFactory.decodeStream(it) } else null
            } finally {
                disconnect()
            }
        }
    } catch (error: Exception) {
        Log.w(TAG, "Image not loaded: $url", error)
        null
    }

    /** Server, project and device, written by Dart after each sync. */
    fun share(context: Context, values: Map<String, String?>) {
        val editor = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
        values.forEach { (key, value) -> if (value == null) editor.remove(key) else editor.putString(key, value) }
        editor.apply()
    }

    fun reportReceived(context: Context, messageId: String) {
        if (messageId.isEmpty()) return
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val server = prefs.getString("server", null)?.trimEnd('/') ?: return
        val app = prefs.getString("app", null) ?: return
        val subscription = prefs.getString("subscription", null) ?: return

        try {
            val connection = URL("$server/api/v1/sdk/apps/$app/subscriptions/$subscription/events").openConnection() as HttpURLConnection
            connection.run {
                requestMethod = "POST"
                connectTimeout = 5000
                readTimeout = 10000
                doOutput = true
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
                outputStream.use {
                    it.write(JSONObject(mapOf("message_id" to messageId, "event" to "received")).toString().toByteArray())
                }
                responseCode
                disconnect()
            }
        } catch (error: Exception) {
            Log.w(TAG, "Reception not reported", error)
        }
    }
}
