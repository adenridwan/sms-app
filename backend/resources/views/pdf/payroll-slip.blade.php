<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Slip Gaji - {{ $slip->employee_name }}</title>
    <style>
        @page {
            margin: 1.5cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .header .logo {
            max-height: 60px;
            max-width: 200px;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 12pt;
            font-weight: normal;
            color: #555;
        }

        .header .period {
            font-size: 11pt;
            margin-top: 8px;
            font-weight: bold;
            color: #222;
        }

        /* Employee Info */
        .employee-info {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .employee-info table {
            width: 100%;
        }

        .employee-info td {
            padding: 3px 5px;
            vertical-align: top;
        }

        .employee-info .label {
            width: 120px;
            font-weight: bold;
            color: #555;
        }

        .employee-info .value {
            color: #222;
        }

        /* Attendance */
        .attendance {
            margin-bottom: 15px;
        }

        .attendance h3 {
            font-size: 10pt;
            margin-bottom: 8px;
            color: #555;
            text-transform: uppercase;
            border-bottom: 1px solid #ddd;
            padding-bottom: 4px;
        }

        .attendance-grid {
            display: table;
            width: 100%;
        }

        .attendance-item {
            display: table-cell;
            text-align: center;
            padding: 8px;
            border: 1px solid #ddd;
            background-color: #fff;
        }

        .attendance-item .number {
            font-size: 14pt;
            font-weight: bold;
            color: #222;
        }

        .attendance-item .label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
        }

        .attendance-item.present .number { color: #28a745; }
        .attendance-item.absent .number { color: #dc3545; }
        .attendance-item.late .number { color: #ffc107; }
        .attendance-item.leave .number { color: #17a2b8; }

        /* Sections */
        .section {
            margin-bottom: 15px;
        }

        .section h3 {
            font-size: 10pt;
            margin-bottom: 8px;
            padding: 6px 10px;
            background-color: #e9ecef;
            text-transform: uppercase;
        }

        .section.earnings h3 {
            background-color: #d4edda;
            color: #155724;
        }

        .section.deductions h3 {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
        }

        .items-table .name {
            text-align: left;
        }

        .items-table .detail {
            font-size: 8pt;
            color: #888;
        }

        .items-table .amount {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            white-space: nowrap;
        }

        .items-table .subtotal {
            border-top: 2px solid #333;
            font-weight: bold;
        }

        .items-table .subtotal.earnings {
            color: #155724;
        }

        .items-table .subtotal.deductions {
            color: #721c24;
        }

        /* Net Salary */
        .net-salary {
            margin-top: 20px;
            padding: 12px 15px;
            background-color: #28a745;
            color: #fff;
            border-radius: 4px;
        }

        .net-salary table {
            width: 100%;
        }

        .net-salary td {
            padding: 0;
        }

        .net-salary .label {
            font-size: 11pt;
            font-weight: bold;
        }

        .net-salary .amount {
            text-align: right;
            font-size: 14pt;
            font-weight: bold;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #888;
            text-align: center;
        }

        .footer p {
            margin-bottom: 3px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 3px;
        }

        .status-badge.draft { background-color: #6c757d; color: #fff; }
        .status-badge.calculated { background-color: #17a2b8; color: #fff; }
        .status-badge.approved { background-color: #28a745; color: #fff; }
        .status-badge.paid { background-color: #007bff; color: #fff; }

        /* Watermark for draft */
        @if($slip->status === 'draft')
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72pt;
            color: rgba(200, 200, 200, 0.3);
            font-weight: bold;
            text-transform: uppercase;
            z-index: -1;
        }
        @endif
    </style>
</head>
<body>
    @if($slip->status === 'draft')
    <div class="watermark">DRAFT</div>
    @endif

    <div class="container">
        <!-- Header -->
        <div class="header">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" class="logo">
            @endif
            <h1>{{ $tenant?->name ?? 'Slip Gaji Karyawan' }}</h1>
            <h2>Slip Gaji Karyawan</h2>
            <div class="period">Periode: {{ $periodLabel }}</div>
        </div>

        <!-- Employee Info -->
        <div class="employee-info">
            <table>
                <tr>
                    <td class="label">Nama</td>
                    <td class="value">: {{ $slip->employee_name }}</td>
                    <td class="label">Status</td>
                    <td class="value">
                        : <span class="status-badge {{ $slip->status }}">{{ $slip->getStatusLabel() }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="label">{{ $slip->employee_type === 'teacher' ? 'NIP' : 'ID Karyawan' }}</td>
                    <td class="value">: {{ $slip->employee_identifier ?? '-' }}</td>
                    <td class="label">Tipe</td>
                    <td class="value">: {{ $slip->getEmployeeTypeLabel() }}</td>
                </tr>
                <tr>
                    <td class="label">Golongan</td>
                    <td class="value">: {{ $slip->salary_grade_code ?? '-' }}</td>
                    <td class="label">Status PTKP</td>
                    <td class="value">: {{ $slip->ptkp_status ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <!-- Attendance -->
        @if($slip->working_days > 0)
        <div class="attendance">
            <h3>Kehadiran</h3>
            <div class="attendance-grid">
                <div class="attendance-item">
                    <div class="number">{{ $slip->working_days }}</div>
                    <div class="label">Hari Kerja</div>
                </div>
                <div class="attendance-item present">
                    <div class="number">{{ $slip->days_present }}</div>
                    <div class="label">Hadir</div>
                </div>
                <div class="attendance-item absent">
                    <div class="number">{{ $slip->days_absent }}</div>
                    <div class="label">Tidak Hadir</div>
                </div>
                <div class="attendance-item late">
                    <div class="number">{{ $slip->days_late }}</div>
                    <div class="label">Terlambat</div>
                </div>
                <div class="attendance-item leave">
                    <div class="number">{{ $slip->days_leave }}</div>
                    <div class="label">Cuti/Izin</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Earnings -->
        <div class="section earnings">
            <h3>Pendapatan</h3>
            <table class="items-table">
                @foreach($earnings as $item)
                <tr>
                    <td class="name">
                        {{ $item->component_name }}
                        @if($item->quantity > 0 && $item->rate > 0)
                            <div class="detail">{{ $item->quantity }} x Rp {{ number_format($item->rate, 0, ',', '.') }}</div>
                        @endif
                    </td>
                    <td class="amount">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr>
                    <td class="name subtotal earnings">Total Pendapatan</td>
                    <td class="amount subtotal earnings">Rp {{ number_format($slip->gross_salary, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Deductions -->
        <div class="section deductions">
            <h3>Potongan</h3>
            <table class="items-table">
                @foreach($deductions as $item)
                <tr>
                    <td class="name">
                        {{ $item->component_name }}
                        @if($item->quantity > 0 && $item->rate > 0)
                            <div class="detail">{{ $item->quantity }} x Rp {{ number_format($item->rate, 0, ',', '.') }}</div>
                        @endif
                    </td>
                    <td class="amount">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr>
                    <td class="name subtotal deductions">Total Potongan</td>
                    <td class="amount subtotal deductions">Rp {{ number_format($slip->total_deductions, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Net Salary -->
        <div class="net-salary">
            <table>
                <tr>
                    <td class="label">Gaji Bersih (Take Home Pay)</td>
                    <td class="amount">Rp {{ number_format($slip->net_salary, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Dokumen ini dibuat secara elektronik dan sah tanpa tanda tangan.</p>
            <p>Dicetak pada: {{ $generatedAt }}</p>
            @if($tenant)
            <p>{{ $tenant->name }} - {{ $tenant->address ?? '' }}</p>
            @endif
        </div>
    </div>
</body>
</html>
