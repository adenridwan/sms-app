export interface User {
    id: string;
    username: string;
    email: string;
    first_name: string;
    last_name: string | null;
    full_name: string;
    phone: string | null;
    avatar: string | null;
    avatar_url: string | null;
    is_active: boolean;
    status?: string;
    email_verified_at: string | null;
    last_login_at: string | null;
    user_type?: string;
    must_change_password?: boolean;
    roles: string[];
    permissions?: string[];
    teacher?: {
        id: string;
        nip: string | null;
        join_date: string | null;
        employment_status: string | null;
        education_level: string | null;
        status: string | null;
    } | null;
    staff?: {
        employee_id: string | null;
        join_date: string | null;
        employment_status: string | null;
    } | null;
    guardian_students?: Array<{
        student_id: string;
        relationship: string;
        is_primary_contact: boolean;
        nis?: string | null;
        name?: string | null;
    }>;
    created_at: string;
    updated_at: string;
}

export interface Tenant {
    id: string;
    name: string;
    logo?: string;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    tenant: Tenant | null;
    /** Daftar tenant untuk super admin (tenant switcher); null untuk user biasa */
    tenants?: Tenant[] | null;
    /** Menu yang boleh tampil untuk user (permission + config visibilitas per-role) */
    menu?: {
        visible: string[];
    };
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    app: {
        name: string;
        locale: string;
        timezone: string;
        version: string;
    };
    [key: string]: unknown;
}

export interface PaginationLinks {
    first?: string;
    last?: string;
    prev?: string | null;
    next?: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    links?: PaginationLinks;
    meta?: PaginationMeta;
}

// Student Types
export interface Student {
    id: string;
    nis: string;
    nisn: string | null;
    nik: string | null;
    unique_code?: string | null;
    rfid_code?: string | null;
    user?: User;
    full_name: string;
    email: string;
    gender: 'male' | 'female';
    gender_label: string;
    birth_place: string | null;
    birth_date: string | null;
    age: number | null;
    religion: string | null;
    address: string | null;
    phone: string | null;
    previous_school: string | null;
    entry_date?: string | null;
    entry_type?: string | null;
    entry_year: number;
    entry_class: string | null;
    entry_semester: number;
    status: 'active' | 'graduated' | 'transferred' | 'dropped';
    status_label: string;
    photo: string | null;
    photo_url: string | null;
    current_class?: {
        id: string;
        name: string;
    };
    parents?: StudentParent[];
    created_at: string;
    updated_at: string;
}

export interface StudentParent {
    id: string;
    user?: User;
    full_name: string;
    email: string;
    phone: string | null;
    relationship: 'father' | 'mother' | 'guardian';
    relationship_label: string;
    occupation: string | null;
    income: number | null;
    address: string | null;
    is_primary_contact: boolean;
    created_at: string;
    updated_at: string;
}

// Teacher Types
export interface Teacher {
    id: string;
    user_id?: string;
    unique_code?: string | null;
    rfid_code?: string | null;
    nip: string | null;
    nuptk: string | null;
    username?: string;
    full_name: string;
    first_name?: string | null;
    last_name?: string | null;
    email: string;
    phone: string | null;
    avatar_url?: string | null;
    gender: 'male' | 'female' | null;
    gender_label: string;
    birth_place: string | null;
    birth_date: string | null;
    religion: string | null;
    address: string | null;
    id_number?: string | null;
    education_level: string | null;
    education_major: string | null;
    university: string | null;
    teaching_experience_years?: number | null;
    employment_status: 'permanent' | 'contract' | 'honorary' | 'part_time';
    employment_status_label: string;
    certification_status?: 'certified' | 'not_certified' | 'in_progress';
    certification_status_label?: string;
    certification_number?: string | null;
    join_date: string | null;
    status: 'active' | 'inactive' | 'on_leave' | 'retired' | 'terminated';
    status_label: string;
    account_is_active?: boolean;
    must_change_password?: boolean;
    // Hanya terisi bila di-eager-load server-side (halaman Edit/Detail);
    // tidak ada pada respons daftar (index) — lihat TeacherResource.
    documents?: TeacherDocuments;
    created_at: string;
    updated_at: string;
}

export interface TeacherFormData {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    religion: string;
    address: string;
    id_number: string;
    nip: string;
    nuptk: string;
    join_date: string;
    employment_status: string;
    status: string;
    certification_status: string;
    certification_number: string;
    education_level: string;
    education_major: string;
    university: string;
    teaching_experience_years: string;
}

// Dokumen pemberkasan guru (Fase G2 — semua opsional, lihat TEACHER-MODULE-PLAN.md)
export type TeacherDocumentCollection =
    | 'ijazah'
    | 'ktp'
    | 'npwp'
    | 'sertifikat_pendidik'
    | 'surat_penugasan'
    | 'lainnya';

export interface TeacherDocument {
    id: number;
    name: string;
    url: string;
    mime_type: string | null;
    size: number;
    created_at: string | null;
}

export type TeacherDocuments = Record<TeacherDocumentCollection, TeacherDocument[]>;

// Ringkasan penugasan guru (read-only) — dikelola dari menu Kelas / Jadwal
export interface TeacherAssignmentClassroom {
    id: string;
    name: string;
    students_count: number;
}

export interface TeacherAssignmentSubject {
    id: string;
    name: string;
    code: string;
    is_primary: boolean;
}

export interface TeacherAssignmentSchedule {
    day_of_week: number;
    day_name: string;
    subject: string;
    classroom: string;
    start_time: string;
    end_time: string;
}

export interface TeacherAssignment {
    active_academic_year: string | null;
    classrooms: TeacherAssignmentClassroom[];
    homeroom_classroom: { id: string; name: string } | null;
    subjects: TeacherAssignmentSubject[];
    schedules: TeacherAssignmentSchedule[];
}

// Academic Types
export interface AcademicYear {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    semesters?: Semester[];
    classes_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Semester {
    id: string;
    academic_year_id: string;
    academic_year?: {
        id: string;
        name: string;
    };
    name: string;
    semester_number: 1 | 2;
    start_date: string;
    end_date: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface ClassRoom {
    id: string;
    name: string;
    level: string;
    major: string | null;
    capacity: number;
    academic_year_id: string;
    academic_year?: {
        id: string;
        name: string;
    };
    homeroom_teacher_id: string | null;
    homeroom_teacher?: {
        id: string;
        name: string;
    };
    is_active: boolean;
    students_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Major {
    id: string;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    classrooms_count?: number;
    created_at: string;
    updated_at: string;
}

export interface GradeLevel {
    id: string;
    name: string;
    code: string;
    order: number;
    description: string | null;
    is_active: boolean;
    classrooms_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Curriculum {
    id: string;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    subjects_count?: number;
    created_at: string;
    updated_at: string;
}

export const SUBJECT_CATEGORIES = ['Wajib', 'Peminatan IPA', 'Peminatan IPS', 'Muatan Lokal'] as const;

export type SubjectCategory = (typeof SUBJECT_CATEGORIES)[number];

export interface Subject {
    id: string;
    curriculum_id: string | null;
    curriculum?: { id: string; name: string; code: string | null } | null;
    name: string;
    code: string;
    category: SubjectCategory | null;
    description: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Classroom {
    id: string;
    name: string;
    code: string;
    room: string | null;
    capacity: number;
    is_active: boolean;
    academic_year_id: string;
    academic_year?: {
        id: string;
        name: string;
    };
    grade_level_id: string;
    grade_level?: {
        id: string;
        name: string;
        code: string;
    };
    major_id: string | null;
    major?: {
        id: string;
        name: string;
        code: string;
    } | null;
    homeroom_teacher_id: string | null;
    homeroom_teacher?: {
        id: string;
        name: string;
    } | null;
    students_count?: number;
    created_at: string;
    updated_at: string;
}

export interface TimeSlot {
    id: string;
    name: string;
    start_time: string;
    end_time: string;
    order: number;
    is_break: boolean;
    created_at: string;
    updated_at: string;
}

export interface Schedule {
    id: string;
    academic_year_id: string;
    semester_id: string;
    classroom_id: string;
    subject_id: string;
    subject?: { id: string; name: string; code: string };
    teacher_id: string;
    teacher?: { id: string; full_name: string };
    time_slot_id: string;
    time_slot?: { id: string; name: string; start_time: string; end_time: string; order: number; is_break: boolean };
    day_of_week: number;
    day_name: string;
    room: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Finance Types
export interface Payment {
    id: string;
    invoice_number: string;
    student_id: string;
    student?: {
        id: string;
        nis: string;
        name: string;
    };
    fee_type_id: string;
    fee_type?: {
        id: string;
        name: string;
    };
    amount: number;
    amount_formatted: string;
    discount: number | null;
    final_amount: number;
    due_date: string;
    paid_at: string | null;
    status: 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled';
    status_label: string;
    payment_method: string | null;
    payment_reference: string | null;
    notes: string | null;
    academic_year_id: string;
    semester_id: string | null;
    created_at: string;
    updated_at: string;
}

export interface FeeType {
    id: string;
    code: string;
    name: string;
    description: string | null;
    amount: number;
    amount_formatted: string;
    category: 'tuition' | 'registration' | 'development' | 'activity' | 'other';
    category_label: string;
    is_recurring: boolean;
    recurring_period: 'monthly' | 'semester' | 'yearly' | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Attendance Types
export interface Attendance {
    id: string;
    student_id: string;
    class_room_id: string;
    date: string;
    status: 'present' | 'absent' | 'late' | 'sick' | 'permission';
    check_in: string | null;
    check_out: string | null;
    notes: string | null;
    student?: Student;
    class_room?: ClassRoom;
    created_at: string;
    updated_at: string;
}

export interface BackupFile {
    filename: string;
    size: number;
    created_at: string;
}

export interface DbConnectionInfo {
    connection: string;
    host: string;
    port: number;
    database: string;
    username: string;
}

// API Response types
export interface ApiResponse<T = unknown> {
    success: boolean;
    message: string;
    data: T;
    errors?: Record<string, string[]>;
}

// Dashboard stats
export interface DashboardStats {
    total_students?: number;
    total_teachers?: number;
    total_classes?: number;
    active_academic_year?: string;
    recent_payments?: Array<{
        id: string;
        student_name: string;
        amount: number;
        paid_at: string;
    }>;
    student_by_gender?: Record<string, number>;
    attendance_today?: {
        present: number;
        absent: number;
        late: number;
        sick: number;
        permission: number;
    };
}

export interface Activity {
    id: string;
    description: string;
    created_at: string;
    user?: User;
}
