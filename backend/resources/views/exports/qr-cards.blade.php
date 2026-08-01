<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu QR</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 12px; }
        h1 { font-size: 16px; margin: 0 0 12px; }
        .grid { width: 100%; }
        .card {
            display: inline-block;
            width: 31%;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 8px;
            margin: 0 0.7% 10px;
            text-align: center;
            vertical-align: top;
            page-break-inside: avoid;
        }
        .card img { width: 110px; height: 110px; }
        .name { font-size: 11px; font-weight: bold; margin-top: 4px; }
        .meta { font-size: 9px; color: #444; }
        .rfid { font-size: 8px; color: #666; margin-top: 2px; word-break: break-all; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Kartu QR' }}</h1>
    <div class="grid">
        @foreach ($rows as $row)
            <div class="card">
                <img src="{{ $row['qr_png'] }}" alt="QR">
                <div class="name">{{ $row['name'] }}</div>
                <div class="meta">{{ $row['identifier_label'] }}: {{ $row['identifier'] }}</div>
                @if ($row['classroom'] !== '-')
                    <div class="meta">{{ $row['classroom'] }}</div>
                @endif
                @if (!empty($row['rfid_code']))
                    <div class="rfid">RFID: {{ $row['rfid_code'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
