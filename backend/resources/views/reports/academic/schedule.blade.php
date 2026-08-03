<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jadwal Pelajaran - {{ $classroom->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .subtitle { font-size: 11px; color: #555; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 5px 6px; vertical-align: top; }
        th { background: #f0f0f0; text-align: center; }
        td.jam { width: 90px; text-align: center; background: #fafafa; }
        td.jam .time { font-size: 9px; color: #555; }
        td.break { text-align: center; background: #fff3d6; color: #8a5a00; font-style: italic; }
        td.cell { text-align: center; }
        td.cell .mapel { font-weight: bold; }
        td.cell .guru { font-size: 9px; color: #555; }
        td.empty { color: #ccc; }
    </style>
</head>
<body>
    <h1>Jadwal Pelajaran &mdash; Kelas {{ $classroom->name }}</h1>
    <p class="subtitle">
        {{ $classroom->gradeLevel?->name }}{{ $classroom->major ? ' &middot; '.$classroom->major->name : '' }}
        &middot; {{ $semester->name }} &middot; {{ $classroom->academicYear?->name }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Jam</th>
                @foreach ($dayNames as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($grid as $row)
                @php $slot = $row['slot']; @endphp
                <tr>
                    <td class="jam">
                        {{ $slot->name }}<br>
                        <span class="time">{{ substr($slot->start_time, 0, 5) }}-{{ substr($slot->end_time, 0, 5) }}</span>
                    </td>
                    @if ($slot->is_break)
                        <td class="break" colspan="6">Istirahat</td>
                    @else
                        @foreach (range(1, 6) as $day)
                            @php $schedule = $row['days'][$day] ?? null; @endphp
                            <td class="cell">
                                @if ($schedule)
                                    <div class="mapel">{{ $schedule->subject->name }}</div>
                                    <div class="guru">{{ $schedule->teacher->full_name }}</div>
                                @else
                                    <span class="empty">-</span>
                                @endif
                            </td>
                        @endforeach
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Belum ada jam pelajaran.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
