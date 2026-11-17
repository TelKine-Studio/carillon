package net.telkine.carillon_flutter

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.LocationManager
import androidx.core.content.ContextCompat

/**
 * Last known position of the device, read only when the app already has a
 * location permission: Carillon never asks for it, never turns the GPS on,
 * and works without Google Play services (Huawei, Fire).
 */
object CarillonLocation {
    @SuppressLint("MissingPermission")
    fun lastKnown(context: Context): Map<String, Any>? {
        val granted = listOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)
            .any { ContextCompat.checkSelfPermission(context, it) == PackageManager.PERMISSION_GRANTED }
        if (!granted) return null

        val manager = context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager ?: return null
        val best = try {
            manager.getProviders(true).mapNotNull { manager.getLastKnownLocation(it) }.maxByOrNull { it.time }
        } catch (_: Exception) {
            null
        } ?: return null

        return mapOf(
            "lat" to best.latitude,
            "long" to best.longitude,
            "accuracy" to best.accuracy.toDouble(),
            "time" to best.time,
        )
    }
}
