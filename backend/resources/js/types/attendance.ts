// Attendance Status
export type AttendanceStatus = 'hadir' | 'sakit' | 'izin' | 'tanpa_keterangan' | 'alfa' | 'belum_scan';

export type TeacherAttendanceStatus = 'present' | 'absent' | 'late' | 'sick' | 'permitted' | 'on_duty' | 'work_from_home' | 'belum_scan';

export type ScanType = 'masuk' | 'pulang';

export type LeaveType = 'sakit' | 'izin';

export type LeaveStatus = 'pending' | 'approved' | 'rejected';

// Student Attendance
export interface StudentAttendance {
    id: string;
    student_id: string;
    classroom_id: string;
    academic_year_id: string;
    semester_id: string;
    attendance_date: string;
    status: AttendanceStatus;
    menit_keterlambatan: number;
    check_in_time: string | null;
    check_out_time: string | null;
    notes: string | null;
    latitude: number | null;
    longitude: number | null;
    student?: {
        id: string;
        nis: string;
        user?: {
            full_name: string;
        };
    };
    classroom?: {
        id: string;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

// Teacher/Employee Attendance
export interface TeacherAttendance {
    id: string;
    user_id: string;
    attendance_date: string;
    status: TeacherAttendanceStatus;
    check_in_time: string | null;
    check_out_time: string | null;
    late_minutes: number;
    notes: string | null;
    user?: {
        id: string;
        full_name: string;
        profile?: {
            first_name: string;
            last_name: string;
        };
    };
    created_at: string;
    updated_at: string;
}

// Daily Attendance Record (for display)
export interface DailyAttendanceRecord {
    student_id: string;
    nis: string;
    name: string;
    student_number_in_class?: string;
    attendance_id: string | null;
    status: AttendanceStatus;
    status_label: string;
    status_color: string;
    check_in_time: string | null;
    check_out_time: string | null;
    menit_keterlambatan: number;
    notes: string | null;
}

// Teacher Daily Attendance Record
export interface TeacherDailyRecord {
    teacher_id: string;
    user_id: string;
    nip: string | null;
    name: string;
    attendance_id: string | null;
    status: TeacherAttendanceStatus;
    check_in_time: string | null;
    check_out_time: string | null;
    late_minutes: number;
    notes: string | null;
}

// Absensi Saya (self-service)
export type MyAttendanceStatus = TeacherAttendanceStatus | 'libur';

export interface MyAttendanceToday {
    date: string;
    date_label: string;
    status: MyAttendanceStatus;
    check_in_time: string | null;
    check_out_time: string | null;
    late_minutes: number;
    identity_number: string | null;
    qr: {
        available: boolean;
        teacher_id: string | null;
    };
    rfid_code: string | null;
}

export interface MyAttendanceHistoryRecord {
    date: string;
    date_short: string;
    day: string;
    status: MyAttendanceStatus;
    check_in_time: string | null;
    check_out_time: string | null;
    late_minutes: number;
    notes: string | null;
}

export interface MyAttendanceHistory {
    month: string;
    month_label: string;
    total_working_days: number;
    summary: {
        hadir: number;
        sakit: number;
        izin: number;
        alfa: number;
        telat: number;
    };
    history: MyAttendanceHistoryRecord[];
}

// Leave Permission
export interface LeavePermission {
    id: string;
    tenant_id: string;
    student_id: string | null;
    teacher_id: string | null;
    tanggal_mulai: string;
    tanggal_selesai: string;
    tipe_izin: LeaveType;
    alasan: string | null;
    bukti: string | null;
    status: LeaveStatus;
    approved_by: string | null;
    approved_at: string | null;
    rejection_reason: string | null;
    total_days: number;
    student?: {
        id: string;
        nis: string;
        user?: {
            full_name: string;
        };
    };
    teacher?: {
        id: string;
        nip: string | null;
        user?: {
            full_name: string;
        };
    };
    approver?: {
        id: string;
        full_name: string;
    };
    created_at: string;
    updated_at: string;
}

// Holiday
export interface Holiday {
    id: string;
    tenant_id: string;
    tanggal: string;
    keterangan: string;
    is_recurring: boolean;
    created_at: string;
    updated_at: string;
}

// Attendance Settings
export interface AttendanceSettings {
    check_in_start: string;
    check_in_end: string;
    check_out_start: string;
    check_out_end: string;
    late_tolerance_minutes: number;
    require_location: boolean;
    require_photo: boolean;
    location_radius: number | null;
    school_latitude: number | null;
    school_longitude: number | null;
    working_days: number[];
}

// Notification Settings
export interface NotificationSettings {
    wa_enabled: boolean;
    wa_provider: 'fonnte' | 'wablas';
    wa_configured: boolean;
    telegram_enabled: boolean;
    telegram_configured: boolean;
    // Email (SMTP per-tenant)
    email_enabled: boolean;
    email_configured: boolean;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_username: string | null;
    smtp_encryption: 'tls' | 'ssl' | null;
    email_from_address: string | null;
    email_from_name: string | null;
    notify_email: boolean;
    notify_check_in: boolean;
    notify_check_out: boolean;
    notify_late: boolean;
    notify_absent: boolean;
    notify_leave_approved: boolean;
    templates: Record<string, string>;
}

// Combined Settings
export interface AttendanceSettingsResponse {
    attendance: AttendanceSettings;
    notification: NotificationSettings;
}

// Scan Result
export interface ScanResult {
    success: boolean;
    message: string;
    data: {
        type: 'student' | 'teacher';
        action: 'check_in' | 'check_out';
        student?: {
            id: string;
            nis: string;
            name: string;
            classroom?: string;
        };
        teacher?: {
            id: string;
            nip: string | null;
            name: string;
        };
        time: string;
        late?: boolean;
        late_minutes?: number;
        late_info?: {
            category: string;
            label: string;
            color: string;
            points: number;
        };
        total_violation_points?: number;
        check_in_time?: string;
    } | null;
}

// Scanner Bootstrap
export interface ScannerBootstrap {
    today: string;
    current_time: string;
    is_holiday: boolean;
    holiday_info: {
        keterangan: string;
    } | null;
    settings: {
        check_in_start: string;
        check_in_end: string;
        check_out_start: string;
        check_out_end: string;
        late_tolerance_minutes: number;
        require_location: boolean;
    };
    check_in_deadline: string;
}

// QR Code
export interface QrCodeData {
    student_id?: string;
    teacher_id?: string;
    nis?: string;
    nip?: string;
    name: string;
    /** Nama kelas siswa (untuk kartu cetak) */
    classroom?: string | null;
    /** Label status kepegawaian guru: Tetap/Kontrak/Honorer/Paruh Waktu */
    employment_status_label?: string | null;
    /** URL foto profil (users.avatar); null bila belum diunggah */
    photo_url?: string | null;
    unique_code: string;
    qr_code: string;
}

// Card Template (Fase 5 — editor kartu ID drag-and-drop)
export type CardElementKey = 'logo' | 'schoolName' | 'photo' | 'qr' | 'name' | 'idNumber' | 'subLine';

export interface CardElementLayout {
    x: number;
    y: number;
    width: number;
    height: number;
    fontSize?: number;
}

export interface CardLayout {
    cardBackground: string;
    headerBackground: string;
    elements: Record<CardElementKey, CardElementLayout>;
}

export interface CardTemplateResponse {
    layout: CardLayout;
    is_custom: boolean;
}

// Attendance Summary
export interface AttendanceSummary {
    period: {
        from: string;
        to: string;
    };
    total_records: number;
    by_status: {
        hadir: number;
        sakit: number;
        izin: number;
        tanpa_keterangan: number;
        alfa: number;
    };
    percentages: {
        hadir: number;
        tidak_hadir: number;
    };
    lateness: {
        total_late: number;
        total_minutes: number;
        avg_minutes: number;
    };
}

// Teacher Attendance Summary
export interface TeacherAttendanceSummary {
    period: {
        from: string;
        to: string;
    };
    total_records: number;
    by_status: {
        present: number;
        absent: number;
        late: number;
        sick: number;
        permitted: number;
        on_duty: number;
        work_from_home: number;
    };
    percentages: {
        present: number;
        absent: number;
    };
    lateness: {
        total_late: number;
        total_minutes: number;
        avg_minutes: number;
    };
}

// Monthly Report
export interface MonthlyReport {
    month: number;
    year: number;
    month_name: string;
    total_working_days: number;
    classroom_id?: string;
    students?: Array<{
        student_id: string;
        nis: string;
        name: string;
        stats: {
            hadir: number;
            sakit: number;
            izin: number;
            alfa: number;
            tanpa_keterangan: number;
            total_late_minutes: number;
        };
        attendance_rate: number;
    }>;
    teachers?: Array<{
        user_id: string;
        name: string;
        stats: {
            present: number;
            absent: number;
            sick: number;
            permitted: number;
            total_late_minutes: number;
        };
        attendance_rate: number;
    }>;
}

// Weekly Trend
export interface WeeklyTrendItem {
    date: string;
    day_name: string;
    hadir?: number;
    sakit?: number;
    izin?: number;
    alfa?: number;
    present?: number;
    absent?: number;
    sick?: number;
    permitted?: number;
}

export interface WeeklyTrend {
    start_date: string;
    end_date: string;
    trend: WeeklyTrendItem[];
}

// Consecutive Absence
export interface ConsecutiveAbsence {
    student_id: string;
    nis: string;
    name: string;
    classroom: string;
    consecutive_days: number;
    last_attendance: string;
}

// Top Late Student
export interface TopLateStudent {
    student_id: string;
    nis: string;
    name: string;
    poin_pelanggaran: number;
}

// Dashboard Attendance Stats
export interface DashboardAttendanceStats {
    today: string;
    summary: {
        hadir: number;
        sakit: number;
        izin: number;
        alfa: number;
        belum_scan: number;
    };
    percentage: number;
    top_late: TopLateStudent[];
    consecutive_absences: ConsecutiveAbsence[];
    weekly_trend: WeeklyTrendItem[];
}

// Offline Scan (for PWA)
export interface OfflineScan {
    id: string;
    unique_code: string;
    scan_type: ScanType;
    scanned_at: string;
    latitude?: number;
    longitude?: number;
    synced: boolean;
}

// Lookup Result
export interface LookupResult {
    type: 'student' | 'teacher';
    id: string;
    nis?: string;
    nip?: string;
    name: string;
    classroom?: string;
    status: string;
}

// Form Data Types
export interface LeavePermissionFormData {
    student_id?: string;
    teacher_id?: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    tipe_izin: LeaveType;
    alasan: string;
    bukti?: File;
}

export interface HolidayFormData {
    tanggal: string;
    keterangan: string;
    is_recurring: boolean;
}

export interface BulkAttendanceFormData {
    classroom_id: string;
    date: string;
    attendances: Array<{
        student_id: string;
        status: AttendanceStatus;
        check_in_time?: string;
        notes?: string;
    }>;
}

// Filter Types
export interface AttendanceFilters {
    date?: string;
    from_date?: string;
    to_date?: string;
    classroom_id?: string;
    student_id?: string;
    status?: AttendanceStatus;
}

export interface LeavePermissionFilters {
    status?: LeaveStatus;
    student_id?: string;
    teacher_id?: string;
    tipe_izin?: LeaveType;
    from_date?: string;
    to_date?: string;
}

export interface HolidayFilters {
    month?: number;
    year?: number;
    from_date?: string;
    to_date?: string;
}
