import 'dart:io';
import 'dart:typed_data';

import 'package:image/image.dart' as img;
import 'package:zxing2/qrcode.dart';

/// Membaca isi QR dari **berkas gambar**, murni di Dart.
///
/// Sengaja tidak memakai `MobileScannerController.analyzeImage`: jalur itu
/// hanya ada di Android/iOS/macOS. Di Windows — tempat aplikasi ini dipakai
/// saat pengembangan dan oleh operator berkomputer — plugin `mobile_scanner`
/// tidak terpasang sama sekali, sehingga pemanggilannya melempar
/// `MissingPluginException` walau dialog pilih berkas berhasil terbuka.
/// Dekoder ZXing versi Dart berjalan di semua platform, jadi "unggah gambar"
/// berperilaku sama di mana pun. Kamera langsung tetap lewat `mobile_scanner`,
/// karena itu memang butuh dukungan native.
///
/// Mengembalikan isi QR, atau `null` bila tidak ada QR yang terbaca.
/// Melempar [QrImageReadException] bila berkasnya sendiri tidak bisa dibaca.
Future<String?> decodeQrFromImageFile(String path) async {
  final bytes = await File(path).readAsBytes();

  final decoded = img.decodeImage(bytes);
  if (decoded == null) {
    throw const QrImageReadException(
      'Format gambar tidak dikenali. Gunakan PNG atau JPG.',
    );
  }

  // Foto kamera modern bisa 4000 px lebih; menyusutkannya mempercepat
  // pendeteksian secara mencolok dan tidak mengurangi keterbacaan QR, yang
  // hanya butuh beberapa piksel per modul.
  final image = decoded.width > 1600 || decoded.height > 1600
      ? img.copyResize(
          decoded,
          width: decoded.width >= decoded.height ? 1600 : null,
          height: decoded.height > decoded.width ? 1600 : null,
          interpolation: img.Interpolation.average,
        )
      : decoded;

  // `RGBLuminanceSource` membaca satu piksel per int32 bergaya ARGB; pada mesin
  // little-endian itu sama dengan urutan byte ABGR. `Int32List.view` dipakai
  // alih-alih `.buffer.asInt32List()` karena `getBytes` boleh mengembalikan
  // potongan buffer yang lebih besar — tanpa offsetnya, gambar tergeser.
  final pixels = image.convert(numChannels: 4).getBytes(
        order: img.ChannelOrder.abgr,
      );
  final source = RGBLuminanceSource(
    image.width,
    image.height,
    Int32List.view(pixels.buffer, pixels.offsetInBytes, pixels.length ~/ 4),
  );

  final reader = QRCodeReader();

  // Dua kali coba: sekali apa adanya, sekali dengan TRY_HARDER. Percobaan
  // pertama menangani tangkapan layar yang bersih dengan cepat; yang kedua
  // jauh lebih lambat tapi menyelamatkan foto QR yang miring atau kurang
  // kontras — kasus yang lumrah saat operator memotret layar admin.
  for (final tryHarder in [false, true]) {
    final hints = DecodeHints();
    if (tryHarder) hints.put(DecodeHintType.tryHarder);

    try {
      final result =
          reader.decode(BinaryBitmap(HybridBinarizer(source)), hints: hints);
      final text = result.text;
      if (text.isNotEmpty) return text;
    } on ReaderException {
      // Tidak terbaca pada percobaan ini — lanjut ke TRY_HARDER, atau menyerah
      // dan kembalikan null di bawah.
      continue;
    }
  }

  return null;
}

/// Berkasnya tidak bisa dibaca sebagai gambar — beda dari "gambar terbaca tapi
/// tidak ada QR di dalamnya", yang cukup diwakili `null`.
class QrImageReadException implements Exception {
  const QrImageReadException(this.message);

  final String message;

  @override
  String toString() => message;
}
