import axios from 'axios';
import type { ApiResponse, PaginatedResponse, Student, Teacher, TeacherFormData, TeacherAssignment, TeacherDocumentCollection, TeacherDocuments, ClassRoom, Subject, Curriculum, AcademicYear, Semester, Payment, FeeType, Attendance, DashboardStats, User, Major, GradeLevel, Classroom, TimeSlot, Schedule, BackupFile, DbConnectionInfo } from '@/types';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

// Add CSRF token & tenant context to requests
api.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }

    // Tenant context for super admin (chosen via tenant switcher in the header).
    // Regular users are resolved from their own tenant_id server-side.
    const tenantId = localStorage.getItem('active_tenant_id');
    if (tenantId) {
        config.headers['X-Tenant-ID'] = tenantId;
    }

    return config;
});

// Handle errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// Auth
export const authApi = {
    login: (data: { email: string; password: string; remember?: boolean }) =>
        api.post<ApiResponse<{ user: User; token: string }>>('/auth/login', data),

    register: (data: { username: string; email: string; password: string; password_confirmation: string; first_name: string; last_name?: string }) =>
        api.post<ApiResponse<{ user: User; token: string }>>('/auth/register', data),

    logout: () => api.post<ApiResponse>('/auth/logout'),

    me: () => api.get<ApiResponse<User>>('/auth/me'),

    updateProfile: (data: FormData) =>
        api.post<ApiResponse<User>>('/auth/profile', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    // Route memakai PUT (lihat routes/api_v1.php: auth.password.update)
    changePassword: (data: { current_password: string; password: string; password_confirmation: string }) =>
        api.put<ApiResponse>('/auth/password', data),
};

// Dashboard
export const dashboardApi = {
    getStats: () => api.get<ApiResponse<DashboardStats>>('/dashboard'),

    classStats: (classroomId: string) =>
        api.get<ApiResponse<Record<string, unknown>>>(`/dashboard/class/${classroomId}`),
};

// Students
export const studentsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Student>>('/students', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Student>>(`/students/${id}`),

    create: (data: FormData) =>
        api.post<ApiResponse<Student>>('/students', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    // Foto profil sudah lewat endpoint terpisah (uploadPhoto/deletePhoto di
    // bawah, Fase 3a) — update() cukup field teks biasa, tidak perlu multipart.
    update: (id: string, data: Record<string, unknown>) =>
        api.put<ApiResponse<Student>>(`/students/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/students/${id}`),

    bulkDelete: (ids: string[]) =>
        api.post<ApiResponse>('/students/bulk-destroy', { ids }),

    // Export/template tersedia dalam xlsx (default) & csv
    export: (format: 'xlsx' | 'csv' = 'xlsx') =>
        api.get('/students/export', { params: { format }, responseType: 'blob' }),

    template: (format: 'xlsx' | 'csv' = 'xlsx') =>
        api.get('/students/template', { params: { format }, responseType: 'blob' }),

    import: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post<ApiResponse<ImportResult>>('/students/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    uploadPhoto: (id: string, file: File) => {
        const formData = new FormData();
        formData.append('photo', file);
        return api.post<ApiResponse<{ photo_url: string }>>(`/students/${id}/photo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deletePhoto: (id: string) =>
        api.delete<ApiResponse>(`/students/${id}/photo`),

    // Kode kartu RFID — endpoint dibuat Fase 2 (RfidController), baru
    // dipakai UI-nya sekarang di students/Edit.tsx.
    updateRfid: (id: string, rfid_code: string | null) =>
        api.put<ApiResponse<{ student_id: string; rfid_code: string | null }>>(
            `/attendance/rfid/students/${id}`,
            { rfid_code }
        ),
};

// Teachers (Data Guru — master: identitas, kepegawaian, akun login.
// Penempatan kelas & mapel dikelola dari menu Kelas / Mata Pelajaran,
// lihat TEACHER-MODULE-PLAN.md §2)
export interface CreateTeacherResponse extends Teacher {
    initial_username: string;
    initial_password: string;
}

export const teachersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Teacher>>>('/teachers', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Teacher>>(`/teachers/${id}`),

    create: (data: Partial<TeacherFormData>) =>
        api.post<ApiResponse<CreateTeacherResponse>>('/teachers', data),

    update: (id: string, data: Partial<TeacherFormData>) =>
        api.put<ApiResponse<Teacher>>(`/teachers/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/teachers/${id}`),

    getAssignment: (id: string) =>
        api.get<ApiResponse<TeacherAssignment>>(`/teachers/${id}/assignment`),

    // Export/template tersedia dalam xlsx (default) & csv
    export: (format: 'xlsx' | 'csv' = 'xlsx') =>
        api.get('/teachers/export', { params: { format }, responseType: 'blob' }),

    template: (format: 'xlsx' | 'csv' = 'xlsx') =>
        api.get('/teachers/template', { params: { format }, responseType: 'blob' }),

    import: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post<ApiResponse<ImportResult>>('/teachers/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    uploadPhoto: (id: string, file: File) => {
        const formData = new FormData();
        formData.append('photo', file);
        return api.post<ApiResponse<{ avatar_url: string }>>(`/teachers/${id}/photo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deletePhoto: (id: string) =>
        api.delete<ApiResponse>(`/teachers/${id}/photo`),

    updateRfid: (id: string, rfid_code: string | null) =>
        api.put<ApiResponse<{ teacher_id: string; rfid_code: string | null }>>(
            `/attendance/rfid/teachers/${id}`,
            { rfid_code }
        ),

    // Kode RFID terbitan sistem — dibuat & disimpan sekaligus di server
    generateRfid: (id: string) =>
        api.post<ApiResponse<{ teacher_id: string; rfid_code: string; previous_rfid_code: string | null }>>(
            `/attendance/rfid/teachers/${id}/generate`
        ),

    uploadDocument: (id: string, collection: TeacherDocumentCollection, file: File) => {
        const formData = new FormData();
        formData.append('collection', collection);
        formData.append('file', file);
        return api.post<ApiResponse<TeacherDocuments>>(`/teachers/${id}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deleteDocument: (id: string, mediaId: number) =>
        api.delete<ApiResponse<TeacherDocuments>>(`/teachers/${id}/documents/${mediaId}`),
};

// Class Rooms
export const classRoomsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<ClassRoom>>('/master/class-rooms', { params }),

    get: (id: string) =>
        api.get<ApiResponse<ClassRoom>>(`/master/class-rooms/${id}`),

    create: (data: Partial<ClassRoom>) =>
        api.post<ApiResponse<ClassRoom>>('/master/class-rooms', data),

    update: (id: string, data: Partial<ClassRoom>) =>
        api.put<ApiResponse<ClassRoom>>(`/master/class-rooms/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/master/class-rooms/${id}`),

    students: (id: string) =>
        api.get<ApiResponse<Student[]>>(`/master/class-rooms/${id}/students`),
};

// Academic Years
// `create_semesters` (opsional) minta backend sekalian membuat Semester Ganjil
// & Genap dari rentang tanggal tahun ajaran — lihat AcademicYearController.
export interface AcademicYearPayload {
    name: string;
    start_date: string;
    end_date: string;
    is_active?: boolean;
    create_semesters?: boolean;
}

export const academicYearsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<AcademicYear>>>('/academic/years', { params }),

    get: (id: string) =>
        api.get<ApiResponse<AcademicYear>>(`/academic/years/${id}`),

    create: (data: AcademicYearPayload) =>
        api.post<ApiResponse<AcademicYear>>('/academic/years', data),

    update: (id: string, data: Partial<AcademicYearPayload>) =>
        api.put<ApiResponse<AcademicYear>>(`/academic/years/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/years/${id}`),

    // Route-nya bernama /activate (dulu di sini ditulis /set-active → 404).
    activate: (id: string) =>
        api.post<ApiResponse<AcademicYear>>(`/academic/years/${id}/activate`),
};

// Semesters
export interface SemesterPayload {
    academic_year_id: string;
    name: string;
    semester_number: 1 | 2;
    start_date: string;
    end_date: string;
    is_active?: boolean;
}

export const semestersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Semester>>>('/academic/semesters', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Semester>>(`/academic/semesters/${id}`),

    create: (data: SemesterPayload) =>
        api.post<ApiResponse<Semester>>('/academic/semesters', data),

    update: (id: string, data: Partial<SemesterPayload>) =>
        api.put<ApiResponse<Semester>>(`/academic/semesters/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/semesters/${id}`),

    activate: (id: string) =>
        api.post<ApiResponse<Semester>>(`/academic/semesters/${id}/activate`),
};

// Import result shape (majors & classrooms import)
export interface ImportResult {
    created: number;
    updated: number;
    errors: string[];
    grade_levels_created?: number; // Only for classrooms import
}

// Majors (Jurusan)
export const majorsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Major>>>('/academic/majors', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Major>>(`/academic/majors/${id}`),

    create: (data: Partial<Major>) =>
        api.post<ApiResponse<Major>>('/academic/majors', data),

    update: (id: string, data: Partial<Major>) =>
        api.put<ApiResponse<Major>>(`/academic/majors/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/majors/${id}`),

    export: () =>
        api.get('/academic/majors/export', { responseType: 'blob' }),

    template: () =>
        api.get('/academic/majors/template', { responseType: 'blob' }),

    import: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post<ApiResponse<ImportResult>>('/academic/majors/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
};

// Grade Levels (Tingkat)
export const gradeLevelsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<GradeLevel>>>('/academic/grade-levels', { params }),

    get: (id: string) =>
        api.get<ApiResponse<GradeLevel>>(`/academic/grade-levels/${id}`),

    create: (data: Partial<GradeLevel>) =>
        api.post<ApiResponse<GradeLevel>>('/academic/grade-levels', data),

    update: (id: string, data: Partial<GradeLevel>) =>
        api.put<ApiResponse<GradeLevel>>(`/academic/grade-levels/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/grade-levels/${id}`),
};

// Curricula (Kurikulum)
export const curriculaApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Curriculum>>>('/academic/curricula', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Curriculum>>(`/academic/curricula/${id}`),

    create: (data: Partial<Curriculum>) =>
        api.post<ApiResponse<Curriculum>>('/academic/curricula', data),

    update: (id: string, data: Partial<Curriculum>) =>
        api.put<ApiResponse<Curriculum>>(`/academic/curricula/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/curricula/${id}`),
};

// Subjects (Mata Pelajaran)
export const subjectsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Subject>>>('/academic/subjects', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Subject>>(`/academic/subjects/${id}`),

    create: (data: Partial<Subject>) =>
        api.post<ApiResponse<Subject>>('/academic/subjects', data),

    update: (id: string, data: Partial<Subject>) =>
        api.put<ApiResponse<Subject>>(`/academic/subjects/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/subjects/${id}`),
};

// Classrooms (Kelas - academic)
export const classroomsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Classroom>>>('/academic/classrooms', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Classroom>>(`/academic/classrooms/${id}`),

    create: (data: Partial<Classroom>) =>
        api.post<ApiResponse<Classroom>>('/academic/classrooms', data),

    update: (id: string, data: Partial<Classroom>) =>
        api.put<ApiResponse<Classroom>>(`/academic/classrooms/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/classrooms/${id}`),

    students: (id: string) =>
        api.get<ApiResponse<Student[]>>(`/academic/classrooms/${id}/students`),

    // Guru pengampu (Fase G3) — penempatan manual per tahun ajaran, lihat
    // TEACHER-MODULE-PLAN.md §2/§5. Ditata di sini, bukan di Data Guru.
    getTeachers: (id: string) =>
        api.get<ApiResponse<Array<{ id: string; name: string | null }>>>(`/academic/classrooms/${id}/teachers`),

    syncTeachers: (id: string, teacherIds: string[]) =>
        api.put<ApiResponse<Array<{ id: string; name: string | null }>>>(`/academic/classrooms/${id}/teachers`, {
            teacher_ids: teacherIds,
        }),

    export: () =>
        api.get('/academic/classrooms/export', { responseType: 'blob' }),

    template: () =>
        api.get('/academic/classrooms/template', { responseType: 'blob' }),

    import: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post<ApiResponse<ImportResult>>('/academic/classrooms/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
};

// Time Slots (Jam Pelajaran)
export const timeSlotsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<TimeSlot>>>('/academic/time-slots', { params }),

    get: (id: string) =>
        api.get<ApiResponse<TimeSlot>>(`/academic/time-slots/${id}`),

    create: (data: Partial<TimeSlot>) =>
        api.post<ApiResponse<TimeSlot>>('/academic/time-slots', data),

    update: (id: string, data: Partial<TimeSlot>) =>
        api.put<ApiResponse<TimeSlot>>(`/academic/time-slots/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/time-slots/${id}`),
};

// Schedules (Jadwal Pelajaran)
export const scheduleApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Schedule>>>('/academic/schedules', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Schedule>>(`/academic/schedules/${id}`),

    create: (data: Partial<Schedule>) =>
        api.post<ApiResponse<Schedule>>('/academic/schedules', data),

    update: (id: string, data: Partial<Schedule>) =>
        api.put<ApiResponse<Schedule>>(`/academic/schedules/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/schedules/${id}`),

    exportPdf: (params: { classroom_id: string; semester_id: string }) =>
        api.get('/academic/schedules/export-pdf', { params, responseType: 'blob' }),
};

// Payments
export const paymentsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Payment>>('/finance/payments', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Payment>>(`/finance/payments/${id}`),

    create: (data: Partial<Payment>) =>
        api.post<ApiResponse<Payment>>('/finance/payments', data),

    update: (id: string, data: Partial<Payment>) =>
        api.put<ApiResponse<Payment>>(`/finance/payments/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/payments/${id}`),

    pay: (id: string, data: { payment_method: string; payment_reference?: string; notes?: string }) =>
        api.post<ApiResponse<Payment>>(`/finance/payments/${id}/pay`, data),

    cancel: (id: string, data?: { reason?: string }) =>
        api.post<ApiResponse>(`/finance/payments/${id}/cancel`, data),

    summary: (params?: Record<string, unknown>) =>
        api.get<ApiResponse>('/finance/payments/summary', { params }),
};

// Fee Types
export const feeTypesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<FeeType>>('/finance/fee-types', { params }),

    get: (id: string) =>
        api.get<ApiResponse<FeeType>>(`/finance/fee-types/${id}`),

    create: (data: Partial<FeeType>) =>
        api.post<ApiResponse<FeeType>>('/finance/fee-types', data),

    update: (id: string, data: Partial<FeeType>) =>
        api.put<ApiResponse<FeeType>>(`/finance/fee-types/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/fee-types/${id}`),
};

// Attendance
export const attendanceApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Attendance>>('/attendance', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Attendance>>(`/attendance/${id}`),

    store: (data: { date: string; class_room_id: string; attendances: Array<{ student_id: string; status: string; check_in?: string; check_out?: string; notes?: string }> }) =>
        api.post<ApiResponse>('/attendance', data),

    update: (id: string, data: Partial<Attendance>) =>
        api.put<ApiResponse<Attendance>>(`/attendance/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/attendance/${id}`),

    byClass: (params: { class_room_id: string; date: string }) =>
        api.get<ApiResponse>('/attendance/by-class', { params }),

    summary: (params: { from_date: string; to_date: string; class_room_id?: string; student_id?: string }) =>
        api.get<ApiResponse>('/attendance/summary', { params }),

    studentHistory: (studentId: string, params?: Record<string, unknown>) =>
        api.get<ApiResponse>(`/attendance/student/${studentId}`, { params }),
};

// Users (super admin only)
export const usersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<User>>>('/admin/users', { params }),

    get: (id: string) =>
        api.get<ApiResponse<User>>(`/admin/users/${id}`),

    create: (data: Partial<User> & { password: string; roles?: string[] }) =>
        api.post<ApiResponse<User>>('/admin/users', data),

    update: (id: string, data: Partial<User> & { password?: string; roles?: string[] }) =>
        api.put<ApiResponse<User>>(`/admin/users/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/admin/users/${id}`),

    roles: () =>
        api.get<ApiResponse<Array<{ id: string; name: string }>>>('/admin/users/roles'),

    studentOptions: (search: string) =>
        api.get<ApiResponse<Array<{ id: string; nis: string; name: string | null; class_name: string | null }>>>(
            '/admin/users/student-options',
            { params: { search } }
        ),

    activate: (id: string) =>
        api.post<ApiResponse<User>>(`/admin/users/${id}/activate`),

    deactivate: (id: string) =>
        api.post<ApiResponse<User>>(`/admin/users/${id}/deactivate`),

    resetPassword: (id: string, password: string) =>
        api.post<ApiResponse>(`/admin/users/${id}/reset-password`, { password }),
};

// Database Backups (super admin only)
export const backupsApi = {
    list: () =>
        api.get<ApiResponse<BackupFile[]>>('/super-admin/backups'),

    create: () =>
        api.post<ApiResponse<{ output: string }>>('/super-admin/backups'),

    download: (filename: string) =>
        api.get(`/super-admin/backups/${filename}/download`, { responseType: 'blob' }),

    delete: (filename: string) =>
        api.delete<ApiResponse>(`/super-admin/backups/${filename}`),
};

// Koneksi Database Aplikasi (super admin only, digabung ke menu Backup Database)
export interface DbConnectionInput {
    host: string;
    port: number;
    database: string;
    username: string;
    password?: string;
}

export const dbConnectionApi = {
    accessStatus: () =>
        api.get<ApiResponse<{ configured: boolean }>>('/super-admin/db-connection/access-status'),

    setAccessPassword: (data: { current_password?: string; new_password: string; new_password_confirmation: string }) =>
        api.post<ApiResponse>('/super-admin/db-connection/access-password', data),

    reveal: (accessPassword: string) =>
        api.post<ApiResponse<DbConnectionInfo>>('/super-admin/db-connection/reveal', { access_password: accessPassword }),

    test: (accessPassword: string, data: DbConnectionInput) =>
        api.post<ApiResponse>('/super-admin/db-connection/test', { access_password: accessPassword, ...data }),

    update: (accessPassword: string, data: DbConnectionInput) =>
        api.put<ApiResponse>('/super-admin/db-connection', { access_password: accessPassword, ...data }),

    updateAppVersion: (version: string) =>
        api.put<ApiResponse<{ version: string }>>('/super-admin/app-version', { version }),
};

// Keamanan Login: riwayat login, kode akses sekali-pakai, cabut sesi perangkat.
export interface LoginLogEntry {
    id: string;
    user_id: string | null;
    email: string | null;
    ip_address: string | null;
    user_agent: string | null;
    method: string;
    successful: boolean;
    failure_reason: string | null;
    created_at: string;
    user?: { id: string; full_name: string; email: string } | null;
}

export const loginSecurityApi = {
    logs: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<LoginLogEntry>>>('/admin/login-security/logs', { params }),

    generateOtp: (userId: string) =>
        api.post<ApiResponse<{ code: string; expires_in_minutes: number; user: { id: string; full_name: string; email: string } }>>(
            `/admin/login-security/users/${userId}/otp`
        ),

    revokeOtp: (userId: string) =>
        api.delete<ApiResponse<{ revoked: number }>>(`/admin/login-security/users/${userId}/otp`),

    revokeSessions: (userId: string) =>
        api.post<ApiResponse<{ revoked: number }>>(`/admin/login-security/users/${userId}/revoke-sessions`),
};

export default api;
