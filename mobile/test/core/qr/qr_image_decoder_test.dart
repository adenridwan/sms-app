import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:qr/qr.dart';
import 'package:sms_mobile/core/qr/qr_image_decoder.dart';

/// Menggambar QR berisi [data] sebagai PNG, lalu menyimpannya ke [dir].
///
/// Dibuat di sini alih-alih menyimpan PNG di repo supaya berkas uji tidak ikut
/// membesarkan repositori, dan supaya isi QR terbaca langsung dari kodenya.
File _writeQrPng(Directory dir, String name, String data, {int scale = 6}) {
  final qr = QrCode.fromData(
    data: data,
    errorCorrectLevel: QrErrorCorrectLevel.M,
  );
  final matrix = QrImage(qr);

  const quiet = 4;
  final side = (matrix.moduleCount + quiet * 2) * scale;
  final image = img.Image(width: side, height: side)
    ..clear(img.ColorRgb8(255, 255, 255));

  for (var r = 0; r < matrix.moduleCount; r++) {
    for (var c = 0; c < matrix.moduleCount; c++) {
      if (!matrix.isDark(r, c)) continue;
      img.fillRect(
        image,
        x1: (c + quiet) * scale,
        y1: (r + quiet) * scale,
        x2: (c + quiet + 1) * scale - 1,
        y2: (r + quiet + 1) * scale - 1,
        color: img.ColorRgb8(0, 0, 0),
      );
    }
  }

  final file = File('${dir.path}/$name.png')
    ..writeAsBytesSync(img.encodePng(image));
  return file;
}

void main() {
  late Directory tmp;

  setUp(() => tmp = Directory.systemTemp.createTempSync('qr_decode_test'));
  tearDown(() => tmp.deleteSync(recursive: true));

  test('membaca QR provisioning dari berkas PNG', () async {
    const payload =
        '{"api":"https://api.sekolah.sch.id/api/v1","token":"abc123",'
        '"school":"SMPN 1 Contoh"}';

    final file = _writeQrPng(tmp, 'provision', payload);

    expect(await decodeQrFromImageFile(file.path), payload);
  });

  test('membaca QR dari gambar besar yang disusutkan dulu', () async {
    // Melewati ambang 1600 px agar cabang `copyResize` ikut teruji — foto
    // kamera memang sebesar ini.
    final file = _writeQrPng(tmp, 'besar', 'SISWA-0007', scale: 80);
    expect(img.decodePng(file.readAsBytesSync())!.width, greaterThan(1600));

    expect(await decodeQrFromImageFile(file.path), 'SISWA-0007');
  });

  test('gambar tanpa QR menghasilkan null, bukan lemparan', () async {
    final blank = img.Image(width: 300, height: 300)
      ..clear(img.ColorRgb8(200, 200, 200));
    final file = File('${tmp.path}/kosong.png')
      ..writeAsBytesSync(img.encodePng(blank));

    expect(await decodeQrFromImageFile(file.path), isNull);
  });

  test('berkas yang bukan gambar melempar QrImageReadException', () async {
    final file = File('${tmp.path}/bukan-gambar.png')
      ..writeAsStringSync('ini teks biasa');

    expect(
      () => decodeQrFromImageFile(file.path),
      throwsA(isA<QrImageReadException>()),
    );
  });
}
