// Smoke test dasar: aplikasi merender layar splash saat pertama dibuka
// (status auth masih `unknown` sebelum bootstrap selesai).

import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:sms_mobile/app.dart';

void main() {
  testWidgets('menampilkan splash saat start', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: SmsApp()));
    await tester.pump();

    expect(find.text('Memeriksa sesi…'), findsOneWidget);
  });
}
