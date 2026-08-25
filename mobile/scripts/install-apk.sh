#!/usr/bin/env bash
#
# Bangun APK release lalu pasang ke HP yang tersambung USB.
#
#   ./scripts/install-apk.sh                 # deteksi IP LAN otomatis
#   ./scripts/install-apk.sh 192.168.1.14    # tentukan IP komputer sendiri
#   SKIP_BUILD=1 ./scripts/install-apk.sh    # pasang APK yang sudah ada
#
# Alamat backend di-*bake* ke dalam APK lewat --dart-define, jadi APK yang
# dipasang selalu terikat ke satu IP. Kalau IP komputer berubah (pindah WiFi),
# APK harus dibangun ulang — itu sebabnya IP-nya dicetak sebelum build.
set -euo pipefail

PORT="${PORT:-8001}"
PKG="id.sch.sms.sms_mobile"
APK="build/app/outputs/flutter-apk/app-release.apk"

cd "$(dirname "$0")/.."

# --- adb ------------------------------------------------------------------
ADB="${ADB:-$LOCALAPPDATA/Android/sdk/platform-tools/adb.exe}"
[ -x "$ADB" ] || ADB="$(command -v adb || true)"
if [ -z "$ADB" ]; then
  echo "adb tidak ditemukan. Set ADB=/path/ke/adb lalu ulangi." >&2
  exit 1
fi

# --- perangkat ------------------------------------------------------------
# Emulator ikut terdaftar dan membuat 'adb install' gagal dengan
# "more than one device/emulator", jadi perangkat dipilih eksplisit.
DEVICE="$("$ADB" devices | awk '$2 == "device" && $1 !~ /^emulator-/ { print $1; exit }')"
if [ -z "$DEVICE" ]; then
  UNAUTH="$("$ADB" devices | awk '$2 == "unauthorized" { print $1; exit }')"
  if [ -n "$UNAUTH" ]; then
    echo "HP terbaca tapi belum diizinkan." >&2
    echo "Buka layar HP -> dialog 'Allow USB debugging?' -> centang" >&2
    echo "'Always allow from this computer' -> Allow. Lalu ulangi." >&2
  else
    echo "Tidak ada HP tersambung. Cek kabel dan aktifkan USB debugging." >&2
  fi
  exit 1
fi

# --- alamat backend -------------------------------------------------------
IP="${1:-}"
if [ -z "$IP" ]; then
  IP="$(powershell.exe -NoProfile -Command \
    "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { \$_.IPAddress -notmatch '^(127|169\.254)' -and \$_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1).IPAddress" \
    2>/dev/null | tr -d '\r\n ')"
fi
if [ -z "$IP" ]; then
  echo "IP LAN tidak terdeteksi. Jalankan: ./scripts/install-apk.sh <ip>" >&2
  exit 1
fi

BASE="http://$IP:$PORT/api/v1"
echo "Perangkat : $DEVICE"
echo "Backend   : $BASE"

# Gagal cepat kalau server mati — lebih baik daripada memasang APK yang lalu
# tidak bisa login dan menyisakan tebak-tebakan.
if ! curl -sf -m 5 -o /dev/null "$BASE/ping"; then
  echo "PERINGATAN: $BASE/ping tidak menjawab." >&2
  echo "Nyalakan backend dulu:" >&2
  echo "  cd ../backend && php artisan serve --env=demo --port=$PORT --host=0.0.0.0" >&2
fi

# --- build & pasang -------------------------------------------------------
if [ "${SKIP_BUILD:-0}" != "1" ]; then
  flutter build apk --release --dart-define=API_BASE_URL="$BASE"
fi

[ -f "$APK" ] || { echo "APK tidak ada: $APK" >&2; exit 1; }

"$ADB" -s "$DEVICE" install -r "$APK"
"$ADB" -s "$DEVICE" shell monkey -p "$PKG" -c android.intent.category.LAUNCHER 1 >/dev/null 2>&1 || true

echo
echo "Selesai. Akun uji (kata sandi: password):"
echo "  admin@demo.sms.local    admin       <- bisa Pindai QR"
echo "  superadmin@sms.local    super admin"
echo "  guru1@demo.sms.local    guru        <- Checklist per kelas"
echo "  guru2@demo.sms.local    wali kelas"
