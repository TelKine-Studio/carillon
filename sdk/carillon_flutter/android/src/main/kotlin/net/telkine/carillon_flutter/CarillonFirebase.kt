package net.telkine.carillon_flutter

import android.content.ContentProvider
import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import android.net.Uri
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions

/**
 * Firebase without google-services.json: the options served by Carillon are
 * kept on the device and applied at every start of the process, before the
 * FCM service runs (a notification can wake the app while it is closed).
 */
internal object CarillonFirebase {
    private const val PREFS = "carillon_firebase"
    private const val TAG = "Carillon"

    fun hasDefaultApp(context: Context): Boolean =
        FirebaseApp.getApps(context).any { it.name == FirebaseApp.DEFAULT_APP_NAME }

    /** Saves the options and starts Firebase with them. */
    fun configure(context: Context, values: Map<*, *>) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().apply {
            for (key in listOf("app_id", "api_key", "project_id", "sender_id")) {
                putString(key, values[key] as String?)
            }
            apply()
        }
        start(context)
    }

    /** Starts Firebase with the saved options, when the app has no google-services.json. */
    fun start(context: Context) {
        if (hasDefaultApp(context)) return
        val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val appId = prefs.getString("app_id", null) ?: return
        val apiKey = prefs.getString("api_key", null) ?: return
        val projectId = prefs.getString("project_id", null) ?: return
        try {
            FirebaseApp.initializeApp(
                context,
                FirebaseOptions.Builder()
                    .setApplicationId(appId)
                    .setApiKey(apiKey)
                    .setProjectId(projectId)
                    .setGcmSenderId(prefs.getString("sender_id", null))
                    .build(),
            )
        } catch (error: Exception) {
            Log.w(TAG, "Firebase could not start", error)
        }
    }
}

/** Runs at the start of the process, after the provider of Firebase. */
class CarillonInitProvider : ContentProvider() {
    override fun onCreate(): Boolean {
        context?.let { CarillonFirebase.start(it.applicationContext) }
        return false
    }

    override fun query(uri: Uri, projection: Array<out String>?, selection: String?, selectionArgs: Array<out String>?, sortOrder: String?): Cursor? = null
    override fun getType(uri: Uri): String? = null
    override fun insert(uri: Uri, values: ContentValues?): Uri? = null
    override fun delete(uri: Uri, selection: String?, selectionArgs: Array<out String>?): Int = 0
    override fun update(uri: Uri, values: ContentValues?, selection: String?, selectionArgs: Array<out String>?): Int = 0
}
