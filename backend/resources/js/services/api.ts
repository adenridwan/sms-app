import axios from 'axios';
import type { ApiResponse, PaginatedResponse, Student, Teacher, ClassRoom, Subject, AcademicYear, Semester, Payment, FeeType, Attendance, DashboardStats, User, Major, GradeLevel, Classroom } from '@/types';

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

    changePassword: (data: { current_password: string; password: string; password_confirmation: string }) =>
        api.post<ApiResponse>('/auth/password', data),
};

// Dashboard
export const dashboardApi = {
    getStats: () => api.get<ApiResponse<DashboardStats>>('/dashboard'),
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

    update: (id: string, data: FormData) =>
        api.post<ApiResponse<Student>>(`/students/${id}`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/students/${id}`),

    bulkDelete: (ids: string[]) =>
        api.post<ApiResponse>('/students/bulk-destroy', { ids }),

    export: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<{ url: string }>>('/students/export', { params }),

    import: (file: File) => {
        const formData = new FormData();
        formData.append('file', file);
        return api.post<ApiResponse>('/students/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
};

// Teachers
export const teachersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Teacher>>('/teachers', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Teacher>>(`/teachers/${id}`),

    create: (data: FormData) =>
        api.post<ApiResponse<Teacher>>('/teachers', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    update: (id: string, data: FormData) =>
        api.post<ApiResponse<Teacher>>(`/teachers/${id}`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/teachers/${id}`),

    schedules: (id: string) =>
        api.get<ApiResponse>(`/teachers/${id}/schedules`),

    attendance: (id: string, params?: Record<string, unknown>) =>
        api.get<ApiResponse>(`/teachers/${id}/attendance`, { params }),
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

// Subjects
export const subjectsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Subject>>('/master/subjects', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Subject>>(`/master/subjects/${id}`),

    create: (data: Partial<Subject>) =>
        api.post<ApiResponse<Subject>>('/master/subjects', data),

    update: (id: string, data: Partial<Subject>) =>
        api.put<ApiResponse<Subject>>(`/master/subjects/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/master/subjects/${id}`),
};

// Academic Years
export const academicYearsApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<ApiResponse<PaginatedResponse<AcademicYear>>>('/academic/years', { params }),

    get: (id: string) =>
        api.get<ApiResponse<AcademicYear>>(`/academic/years/${id}`),

    create: (data: Partial<AcademicYear>) =>
        api.post<ApiResponse<AcademicYear>>('/academic/years', data),

    update: (id: string, data: Partial<AcademicYear>) =>
        api.put<ApiResponse<AcademicYear>>(`/academic/years/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/years/${id}`),

    setActive: (id: string) =>
        api.post<ApiResponse<AcademicYear>>(`/academic/years/${id}/set-active`),
};

// Semesters
export const semestersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<Semester>>('/academic/semesters', { params }),

    get: (id: string) =>
        api.get<ApiResponse<Semester>>(`/academic/semesters/${id}`),

    create: (data: Partial<Semester>) =>
        api.post<ApiResponse<Semester>>('/academic/semesters', data),

    update: (id: string, data: Partial<Semester>) =>
        api.put<ApiResponse<Semester>>(`/academic/semesters/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/academic/semesters/${id}`),

    setActive: (id: string) =>
        api.post<ApiResponse<Semester>>(`/academic/semesters/${id}/set-active`),
};

// Import result shape (majors & classrooms import)
export interface ImportResult {
    created: number;
    updated: number;
    errors: string[];
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

// Users
export const usersApi = {
    list: (params?: Record<string, unknown>) =>
        api.get<PaginatedResponse<User>>('/users', { params }),

    get: (id: string) =>
        api.get<ApiResponse<User>>(`/users/${id}`),

    create: (data: Partial<User> & { password: string; roles?: string[] }) =>
        api.post<ApiResponse<User>>('/users', data),

    update: (id: string, data: Partial<User> & { password?: string; roles?: string[] }) =>
        api.put<ApiResponse<User>>(`/users/${id}`, data),

    delete: (id: string) =>
        api.delete<ApiResponse>(`/users/${id}`),

    roles: () =>
        api.get<ApiResponse<Array<{ id: string; name: string }>>>('/users/roles'),

    toggleActive: (id: string) =>
        api.post<ApiResponse<User>>(`/users/${id}/toggle-active`),
};

export default api;
