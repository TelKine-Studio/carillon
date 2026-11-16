package net.telkine.carillon_flutter

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.graphics.Color
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import org.json.JSONObject

/**
 * Live Activities on Android: an ongoing notification with progress,
 * promoted as a Live Update on Android 16 (status bar chip, top of the
 * shade, lock screen). Replaced on each update, closed or turned into a
 * regular notification at the end.
 */
internal object CarillonLiveUpdates {
    private const val CHANNEL = "carillon_live"
    private const val TAG = "carillon"
    private const val ORDER = "carillon.live"

    fun handle(context: Context, raw: String) {
        val live = try {
            JSONObject(raw)
        } catch (_: Exception) {
            return
        }
        val activityId = live.optString("a").ifEmpty { return }
        val event = live.optString("e")

        // FCM does not keep the order: an older update never replaces a newer one.
        val prefs = context.getSharedPreferences(ORDER, Context.MODE_PRIVATE)
        val timestamp = live.optLong("ts")
        if (timestamp < prefs.getLong(activityId, 0L)) return
        prefs.edit().putLong(activityId, timestamp).apply()
        if (event == "end") forget(prefs)

        val manager = NotificationManagerCompat.from(context)
        val id = "live:$activityId".hashCode() and 0x7fffffff
        val title = live.optString("title").ifEmpty { null }

        if (event == "end" && title == null) {
            manager.cancel(id)
            return
        }
        if (!manager.areNotificationsEnabled()) return

        // Tap: opens the app with the data of the activity (click listener).
        val payload = JSONObject().apply {
            put("i", "live:$activityId")
            title?.let { put("title", it) }
            live.optString("body").ifEmpty { null }?.let { put("body", it) }
            live.optJSONObject("d")?.let { put("d", it) }
        }
        val ongoing = event != "end"
        val builder = NotificationCompat.Builder(context, channel(context))
            .setSmallIcon(CarillonNotifications.smallIcon(context, live.optString("si").ifEmpty { null }))
            .setContentTitle(title ?: context.applicationInfo.loadLabel(context.packageManager))
            .setContentText(live.optString("body").ifEmpty { null })
            .setContentIntent(CarillonNotifications.open(context, payload, id, null))
            .setOnlyAlertOnce(true)
            .setOngoing(ongoing)
            .setAutoCancel(!ongoing)
            .setCategory(NotificationCompat.CATEGORY_PROGRESS)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)

        live.optString("color").ifEmpty { null }?.let { color ->
            runCatching { Color.parseColor(if (color.startsWith("#")) color else "#$color") }.onSuccess { builder.setColor(it) }
        }
        live.optString("st").ifEmpty { null }?.let { builder.setShortCriticalText(it) }

        val chronometer = live.optLong("chr")
        if (ongoing && chronometer > 0) {
            builder.setWhen(chronometer * 1000).setShowWhen(true).setUsesChronometer(true)
            builder.setChronometerCountDown(chronometer * 1000 > System.currentTimeMillis())
        }

        if (ongoing) {
            builder.setRequestPromotedOngoing(true)
            progress(builder, live)
        } else {
            val dismissal = live.optLong("dis")
            if (dismissal > 0) builder.setTimeoutAfter(maxOf(1000L, dismissal * 1000 - System.currentTimeMillis()))
            if (live.has("p")) progress(builder, live)
        }

        try {
            manager.notify(id, builder.build())
        } catch (error: SecurityException) {
            Log.w(TAG, "Live Update not shown", error)
        }
    }

    /**
     * Progress bar: segmented on Android 16 (ProgressStyle), a plain bar
     * before; "pi" for an unknown duration.
     */
    private fun progress(builder: NotificationCompat.Builder, live: JSONObject) {
        val indeterminate = live.optInt("pi") == 1
        if (!indeterminate && !live.has("p")) {
            builder.setStyle(NotificationCompat.BigTextStyle().bigText(live.optString("body")))
            return
        }
        val max = live.optInt("pm", 100).coerceAtLeast(1)
        val value = live.optInt("p").coerceIn(0, max)

        if (Build.VERSION.SDK_INT >= 36) {
            val style = NotificationCompat.ProgressStyle().setProgressIndeterminate(indeterminate).setProgress(value)
            val segments = live.optInt("seg", 1).coerceIn(1, 20)
            val length = max / segments
            for (index in 0 until segments) {
                val size = if (index == segments - 1) max - length * (segments - 1) else length
                style.addProgressSegment(NotificationCompat.ProgressStyle.Segment(size.coerceAtLeast(1)))
            }
            builder.setStyle(style)
        } else {
            builder.setProgress(max, value, indeterminate)
        }
    }

    /** Ended activities older than a day: their order no longer matters. */
    private fun forget(prefs: android.content.SharedPreferences) {
        val limit = System.currentTimeMillis() - 86_400_000L
        val editor = prefs.edit()
        prefs.all.forEach { (key, value) -> if (value is Long && value < limit) editor.remove(key) }
        editor.apply()
    }

    /** Default importance: shown, without a sound at each update. */
    private fun channel(context: Context): String {
        if (Build.VERSION.SDK_INT >= 26) {
            val manager = context.getSystemService(NotificationManager::class.java)
            if (manager.getNotificationChannel(CHANNEL) == null) {
                manager.createNotificationChannel(NotificationChannel(CHANNEL, "Live", NotificationManager.IMPORTANCE_DEFAULT))
            }
        }
        return CHANNEL
    }
}
