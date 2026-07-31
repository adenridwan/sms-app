import 'package:geolocator/geolocator.dart';

/// Hasil pengambilan lokasi.
class LocationResult {
  const LocationResult({this.latitude, this.longitude, this.error});
  final double? latitude;
  final double? longitude;
  final String? error;

  bool get ok => latitude != null && longitude != null;

  static const denied = LocationResult(
    error: 'Izin lokasi ditolak. Aktifkan untuk melanjutkan absen.',
  );
  static const serviceOff = LocationResult(
    error: 'Layanan lokasi (GPS) tidak aktif.',
  );
}

/// Pembungkus geolocator untuk kebutuhan geofence absensi.
class LocationService {
  /// Ambil posisi saat ini. Menangani izin & layanan mati.
  Future<LocationResult> current() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return LocationResult.serviceOff;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      return LocationResult.denied;
    }

    try {
      final pos = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
        timeLimit: const Duration(seconds: 12),
      );
      return LocationResult(latitude: pos.latitude, longitude: pos.longitude);
    } catch (_) {
      return const LocationResult(error: 'Gagal mendapatkan lokasi.');
    }
  }
}
