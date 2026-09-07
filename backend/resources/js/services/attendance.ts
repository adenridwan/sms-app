import api from './api';
import type { ApiResponse, PaginatedResponse } from '@/types';
import type {
    StudentAttendance,
    TeacherAttendance,
    DailyAttendanceRecord,
    TeacherDailyRecord,
    LeavePermission,
    Holiday,
    AttendanceSettingsResponse,
    ScanResult,
    ScannerBootstrap,
    QrCodeData,
    AttendanceSummary,
    TeacherAttendanceSummary,
    MonthlyReport,
    WeeklyTrend,
    ConsecutiveAbsence,
    TopLateStudent,
    AttendanceFilters,
    LeavePermissionFilters,
    HolidayFilters,
    BulkAttendanceFormData,
    LeavePermissionFormData,
    HolidayFormData,
    LookupResult,
    CardLayout,
    CardTemplateResponse,
    MyAttendanceToday,
    MyAttendanceHistory,
} from '@/types/attendance';

// My Attendance API (self-service — guru & pegawai)
export const myAttendanceApi = {
    today: () =>
        api.get<ApiResponse<MyAttendanceToday>>('/attendance/me/today'),

    history: (month: string) =>
        api.get<ApiResponse<MyAttendanceHistory>>('/attendance/me/history', { params: { month } }),
};

// Card Template API (Fase 5 — editor kartu ID drag-and-drop)
export const cardTemplateApi = {
    get: (type: 'student' | 'teacher') =>
        api.get<ApiResponse<CardTemplateResponse>>(`/attendance/card-templates/${type}`),

    update: (type: 'student' | 'teacher', layout_json: CardLayout) =>
        api.put<ApiResponse<CardTemplateResponse>>(`/attendance/card-templates/${type}`, { layout_json }),

    reset: (type: 'student' | 'teacher') =>
        api.delete<ApiResponse<CardTemplateResponse>>(`/attendance/card-templates/${type}`),
};

// Scanner API
export const scannerApi = {
    bootstrap: () =>
        api.get<ApiResponse<ScannerBootstrap>>('/scan/bootstrap'),

    scan: (data: { unique_code: string; waktu: 'masuk' | 'pulang'; latitude?: number; longitude?: number }) =>
        api.post<ScanResult>('/scan', data),

    syncOffline: (scans: Array<{ unique_code: string; waktu: 'masuk' | 'pulang'; scanned_at: string; latitude?: number; longitude?: number }>) =>
        api.post<ApiResponse<{ total: number; success: number; failed: number; results: Array<{ unique_code: string; success: boolean; message: string }> }>>('/scan/sync-offline', { scans }),

    lookup: (unique_code: string) =>
        api.post<ApiResponse<LookupResult>>('/scan/lookup', { unique_code }),
};

// Student Attendance API
export const studentAttendanceApi = {
    list: (params?: AttendanceFilters) =>
        api.get<PaginatedResponse<StudentAttendance>>('/attendance/students', { params }),

    daily: (classroom_id: string, date: string) =>
        api.get<ApiResponse<{ date: string; classroom_id: string; students: DailyAttendanceRecord[]; summary: Record<string, number> }>>('/attendance/students/daily', { params: { classroom_id, date } }),

    storeBulk: (data: BulkAttendanceFormData) =>
        api.post<ApiResponse<{ created: number; updated: number }>>('/attendance/students/bulk', data),

    update: (id: string, data: Partial<{ status: string; check_in_time: string; check_out_time: string; notes: string; menit_keterlambatan: number }>) =>
        api.put<ApiResponse<StudentAttendance>>(`/attendance/students/${id}`, data),

    summary: (params: { from_date: string; to_date: string; classroom_id?: string; student_id?: string }) =>
        api.get<ApiResponse<AttendanceSummary>>('/attendance/students/summary', { params }),

    consecutiveAbsences: (params?: { min_days?: number; classroom_id?: string }) =>
        api.get<ApiResponse<{ min_days: number; count: number; students: ConsecutiveAbsence[] }>>('/attendance/students/consecutive-absences', { params }),

    topLate: (params?: { limit?: number; classroom_id?: string }) =>
        api.get<ApiResponse<TopLateStudent[]>>('/attendance/students/top-late', { params }),

    notifyDaily: (data: { classroom_id: string; date: string }) =>
        api.post<ApiResponse<{ recipients: number }>>('/attendance/students/notify-daily', data),
};

// Teacher Attendance API
export const teacherAttendanceApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<TeacherAttendance>>('/attendance/teachers', { params }),

    daily: (date: string) =>
        api.get<ApiResponse<{ date: string; teachers: TeacherDailyRecord[]; summary: Record<string, number> }>>('/attendance/teachers/daily', { params: { date } }),

    update: (id: string, data: Partial<{ status: string; check_in_time: string; check_out_time: string; notes: string; late_minutes: number }>) =>
        api.put<ApiResponse<TeacherAttendance>>(`/attendance/teachers/${id}`, data),

    summary: (params: { from_date: string; to_date: string; user_id?: string }) =>
        api.get<ApiResponse<TeacherAttendanceSummary>>('/attendance/teachers/summary', { params }),
};

// Leave Permission API
export const leavePermissionApi = {
    list: (params?: LeavePermissionFilters) =>
        api.get<ApiResponse<PaginatedResponse<LeavePermission>>>('/attendance/permissions', { params }),

    get: (id: string) =>
        api.get<ApiResponse<LeavePermission>>(`/attendance/permissions/${id}`),

    create: (data: LeavePermissionFormData) => {
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                if (value instanceof File) {
                    formData.append(key, value);
                } else {
                    formData.append(key, String(value));
                }
            }
        });
        return api.post<ApiResponse<LeavePermission>>('/attendance/permissions', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    update: (id: string, data: Partial<LeavePermissionFormData>) =>
        api.put<ApiResponse<LeavePermission>>(`/attendance/permissions/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/attendance/permissions/${id}`),

    approve: (id: string) =>
        api.post<ApiResponse<{ permission_id: string; dates_affected: string[]; records_upserted: number }>>(`/attendance/permissions/${id}/approve`),

    reject: (id: string, reason: string) =>
        api.post<ApiResponse<LeavePermission>>(`/attendance/permissions/${id}/reject`, { reason }),

    pendingCount: () =>
        api.get<ApiResponse<{ count: number }>>('/attendance/permissions-pending-count'),
};

// Holiday API
export const holidayApi = {
    list: (params?: HolidayFilters) =>
        api.get<ApiResponse<Holiday[]>>('/attendance/holidays', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Holiday>>(`/attendance/holidays/${id}`),

    create: (data: HolidayFormData) =>
        api.post<ApiResponse<Holiday>>('/attendance/holidays', data),

    update: (id: string, data: Partial<HolidayFormData>) =>
        api.put<ApiResponse<Holiday>>(`/attendance/holidays/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/attendance/holidays/${id}`),

    generateWeekends: (month: number, year: number) =>
        api.post<ApiResponse<{ count: number }>>('/attendance/holidays/generate-weekends', { month, year }),

    bulkDelete: (ids: string[]) =>
        api.post<ApiResponse<{ deleted: number }>>('/attendance/holidays/bulk-delete', { ids }),

    check: (date: string) =>
        api.post<ApiResponse<{ is_holiday: boolean; holiday: Holiday | null }>>('/attendance/holidays/check', { date }),
};

// QR Code API
export const qrCodeApi = {
    student: (id: string) =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/students/${id}`),

    regenerateStudent: (id: string) =>
        api.post<ApiResponse<{ old_code: string; new_code: string; qr_code: string }>>(`/attendance/qr/students/${id}/regenerate`),

    bulkStudents: (classroom_id: string, size?: number) =>
        api.get<ApiResponse<{ classroom_id: string; count: number; students: QrCodeData[] }>>('/attendance/qr/students/bulk', { params: { classroom_id, size } }),

    downloadStudent: (id: string, size?: number, format?: 'png' | 'svg') =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/students/${id}/download`, { params: { size, format } }),

    teacher: (id: string) =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/teachers/${id}`),

    regenerateTeacher: (id: string) =>
        api.post<ApiResponse<{ old_code: string; new_code: string; qr_code: string }>>(`/attendance/qr/teachers/${id}/regenerate`),

    bulkTeachers: (size?: number) =>
        api.get<ApiResponse<{ count: number; teachers: QrCodeData[] }>>('/attendance/qr/teachers/bulk', { params: { size } }),

    downloadTeacher: (id: string, size?: number) =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/teachers/${id}/download`, { params: { size } }),

    staff: (id: string) =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/staff/${id}`),

    regenerateStaff: (id: string) =>
        api.post<ApiResponse<{ old_code: string; new_code: string; qr_code: string }>>(`/attendance/qr/staff/${id}/regenerate`),

    bulkStaff: (size?: number) =>
        api.get<ApiResponse<{ count: number; staff: QrCodeData[] }>>('/attendance/qr/staff/bulk', { params: { size } }),

    downloadStaff: (id: string, size?: number) =>
        api.get<ApiResponse<QrCodeData>>(`/attendance/qr/staff/${id}/download`, { params: { size } }),

    // Export QR/RFID untuk pembuatan kartu (Excel / ZIP gambar / PDF)
    export: (params: {
        type: 'student' | 'teacher';
        scope?: 'class' | 'all';
        classroom_id?: string;
        format: 'excel' | 'zip' | 'pdf';
        image?: 'svg' | 'png' | 'both';
        generate_rfid?: boolean;
    }) => api.get('/attendance/qr/export', { params, responseType: 'blob' }),

    generateRfid: (params: { type: 'student' | 'teacher'; scope?: 'class' | 'all'; classroom_id?: string }) =>
        api.post<ApiResponse<{ total: number; with_rfid: number }>>('/attendance/qr/generate-rfid', params),
};

// Report API
export const attendanceReportApi = {
    monthly: (params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' }) =>
        api.get<ApiResponse<MonthlyReport>>('/attendance/reports/monthly', { params }),

    downloadPdf: (params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' }) =>
        api.get('/attendance/reports/pdf', { params, responseType: 'blob' }),

    downloadExcel: (params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' }) =>
        api.get('/attendance/reports/excel', { params, responseType: 'blob' }),

    weeklyTrend: (params?: { classroom_id?: string; type?: 'student' | 'teacher' }) =>
        api.get<ApiResponse<WeeklyTrend>>('/attendance/reports/weekly-trend', { params }),
};

// Settings API
export const attendanceSettingsApi = {
    get: () =>
        api.get<ApiResponse<AttendanceSettingsResponse>>('/attendance/settings'),

    update: (data: Partial<{
        // Attendance settings
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
        // Notification settings
        wa_enabled: boolean;
        wa_provider: string;
        wa_api_key: string;
        wa_sender_number: string;
        telegram_enabled: boolean;
        telegram_bot_token: string;
        telegram_default_chat_id: string;
        notify_check_in: boolean;
        notify_check_out: boolean;
        notify_late: boolean;
        notify_absent: boolean;
        notify_leave_approved: boolean;
        templates: Record<string, string>;
    }>) =>
        api.put<ApiResponse>('/attendance/settings', data),

    testWhatsApp: (phone: string) =>
        api.post<ApiResponse>('/attendance/settings/test-whatsapp', { phone }),

    testTelegram: (chat_id: string) =>
        api.post<ApiResponse>('/attendance/settings/test-telegram', { chat_id }),

    testEmail: (email: string) =>
        api.post<ApiResponse>('/attendance/settings/test-email', { email }),

    telegramBotInfo: () =>
        api.get<ApiResponse<{ id: number; first_name: string; username: string }>>('/attendance/settings/telegram-bot-info'),
};

// Public API (no auth required)
export const publicAttendanceApi = {
    lookup: (nis: string) =>
        api.post<ApiResponse<{ student_id: string; nis: string; name: string; classroom: string }>>('/public/izin/lookup', { nis }),

    submitLeave: (data: {
        student_id: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        tipe_izin: 'sakit' | 'izin';
        alasan: string;
        guardian_name: string;
        guardian_phone: string;
        bukti?: File;
    }) => {
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
                if (value instanceof File) {
                    formData.append(key, value);
                } else {
                    formData.append(key, String(value));
                }
            }
        });
        return api.post<ApiResponse<{ permission_id: string; status: string; message: string }>>('/public/izin/submit', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    leaveStatus: (permission_id: string) =>
        api.post<ApiResponse<{
            permission_id: string;
            student_name: string;
            tanggal_mulai: string;
            tanggal_selesai: string;
            tipe_izin: string;
            status: string;
            status_label: string;
            rejection_reason: string | null;
            submitted_at: string;
            processed_at: string | null;
        }>>('/public/izin/status', { permission_id }),

    checkAttendance: (nis: string, date?: string) =>
        api.post<ApiResponse<{
            student: { nis: string; name: string };
            date: string;
            status: string;
            status_label: string;
            check_in_time: string | null;
            check_out_time: string | null;
            menit_keterlambatan: number;
        }>>('/public/cek-kehadiran', { nis, date }),

    attendanceHistory: (nis: string, month?: number, year?: number) =>
        api.post<ApiResponse<{
            student: { nis: string; name: string };
            period: { month: number; year: number; month_name: string };
            history: Array<{
                date: string;
                day: string;
                status: string;
                status_label: string;
                check_in_time: string | null;
                check_out_time: string | null;
            }>;
            summary: {
                total: number;
                hadir: number;
                sakit: number;
                izin: number;
                alfa: number;
            };
        }>>('/public/riwayat-kehadiran', { nis, month, year }),
};

export default {
    cardTemplate: cardTemplateApi,
    scanner: scannerApi,
    myAttendance: myAttendanceApi,
    studentAttendance: studentAttendanceApi,
    teacherAttendance: teacherAttendanceApi,
    leavePermission: leavePermissionApi,
    holiday: holidayApi,
    qrCode: qrCodeApi,
    report: attendanceReportApi,
    settings: attendanceSettingsApi,
    public: publicAttendanceApi,
};
