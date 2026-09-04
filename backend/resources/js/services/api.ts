import axios from 'axios';
import type { ApiResponse, ReportResponse, PaginatedResponse, Student, Teacher, TeacherFormData, TeacherAssignment, TeacherDocumentCollection, TeacherDocuments, ClassRoom, Subject, Curriculum, AcademicYear, Semester, StudentFee, Payment, FeeType, FeeStructure, PaymentMethod, Discount, SalaryGrade, SalaryComponent, BpjsRate, TaxBracket, TaxSetting, EmployeeSalary, PayrollPeriod, PayrollSlip, Attendance, DashboardStats, User, Major, GradeLevel, Classroom, TimeSlot, Schedule, ScheduleCopyResult, BackupFile, DbConnectionInfo } from '@/types';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
    // Axios akan otomatis baca cookie XSRF-TOKEN dan kirim sebagai header X-XSRF-TOKEN
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

// Add tenant context to requests
api.interceptors.request.use((config) => {
    // Tenant context for super admin (chosen via tenant switcher in the header).
    // Regular users are resolved from their own tenant_id server-side.
    const tenantId = localStorage.getItem('active_tenant_id');
    if (tenantId) {
        config.headers['X-Tenant-ID'] = tenantId;
    }

    return config;
});

/**
 * Ambil CSRF cookie dari Laravel Sanctum.
 * HARUS dipanggil sebelum request POST/PUT/DELETE pertama (login, register, dll).
 * Cookie XSRF-TOKEN akan di-set oleh Laravel, lalu axios otomatis kirim sebagai header.
 */
export const getCsrfCookie = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true });

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
    login: async (data: { email: string; password: string; remember?: boolean }) => {
        // Ambil CSRF cookie dulu sebelum POST login
        await getCsrfCookie();
        return api.post<ApiResponse<{ user: User; token: string }>>('/auth/login', data);
    },

    // Tidak mengembalikan token: akun lahir `pending` dan harus diaktifkan
    // dengan kode dari admin lewat authApi.activate().
    register: async (data: { username: string; email: string; password: string; password_confirmation: string; first_name: string; last_name?: string }) => {
        // Ambil CSRF cookie dulu sebelum POST register
        await getCsrfCookie();
        return api.post<ApiResponse<{ user: User; status: string; requires_activation: boolean }>>('/auth/register', data);
    },

    activate: async (data: { email: string; code: string }) => {
        // Sama seperti login/register: endpoint publik ber-session, jadi butuh
        // CSRF cookie lebih dulu.
        await getCsrfCookie();
        return api.post<ApiResponse<{ status: string }>>('/auth/activate', data);
    },

    // "Lupa password": bukan reset password, tapi login pakai kode akses
    // sekali-pakai yang dibuatkan admin lewat menu Keamanan Login (lihat
    // AuthController::loginWithOtp — tidak ada alur reset password/email
    // link di aplikasi ini).
    loginWithOtp: async (data: { email: string; code: string }) => {
        await getCsrfCookie();
        return api.post<ApiResponse<{ user: User; token: string }>>('/auth/login-otp', data);
    },

    logout: () => api.post<ApiResponse>('/auth/logout'),

    me: () => api.get<ApiResponse<User>>('/auth/me'),

    profile: () => api.get<ApiResponse<User>>('/auth/profile'),

    // Route-nya PUT, tapi dikirim sebagai POST + `_method=PUT` (method spoofing
    // Laravel): PHP tidak mem-parse body multipart pada request PUT asli, jadi
    // file avatar tidak akan sampai kalau benar-benar dikirim via PUT.
    updateProfile: (data: FormData) => {
        data.append('_method', 'PUT');
        return api.post<ApiResponse<User>>('/auth/profile', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deleteAvatar: () => api.delete<ApiResponse>('/auth/profile/avatar'),

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

// Staff
export interface Staff {
    id: string;
    user_id: string;
    unique_code?: string;
    rfid_code?: string;
    employee_id?: string;
    username?: string;
    email?: string;
    contact_email?: string;
    email_is_generated?: boolean;
    first_name?: string;
    last_name?: string;
    full_name?: string;
    phone?: string;
    avatar_url?: string | null;
    gender?: string;
    gender_label?: string;
    birth_place?: string;
    birth_date?: string;
    religion?: string;
    address?: string;
    id_number?: string;
    department_id?: string;
    department_name?: string;
    position_id?: string;
    position_name?: string;
    education_level?: string;
    employment_status: string;
    employment_status_label?: string;
    join_date?: string;
    status: string;
    status_label?: string;
    account_is_active?: boolean;
    must_change_password?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface StaffFormData {
    first_name: string;
    last_name: string;
    email: string;
    contact_email: string;
    phone: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    religion: string;
    address: string;
    id_number: string;
    employee_id: string;
    department_id: string;
    position_id: string;
    join_date: string;
    employment_status: string;
    status: string;
    education_level: string;
}

export interface CreateStaffResponse extends Staff {
    initial_username: string;
    initial_password: string;
}

export interface DepartmentOption {
    id: string;
    name: string;
    code: string;
}

export interface PositionOption {
    id: string;
    name: string;
    code: string;
}

export interface StaffOption {
    id: string;
    employee_id?: string;
    full_name?: string;
    join_date?: string;
    employment_status?: string;
}

export const staffApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Staff>>>('/staff', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Staff>>(`/staff/${id}`),

    create: (data: Partial<StaffFormData>) =>
        api.post<ApiResponse<CreateStaffResponse>>('/staff', data),

    update: (id: string, data: Partial<StaffFormData>) =>
        api.put<ApiResponse<Staff>>(`/staff/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/staff/${id}`),

    uploadPhoto: (id: string, file: File) => {
        const formData = new FormData();
        formData.append('photo', file);
        return api.post<ApiResponse<{ avatar_url: string }>>(`/staff/${id}/photo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    deletePhoto: (id: string) =>
        api.delete<ApiResponse>(`/staff/${id}/photo`),

    departments: () =>
        api.get<ApiResponse<DepartmentOption[]>>('/staff/departments'),

    positions: () =>
        api.get<ApiResponse<PositionOption[]>>('/staff/positions'),

    options: () =>
        api.get<ApiResponse<StaffOption[]>>('/staff/options'),
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

    copyFromClassroom: (data: {
        source_classroom_id: string;
        source_semester_id: string;
        target_classroom_id: string;
        target_semester_id: string;
        target_academic_year_id: string;
        overwrite: boolean;
    }) => api.post<ApiResponse<ScheduleCopyResult>>('/academic/schedules/copy-from-classroom', data),

    copyFromDay: (data: {
        academic_year_id: string;
        classroom_id: string;
        semester_id: string;
        source_day_of_week: number;
        target_days: number[];
        overwrite: boolean;
    }) => api.post<ApiResponse<ScheduleCopyResult>>('/academic/schedules/copy-from-day', data),
};

// Student Fees
export const studentFeesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<StudentFee>>>('/finance/fees', { params }),

    get: (id: string) =>
        api.get<ApiResponse<StudentFee>>(`/finance/fees/${id}`),

    create: (data: Partial<StudentFee>) =>
        api.post<ApiResponse<StudentFee>>('/finance/fees', data),

    update: (id: string, data: Partial<StudentFee>) =>
        api.put<ApiResponse<StudentFee>>(`/finance/fees/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/fees/${id}`),

    generate: (data: {
        academic_year_id: string;
        grade_level_id?: string;
        classroom_id?: string;
        fee_type_id?: string;
        month: number;
        year: number;
        due_date: string;
        apply_discounts?: boolean;
    }) => api.post<ApiResponse<{ created: number; skipped: number }>>('/finance/fees/generate', data),

    waive: (id: string, data: { reason: string }) =>
        api.post<ApiResponse>(`/finance/fees/${id}/waive`, data),

    studentHistory: (studentId: string, params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<StudentFee>>>(`/finance/fees/student/${studentId}`, { params }),

    summary: (params?: Record<string, unknown>) =>
        api.get<ApiResponse>('/finance/fees/summary', { params }),

    statuses: () =>
        api.get<ApiResponse<Record<string, string>>>('/finance/fees/statuses'),
};

// Payments
export const paymentsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Payment>>>('/finance/payments', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Payment>>(`/finance/payments/${id}`),

    create: (data: {
        student_id: string;
        payment_method_id: string;
        student_fee_ids: string[];
        amounts: number[];
        notes?: string;
    }) => api.post<ApiResponse<Payment>>('/finance/payments', data),

    complete: (id: string, data?: { transaction_id?: string; notes?: string }) =>
        api.post<ApiResponse<Payment>>(`/finance/payments/${id}/complete`, data),

    uploadProof: (id: string, formData: FormData) =>
        api.post<ApiResponse<Payment>>(`/finance/payments/${id}/upload-proof`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    verify: (id: string, data: { approved: boolean; notes?: string }) =>
        api.post<ApiResponse<Payment>>(`/finance/payments/${id}/verify`, data),

    cancel: (id: string, data?: { reason?: string }) =>
        api.post<ApiResponse>(`/finance/payments/${id}/cancel`, data),

    receipt: (id: string) =>
        api.get<ApiResponse>(`/finance/payments/${id}/receipt`),

    summary: (params?: Record<string, unknown>) =>
        api.get<ApiResponse>('/finance/payments/summary', { params }),

    statuses: () =>
        api.get<ApiResponse<Record<string, string>>>('/finance/payments/statuses'),
};

// Fee Types
export const feeTypesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<FeeType>>>('/finance/fee-types', { params }),

    get: (id: string) =>
        api.get<ApiResponse<FeeType>>(`/finance/fee-types/${id}`),

    create: (data: Partial<FeeType>) =>
        api.post<ApiResponse<FeeType>>('/finance/fee-types', data),

    update: (id: string, data: Partial<FeeType>) =>
        api.put<ApiResponse<FeeType>>(`/finance/fee-types/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/fee-types/${id}`),
};

// Fee Structures
export const feeStructuresApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<FeeStructure>>>('/finance/fee-structures', { params }),

    get: (id: string) =>
        api.get<ApiResponse<FeeStructure>>(`/finance/fee-structures/${id}`),

    create: (data: Partial<FeeStructure>) =>
        api.post<ApiResponse<FeeStructure>>('/finance/fee-structures', data),

    update: (id: string, data: Partial<FeeStructure>) =>
        api.put<ApiResponse<FeeStructure>>(`/finance/fee-structures/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/fee-structures/${id}`),

    bulkCreate: (data: {
        academic_year_id: string;
        grade_level_id: string;
        major_id?: string | null;
        structures: Array<{
            fee_type_id: string;
            amount: number;
            discount_amount?: number;
            due_date?: string;
            due_day?: number;
            is_active?: boolean;
        }>;
    }) => api.post<ApiResponse<FeeStructure[]>>('/finance/fee-structures/bulk', data),
};

// Payment Methods
export const paymentMethodsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<PaymentMethod>>>('/finance/payment-methods', { params }),

    get: (id: string) =>
        api.get<ApiResponse<PaymentMethod>>(`/finance/payment-methods/${id}`),

    create: (data: Partial<PaymentMethod>) =>
        api.post<ApiResponse<PaymentMethod>>('/finance/payment-methods', data),

    update: (id: string, data: Partial<PaymentMethod>) =>
        api.put<ApiResponse<PaymentMethod>>(`/finance/payment-methods/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/payment-methods/${id}`),

    types: () =>
        api.get<ApiResponse<Record<string, string>>>('/finance/payment-methods/types'),
};

// Discounts
export const discountsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<Discount>>>('/finance/discounts', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Discount>>(`/finance/discounts/${id}`),

    create: (data: Partial<Discount>) =>
        api.post<ApiResponse<Discount>>('/finance/discounts', data),

    update: (id: string, data: Partial<Discount>) =>
        api.put<ApiResponse<Discount>>(`/finance/discounts/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/finance/discounts/${id}`),

    types: () =>
        api.get<ApiResponse<Record<string, string>>>('/finance/discounts/types'),
};

// Salary Grades
export const salaryGradesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<SalaryGrade>>>('/payroll/salary-grades', { params }),

    get: (id: string) =>
        api.get<ApiResponse<SalaryGrade>>(`/payroll/salary-grades/${id}`),

    create: (data: Partial<SalaryGrade>) =>
        api.post<ApiResponse<SalaryGrade>>('/payroll/salary-grades', data),

    update: (id: string, data: Partial<SalaryGrade>) =>
        api.put<ApiResponse<SalaryGrade>>(`/payroll/salary-grades/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/salary-grades/${id}`),
};

// Formula item type for salary component calculation
export interface FormulaItem {
    type: 'component' | 'base_salary' | 'gross_salary' | 'number' | 'operator';
    id?: string;
    code?: string;
    name?: string;
    value?: number | string;
    current_value?: number;
    formatted_value?: string;
}

// Salary Components
export const salaryComponentsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<SalaryComponent>>>('/payroll/salary-components', { params }),

    get: (id: string) =>
        api.get<ApiResponse<SalaryComponent>>(`/payroll/salary-components/${id}`),

    create: (data: Partial<SalaryComponent> & { formula?: FormulaItem[] | null }) =>
        api.post<ApiResponse<SalaryComponent>>('/payroll/salary-components', data),

    update: (id: string, data: Partial<SalaryComponent> & { formula?: FormulaItem[] | null }) =>
        api.put<ApiResponse<SalaryComponent>>(`/payroll/salary-components/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/salary-components/${id}`),

    types: () =>
        api.get<ApiResponse<Record<string, string>>>('/payroll/salary-components/types'),

    calculationTypes: () =>
        api.get<ApiResponse<Record<string, string>>>('/payroll/salary-components/calculation-types'),

    percentageReferences: () =>
        api.get<ApiResponse<{
            references: Record<string, string>;
            operators: Record<string, string>;
        }>>('/payroll/salary-components/percentage-references'),

    availableForReference: (excludeId?: string) =>
        api.get<ApiResponse<Array<{
            id: string;
            code: string;
            name: string;
            type: string;
            type_label: string;
            default_value: number;
            default_value_formatted: string;
        }>>>('/payroll/salary-components/available-for-reference', {
            params: excludeId ? { exclude: excludeId } : undefined,
        }),

    validateFormula: (formula: FormulaItem[]) =>
        api.post<ApiResponse<{ valid: boolean; errors: string[] }>>(
            '/payroll/salary-components/validate-formula',
            { formula }
        ),
};

// BPJS Rates
export const bpjsRatesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<BpjsRate>>>('/payroll/bpjs-rates', { params }),

    get: (id: string) =>
        api.get<ApiResponse<BpjsRate>>(`/payroll/bpjs-rates/${id}`),

    create: (data: Partial<BpjsRate>) =>
        api.post<ApiResponse<BpjsRate>>('/payroll/bpjs-rates', data),

    update: (id: string, data: Partial<BpjsRate>) =>
        api.put<ApiResponse<BpjsRate>>(`/payroll/bpjs-rates/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/bpjs-rates/${id}`),

    types: () =>
        api.get<ApiResponse<Record<string, string>>>('/payroll/bpjs-rates/types'),

    currentRates: () =>
        api.get<ApiResponse<BpjsRate[]>>('/payroll/bpjs-rates/current'),
};

// Tax Brackets
export const taxBracketsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<TaxBracket>>>('/payroll/tax-brackets', { params }),

    get: (id: string) =>
        api.get<ApiResponse<TaxBracket>>(`/payroll/tax-brackets/${id}`),

    create: (data: Partial<TaxBracket>) =>
        api.post<ApiResponse<TaxBracket>>('/payroll/tax-brackets', data),

    update: (id: string, data: Partial<TaxBracket>) =>
        api.put<ApiResponse<TaxBracket>>(`/payroll/tax-brackets/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/tax-brackets/${id}`),

    forYear: (year: number) =>
        api.get<ApiResponse<TaxBracket[]>>(`/payroll/tax-brackets/year/${year}`),

    calculate: (data: { pkp: number; year: number }) =>
        api.post<ApiResponse<{
            pkp: number;
            pkp_formatted: string;
            tax: number;
            tax_formatted: string;
            effective_rate: number;
        }>>('/payroll/tax-brackets/calculate', data),
};

// Tax Settings
export const taxSettingsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<TaxSetting>>>('/payroll/tax-settings', { params }),

    get: (id: string) =>
        api.get<ApiResponse<TaxSetting>>(`/payroll/tax-settings/${id}`),

    create: (data: Partial<TaxSetting>) =>
        api.post<ApiResponse<TaxSetting>>('/payroll/tax-settings', data),

    update: (id: string, data: Partial<TaxSetting>) =>
        api.put<ApiResponse<TaxSetting>>(`/payroll/tax-settings/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/tax-settings/${id}`),

    categories: () =>
        api.get<ApiResponse<Record<string, string>>>('/payroll/tax-settings/categories'),

    ptkpLabels: () =>
        api.get<ApiResponse<Record<string, string>>>('/payroll/tax-settings/ptkp-labels'),

    ptkpForYear: (year: number) =>
        api.get<ApiResponse<TaxSetting[]>>(`/payroll/tax-settings/ptkp/year/${year}`),

    getPtkpValue: (data: { status: string; year: number }) =>
        api.post<ApiResponse<{
            status: string;
            year: number;
            value: number;
            value_formatted: string;
        }>>('/payroll/tax-settings/ptkp/value', data),

    biayaJabatanForYear: (year: number) =>
        api.get<ApiResponse<{
            year: number;
            rate: number;
            rate_formatted: string;
            max_per_year: number;
            max_per_year_formatted: string;
            max_per_month: number;
            max_per_month_formatted: string;
        }>>(`/payroll/tax-settings/biaya-jabatan/year/${year}`),
};

// Employee Salaries
export const employeeSalariesApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<EmployeeSalary>>>('/payroll/employee-salaries', { params }),

    get: (id: string) =>
        api.get<ApiResponse<EmployeeSalary>>(`/payroll/employee-salaries/${id}`),

    create: (data: Record<string, unknown>) =>
        api.post<ApiResponse<EmployeeSalary>>('/payroll/employee-salaries', data),

    update: (id: string, data: Record<string, unknown>) =>
        api.put<ApiResponse<EmployeeSalary>>(`/payroll/employee-salaries/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/employee-salaries/${id}`),

    availableEmployees: (params?: Record<string, string>) =>
        api.get<ApiResponse<{ data: Array<{
            id: string;
            type: 'teacher' | 'staff';
            type_label: string;
            identifier: string;
            name: string;
            email?: string;
            employment_status?: string;
        }> }>>('/payroll/employee-salaries/available-employees', { params }),

    ptkpStatuses: () =>
        api.get<ApiResponse<{ data: Array<{ code: string; label: string }> }>>('/payroll/employee-salaries/ptkp-statuses'),

    summary: () =>
        api.get<ApiResponse<{
            total_employees: number;
            total_teachers: number;
            total_staff: number;
            total_base_salary: number;
            total_base_salary_formatted: string;
            average_base_salary: number;
            average_base_salary_formatted: string;
            by_grade: Array<{ grade: string; count: number; total: number; total_formatted: string }>;
        }>>('/payroll/employee-salaries/summary'),

    history: (id: string) =>
        api.get<ApiResponse<{ data: Array<{
            id: string;
            change_type: string;
            change_type_label: string;
            old_grade?: { id: string; code: string; name: string };
            new_grade?: { id: string; code: string; name: string };
            old_base_salary?: number;
            old_base_salary_formatted?: string;
            new_base_salary: number;
            new_base_salary_formatted: string;
            salary_difference: number;
            salary_difference_formatted: string;
            percentage_change: number;
            effective_date: string;
            reason?: string;
            changed_by?: { id: string; name: string };
            created_at: string;
        }> }>>(`/payroll/employee-salaries/${id}/history`),

    syncComponents: (id: string, data: { components: Array<{ salary_component_id: string; value: number; is_active?: boolean }> }) =>
        api.put<ApiResponse<EmployeeSalary>>(`/payroll/employee-salaries/${id}/components`, data),
};

// Payroll Periods
export const payrollPeriodsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<PayrollPeriod>>>('/payroll/periods', { params }),

    get: (id: string) =>
        api.get<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}`),

    create: (data: {
        year: number;
        month: number;
        start_date: string;
        end_date: string;
        payment_date?: string | null;
        notes?: string | null;
    }) => api.post<ApiResponse<PayrollPeriod>>('/payroll/periods', data),

    update: (id: string, data: Partial<{
        start_date: string;
        end_date: string;
        payment_date: string | null;
        notes: string | null;
    }>) => api.put<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/payroll/periods/${id}`),

    statuses: () =>
        api.get<ApiResponse<{ data: Record<string, string> }>>('/payroll/periods/statuses'),

    generateSlips: (id: string) =>
        api.post<ApiResponse<{
            slips_created: number;
            attendance_processed: number;
            attendance_errors?: Array<{ slip_id: string; error: string }>;
        }>>(`/payroll/periods/${id}/generate-slips`),

    getGenerateProgress: (id: string) =>
        api.get<ApiResponse<{
            status: 'idle' | 'processing' | 'completed' | 'error';
            current: number;
            total: number;
            percentage: number;
            message: string;
            result?: {
                slips_created: number;
                attendance_processed: number;
                attendance_errors?: Array<{ slip_id: string; error: string }>;
            };
        }>>(`/payroll/periods/${id}/generate-progress`),

    calculate: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/calculate`),

    submitForApproval: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/submit-for-approval`),

    approve: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/approve`),

    markAsPaid: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/mark-as-paid`),

    finalize: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/finalize`),

    unfinalize: (id: string) =>
        api.post<ApiResponse<PayrollPeriod>>(`/payroll/periods/${id}/unfinalize`),

    syncEmployees: (id: string) =>
        api.post<ApiResponse<{ added: number }>>(`/payroll/periods/${id}/sync-employees`),

    summary: (id: string) =>
        api.get<ApiResponse>(`/payroll/periods/${id}/summary`),

    // Attendance integration
    generateSlipsWithAttendance: (id: string) =>
        api.post<ApiResponse<{ slips_created: number; attendance_processed: number; attendance_errors?: Array<{ slip_id: string; error: string }> }>>(
            `/payroll/periods/${id}/generate-slips-with-attendance`
        ),

    calculateAttendance: (id: string) =>
        api.post<ApiResponse<{ processed: number; errors: Array<{ slip_id: string; error: string }> }>>(
            `/payroll/periods/${id}/calculate-attendance`
        ),

    // PDF Export
    getExportPdfUrl: (id: string, employeeType?: 'teacher' | 'staff') => {
        const params = employeeType ? `?employee_type=${employeeType}` : '';
        return `/api/v1/payroll/periods/${id}/export-pdf${params}`;
    },

    // WhatsApp
    previewWhatsAppRecipients: (id: string, employeeType?: 'teacher' | 'staff') =>
        api.get<ApiResponse<{
            recipients: Array<{
                id: string;
                name: string;
                type: string;
                type_label: string;
                phone: string;
                net_salary: number;
                net_salary_formatted: string;
            }>;
            no_phone: Array<{
                id: string;
                name: string;
                type: string;
                type_label: string;
            }>;
            total: number;
            total_no_phone: number;
        }>>(`/payroll/periods/${id}/whatsapp-preview`, { params: { employee_type: employeeType } }),

    sendWhatsAppBulk: (id: string, employeeType?: 'teacher' | 'staff') =>
        api.post<ApiResponse<{
            success: boolean;
            sent: number;
            failed: number;
            no_phone: string[];
            errors: Array<{ employee: string; message: string }>;
            message: string;
        }>>(`/payroll/periods/${id}/send-whatsapp`, { employee_type: employeeType }),
};

// Payroll Slips
export const payrollSlipsApi = {
    list: (periodId: string, params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<PayrollSlip>>>(`/payroll/periods/${periodId}/slips`, { params }),

    get: (id: string) =>
        api.get<ApiResponse<PayrollSlip>>(`/payroll/slips/${id}`),

    updateItems: (id: string, data: { items: Array<{
        id?: string;
        salary_component_id?: string;
        component_code: string;
        component_name: string;
        type: 'earning' | 'deduction';
        category: string;
        amount: number;
        quantity?: number;
        rate?: number;
        is_taxable?: boolean;
        is_auto_calculated?: boolean;
        notes?: string;
    }> }) => api.put<ApiResponse<PayrollSlip>>(`/payroll/slips/${id}/items`, data),

    addItem: (id: string, data: {
        salary_component_id?: string;
        component_code?: string;
        component_name?: string;
        type?: 'earning' | 'deduction';
        category?: string;
        amount: number;
        quantity?: number;
        rate?: number;
        is_taxable?: boolean;
        notes?: string;
    }) => api.post<ApiResponse<PayrollSlip>>(`/payroll/slips/${id}/items`, data),

    removeItem: (slipId: string, itemId: string) =>
        api.delete<ApiResponse<PayrollSlip>>(`/payroll/slips/${slipId}/items/${itemId}`),

    updateItem: (slipId: string, itemId: string, data: {
        quantity?: number;
        rate?: number;
        amount?: number;
        notes?: string;
        reason: string;
    }) => api.put<ApiResponse<PayrollSlip>>(`/payroll/slips/${slipId}/items/${itemId}`, data),

    updateNotes: (id: string, notes: string | null) =>
        api.put<ApiResponse<PayrollSlip>>(`/payroll/slips/${id}/notes`, { notes }),

    getAudits: (id: string) =>
        api.get<ApiResponse<{ audits: Array<{
            id: string;
            item: { id: string; component_code: string; component_name: string } | null;
            field: string;
            field_label: string;
            old_value: string | null;
            new_value: string | null;
            old_value_formatted: string;
            new_value_formatted: string;
            reason: string;
            changed_by: { id: string; name: string } | null;
            changed_at: string;
        }>; total: number }>>(`/payroll/slips/${id}/audits`),

    printData: (id: string) =>
        api.get<ApiResponse>(`/payroll/slips/${id}/print`),

    getComponents: (params?: { type?: 'earning' | 'deduction' }) =>
        api.get<ApiResponse<Array<{
            id: string;
            code: string;
            name: string;
            type: 'earning' | 'deduction';
            type_label: string;
            calculation_type: string;
            calculation_type_label: string;
            default_value: number;
            default_value_formatted: string;
            is_taxable: boolean;
            is_active: boolean;
        }>>>('/payroll/slips/components', { params }),

    // PDF Export
    getPdfUrl: (id: string) => `/api/v1/payroll/slips/${id}/pdf`,
    getPreviewPdfUrl: (id: string) => `/api/v1/payroll/slips/${id}/pdf/preview`,

    // WhatsApp
    getEmployeePhone: (id: string) =>
        api.get<ApiResponse<{ phone: string | null; phone_masked: string | null; has_phone: boolean }>>(`/payroll/slips/${id}/phone`),

    sendWhatsApp: (id: string, phone?: string) =>
        api.post<ApiResponse<{ success: boolean; message: string }>>(`/payroll/slips/${id}/send-whatsapp`, { phone }),
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

    staffOptions: (userId?: string) =>
        api.get<ApiResponse<Array<{
            id: string;
            employee_id: string | null;
            join_date: string | null;
            employment_status: string | null;
            user_id: string | null;
            user_name: string | null;
        }>>>('/admin/users/staff-options', { params: { user_id: userId } }),

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

    activeDatabase: () =>
        api.get<ApiResponse<{ database: string }>>('/super-admin/backups/active-database'),

    create: () =>
        api.post<ApiResponse<{ output: string }>>('/super-admin/backups'),

    download: (filename: string) =>
        api.get(`/super-admin/backups/${filename}/download`, { responseType: 'blob' }),

    delete: (filename: string) =>
        api.delete<ApiResponse>(`/super-admin/backups/${filename}`),

    restore: (filename: string, confirmDatabase: string) =>
        api.post<ApiResponse<{ output: string }>>(`/super-admin/backups/${filename}/restore`, {
            confirm_database: confirmDatabase,
        }),

    import: (file: File, confirmDatabase: string) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('confirm_database', confirmDatabase);
        return api.post<ApiResponse<{ output: string }>>('/super-admin/backups/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
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

// QR Code Provisioning: admin generate QR untuk user login di mobile tanpa ketik password
export interface ProvisionTokenResponse {
    provision_token: string;
    expires_at: string;
    expires_in_minutes: number;
    user: { id: string; name: string; email: string };
    qr_content: string;
}

export interface ProvisionHistoryEntry {
    id: string;
    created_at: string;
    expires_at: string;
    redeemed_at: string | null;
    is_active: boolean;
    created_by: { id: string; name: string } | null;
}

export const provisionApi = {
    generate: (userId: string) =>
        api.post<ApiResponse<ProvisionTokenResponse>>('/admin/provision', { user_id: userId }),

    history: (userId: string) =>
        api.get<ApiResponse<{
            user: { id: string; name: string };
            tokens: ProvisionHistoryEntry[];
        }>>(`/admin/provision/users/${userId}`),
};

// Finance Reports
export const financeReportsApi = {
    dashboard: <T = unknown>(params?: Record<string, unknown>) =>
        api.get<ApiResponse<T>>('/finance/reports/dashboard', { params }),

    outstanding: <TRow = unknown>(params?: Record<string, unknown>) =>
        api.get<ReportResponse<TRow>>('/finance/reports/outstanding', { params }),

    byClassroom: <TRow = unknown>(params?: Record<string, unknown>) =>
        api.get<ReportResponse<TRow>>('/finance/reports/by-classroom', { params }),

    monthly: <TRow = unknown>(params: { month: number; year: number; academic_year_id?: string; classroom_id?: string; status?: string; per_page?: number }) =>
        api.get<ReportResponse<TRow>>('/finance/reports/monthly', { params }),

    studentHistory: (studentId: string) =>
        api.get<ApiResponse>(`/finance/reports/student/${studentId}/history`),

    exportOutstanding: (params?: Record<string, unknown>) =>
        api.get('/finance/reports/export/outstanding', { params, responseType: 'blob' }),

    exportByClassroom: (params?: Record<string, unknown>) =>
        api.get('/finance/reports/export/by-classroom', { params, responseType: 'blob' }),

    exportMonthly: (params: { month: number; year: number; academic_year_id?: string; classroom_id?: string }) =>
        api.get('/finance/reports/export/monthly', { params, responseType: 'blob' }),
};

// Payroll Reports
export const payrollReportsApi = {
    dashboard: <T = unknown>(params?: { year?: number }) =>
        api.get<ApiResponse<T>>('/payroll/reports/dashboard', { params }),

    monthlyRecap: <TRow = unknown>(params: { year: number; month?: number }) =>
        api.get<ReportResponse<TRow>>('/payroll/reports/monthly-recap', { params }),

    pph21: <TRow = unknown>(params: { year: number; month?: number; period_id?: string; per_page?: number }) =>
        api.get<ReportResponse<TRow>>('/payroll/reports/pph21', { params }),

    bpjs: <TRow = unknown>(params: { year: number; month?: number; period_id?: string; type?: string; per_page?: number }) =>
        api.get<ReportResponse<TRow>>('/payroll/reports/bpjs', { params }),

    employeeHistory: (employeeId: string, params: { employee_type: 'teacher' | 'staff' }) =>
        api.get<ApiResponse>(`/payroll/reports/employee/${employeeId}/history`, { params }),

    exportMonthlyRecap: (params: { year: number }) =>
        api.get('/payroll/reports/export/monthly-recap', { params, responseType: 'blob' }),

    exportPph21: (params: { year: number; month?: number }) =>
        api.get('/payroll/reports/export/pph21', { params, responseType: 'blob' }),

    exportBpjs: (params: { year: number; month?: number }) =>
        api.get('/payroll/reports/export/bpjs', { params, responseType: 'blob' }),
};

// Expense Reports (Laporan Pengeluaran)
export const expenseReportsApi = {
    monthly: (params: { year: number; month?: number }) =>
        api.get<ApiResponse<unknown>>('/finance/reports/monthly-expense', { params }),

    exportMonthly: (params: { year: number; month?: number }) =>
        api.get('/finance/reports/export/monthly-expense', { params, responseType: 'blob' }),
};

// Device Monitor: monitoring perangkat scanner
export interface ServerInfo {
    status: string;
    local_ip: string;
    port: number;
    base_url: string;
    api_url: string;
    public_url: string | null;
    server_time: string;
}

export interface DeviceSummary {
    total: number;
    online: number;
    idle: number;
    offline: number;
    pending_sync_total: number;
}

export interface ScannerDevice {
    id: string;
    device_name: string;
    location: string | null;
    is_active: boolean;
    connection_status: 'online' | 'idle' | 'offline';
    last_seen: string | null;
    last_heartbeat_at: string | null;
    last_scan_at: string | null;
    pending_sync_count: number;
    user: { id: string; name: string; email: string } | null;
    // Detail fields (only in show)
    app_version?: string | null;
    os_version?: string | null;
    device_model?: string | null;
    battery_level?: number | null;
    battery_charging?: boolean;
    network_type?: string | null;
    network_name?: string | null;
    latency_ms?: number | null;
    created_at?: string;
    updated_at?: string;
}

export interface DeviceTodayStats {
    total_scans: number;
    check_in: number;
    check_out: number;
    synced: number;
    failed: number;
    pending: number;
}

export interface DeviceProvisionQr {
    qr_content: string;
    provision_token: string;
    server_url: string;
    expires_at: string;
    expires_in_minutes: number;
    user: { id: string; name: string; email: string };
    device_info: { name: string | null; location: string | null };
}

export const deviceMonitorApi = {
    serverInfo: () =>
        api.get<ApiResponse<ServerInfo>>('/admin/devices/server-info'),

    summary: () =>
        api.get<ApiResponse<DeviceSummary>>('/admin/devices/summary'),

    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<{ data: ScannerDevice[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }>>('/admin/devices', { params }),

    get: (id: string) =>
        api.get<ApiResponse<{ device: ScannerDevice; today_stats: DeviceTodayStats }>>(`/admin/devices/${id}`),

    create: (data: { device_name: string; location?: string; user_id?: string }) =>
        api.post<ApiResponse<ScannerDevice>>('/admin/devices', data),

    update: (id: string, data: Partial<{ device_name: string; location: string; user_id: string; is_active: boolean }>) =>
        api.put<ApiResponse<ScannerDevice>>(`/admin/devices/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/admin/devices/${id}`),

    regenerateToken: (id: string) =>
        api.post<ApiResponse<{ device_token: string }>>(`/admin/devices/${id}/regenerate-token`),

    generateProvisionQr: (data: { user_id: string; device_name?: string; location?: string }) =>
        api.post<ApiResponse<DeviceProvisionQr>>('/admin/devices/provision-qr', data),
};

export default api;
