<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Absensi Guru - {{ $month_name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .subtitle { font-size: 11px; color: #555; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; }
        th { background: #f0f0f0; text-align: center; }
        td.num { text-align: center; }
        td.name { text-align: left; }
        tfoot td { font-weight: bold; background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>Laporan Absensi Guru</h1>
    <p class="subtitle">{{ $month_name }} &middot; {{ $total_working_days }} hari kerja</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Hadir</th>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Tidak Hadir</th>
                <th>Terlambat (mnt)</th>
                <th>% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($teachers as $index => $teacher)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td class="name">{{ $teacher['name'] }}</td>
                    <td class="num">{{ $teacher['stats']['present'] }}</td>
                    <td class="num">{{ $teacher['stats']['sick'] }}</td>
                    <td class="num">{{ $teacher['stats']['permitted'] }}</td>
                    <td class="num">{{ $teacher['stats']['absent'] }}</td>
                    <td class="num">{{ $teacher['stats']['total_late_minutes'] }}</td>
                    <td class="num">{{ number_format($teacher['attendance_rate'], 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="num">Tidak ada data guru untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($teachers) > 0)
            <tfoot>
                <tr>
                    <td colspan="2" class="name">Total</td>
                    <td class="num">{{ collect($teachers)->sum('stats.present') }}</td>
                    <td class="num">{{ collect($teachers)->sum('stats.sick') }}</td>
                    <td class="num">{{ collect($teachers)->sum('stats.permitted') }}</td>
                    <td class="num">{{ collect($teachers)->sum('stats.absent') }}</td>
                    <td class="num">{{ collect($teachers)->sum('stats.total_late_minutes') }}</td>
                    <td class="num">{{ number_format(collect($teachers)->avg('attendance_rate'), 1) }}%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
