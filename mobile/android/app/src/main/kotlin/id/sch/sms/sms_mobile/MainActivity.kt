package id.sch.sms.sms_mobile

import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity, bukan FlutterActivity: plugin local_auth memakai
// BiometricPrompt dari AndroidX, yang menuntut host berupa FragmentActivity.
// Dengan FlutterActivity biasa, permintaan biometrik gagal saat dijalankan
// dengan "no fragment activity" — bukan saat dikompilasi.
class MainActivity: FlutterFragmentActivity()
