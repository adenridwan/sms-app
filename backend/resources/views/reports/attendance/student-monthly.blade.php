<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Absensi Siswa - {{ $month_name }}</title>
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
    <h1>Laporan Absensi Siswa{{ $classroom_id ? '' : ' — Semua Kelas' }}</h1>
    <p class="subtitle">{{ $month_name }} &middot; {{ $total_working_days }} hari kerja</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Hadir</th>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Alfa</th>
                <th>Terlambat (mnt)</th>
                <th>% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $index => $student)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td class="num">{{ $student['nis'] }}</td>
                    <td class="name">{{ $student['name'] }}</td>
                    <td class="num">{{ $student['stats']['hadir'] }}</td>
                    <td class="num">{{ $student['stats']['sakit'] }}</td>
                    <td class="num">{{ $student['stats']['izin'] }}</td>
                    <td class="num">{{ $student['stats']['alfa'] }}</td>
                    <td class="num">{{ $student['stats']['total_late_minutes'] }}</td>
                    <td class="num">{{ number_format($student['attendance_rate'], 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="num">Tidak ada data siswa untuk periode ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if (count($students) > 0)
            <tfoot>
                <tr>
                    <td colspan="3" class="name">Total</td>
                    <td class="num">{{ collect($students)->sum('stats.hadir') }}</td>
                    <td class="num">{{ collect($students)->sum('stats.sakit') }}</td>
                    <td class="num">{{ collect($students)->sum('stats.izin') }}</td>
                    <td class="num">{{ collect($students)->sum('stats.alfa') }}</td>
                    <td class="num">{{ collect($students)->sum('stats.total_late_minutes') }}</td>
                    <td class="num">{{ number_format(collect($students)->avg('attendance_rate'), 1) }}%</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
