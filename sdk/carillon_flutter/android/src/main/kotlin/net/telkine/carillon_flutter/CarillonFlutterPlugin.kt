package net.telkine.carillon_flutter

import android.Manifest
import android.app.Activity
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.google.firebase.messaging.FirebaseMessaging
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.embedding.engine.plugins.activity.ActivityAware
import io.flutter.embedding.engine.plugins.activity.ActivityPluginBinding
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import io.flutter.plugin.common.PluginRegistry

/**
 * Native side of Carillon on Android: FCM token, permission, and the taps
 * on the notifications shown by [CarillonMessagingService], forwarded to Dart.
 */
class CarillonFlutterPlugin :
    FlutterPlugin,
    ActivityAware,
    MethodChannel.MethodCallHandler,
    PluginRegistry.NewIntentListener,
    PluginRegistry.RequestPermissionsResultListener {

    private lateinit var context: Context
    private var channel: MethodChannel? = null
    private var activity: ActivityPluginBinding? = null
    private var started = false
    private var initialClick: Map<String, Any?>? = null
    private var pendingPermission: MethodChannel.Result? = null
    private val main = Handler(Looper.getMainLooper())

    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        context = binding.applicationContext
        channel = MethodChannel(binding.binaryMessenger, CHANNEL).also { it.setMethodCallHandler(this) }
        instance = this
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel?.setMethodCallHandler(null)
        channel = null
        started = false
        if (instance === this) instance = null
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "start" -> {
                started = true
                result.success(null)
            }
            "token" -> FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (!task.isSuccessful) Log.w(TAG, "FCM token unavailable", task.exception)
                result.success(if (task.isSuccessful) task.result else null)
            }
            "environment" -> result.success(null)
            "permission" -> result.success(permission())
            "requestPermission" -> requestPermission(result)
            "initialNotification" -> {
                result.success(initialClick)
                initialClick = null
            }
            "clearAll" -> {
                NotificationManagerCompat.from(context).cancelAll()
                result.success(null)
            }
            "share" -> {
                val values = call.arguments as? Map<*, *> ?: emptyMap<String, Any?>()
                CarillonNotifications.share(context, values.entries.associate { "${it.key}" to it.value as String? })
                result.success(null)
            }
            else -> result.notImplemented()
        }
    }

    private fun permission(): String {
        if (NotificationManagerCompat.from(context).areNotificationsEnabled()) return "granted"
        val asked = context.getSharedPreferences(CarillonNotifications.PREFS, Context.MODE_PRIVATE).getBoolean("asked", false)
        return if (Build.VERSION.SDK_INT >= 33 && !asked) "not_determined" else "denied"
    }

    private fun requestPermission(result: MethodChannel.Result) {
        val activity = activity?.activity
        val granted = Build.VERSION.SDK_INT < 33 ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
        if (granted || activity == null) {
            result.success(NotificationManagerCompat.from(context).areNotificationsEnabled())
            return
        }
        pendingPermission?.success(false)
        pendingPermission = result
        context.getSharedPreferences(CarillonNotifications.PREFS, Context.MODE_PRIVATE).edit().putBoolean("asked", true).apply()
        ActivityCompat.requestPermissions(activity, arrayOf(Manifest.permission.POST_NOTIFICATIONS), PERMISSION_REQUEST)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray): Boolean {
        if (requestCode != PERMISSION_REQUEST) return false
        pendingPermission?.success(grantResults.firstOrNull() == PackageManager.PERMISSION_GRANTED)
        pendingPermission = null
        return true
    }

    // Activity: taps on notifications open it with the payload in extras.

    override fun onAttachedToActivity(binding: ActivityPluginBinding) {
        activity = binding
        binding.addOnNewIntentListener(this)
        binding.addRequestPermissionsResultListener(this)
        handleIntent(binding.activity.intent)
    }

    override fun onDetachedFromActivity() {
        activity?.removeOnNewIntentListener(this)
        activity?.removeRequestPermissionsResultListener(this)
        activity = null
    }

    override fun onReattachedToActivityForConfigChanges(binding: ActivityPluginBinding) = onAttachedToActivity(binding)

    override fun onDetachedFromActivityForConfigChanges() = onDetachedFromActivity()

    override fun onNewIntent(intent: Intent): Boolean {
        handleIntent(intent)
        return false
    }

    private fun handleIntent(intent: Intent?) {
        val payload = intent?.getStringExtra(CarillonNotifications.EXTRA_PAYLOAD) ?: return
        val action = intent.getStringExtra(CarillonNotifications.EXTRA_ACTION)
        val id = intent.getIntExtra(CarillonNotifications.EXTRA_ID, 0)
        // Handled once (not again when the activity is recreated).
        intent.removeExtra(CarillonNotifications.EXTRA_PAYLOAD)
        intent.removeExtra(CarillonNotifications.EXTRA_ACTION)
        if (action != null) NotificationManagerCompat.from(context).cancel(id)

        val event = mapOf("carillon" to payload, "action" to action)
        if (started) channel?.invokeMethod("onOpened", event) else initialClick = event
    }

    // Called by the messaging service.

    internal fun onToken(token: String) = main.post { channel?.invokeMethod("onToken", mapOf("token" to token)) }

    /** Asks Dart whether to show a notification received in the foreground. */
    internal fun askForeground(payload: String, answer: (Boolean) -> Unit) = main.post {
        val channel = channel
        if (channel == null || !started) return@post answer(true)
        channel.invokeMethod("onForeground", mapOf("carillon" to payload), object : MethodChannel.Result {
            override fun success(result: Any?) = answer(result as? Boolean ?: true)
            override fun error(code: String, message: String?, details: Any?) = answer(true)
            override fun notImplemented() = answer(true)
        })
    }

    internal val isForeground: Boolean
        get() = started && activity != null && android.app.ActivityManager.RunningAppProcessInfo().also {
            android.app.ActivityManager.getMyMemoryState(it)
        }.importance == android.app.ActivityManager.RunningAppProcessInfo.IMPORTANCE_FOREGROUND

    companion object {
        private const val CHANNEL = "carillon_flutter"
        private const val TAG = "carillon"
        private const val PERMISSION_REQUEST = 7310

        @Volatile
        internal var instance: CarillonFlutterPlugin? = null
    }
}
