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
        id?: string;
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
    /** Identitas login — bisa hasil generate (docs/EMAIL-OTOMATIS-AKUN.md). */
    email: string;
    /** Alamat surat sungguhan: tujuan OTP & notifikasi. */
    contact_email?: string | null;
    email_is_generated?: boolean;
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
    /** Identitas login — bisa hasil generate (docs/EMAIL-OTOMATIS-AKUN.md). */
    email: string;
    /** Alamat surat sungguhan: tujuan OTP & notifikasi kehadiran. */
    contact_email?: string | null;
    email_is_generated?: boolean;
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
    /** Identitas login — boleh kosong, dibuatkan otomatis (docs/EMAIL-OTOMATIS-AKUN.md). */
    email: string;
    /** Alamat surat sungguhan: tujuan OTP & notifikasi. */
    contact_email: string;
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

// Staff Types
export interface Staff {
    id: string;
    user_id: string;
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

export interface ScheduleCopyResult {
    copied: number;
    skipped: string[];
}

// Finance Types
export interface StudentFee {
    id: string;
    student_id: string;
    student?: {
        id: string;
        nis: string;
        name: string;
        classroom?: {
            id: string;
            name: string;
        } | null;
    };
    fee_structure_id: string;
    fee_structure?: {
        id: string;
        fee_type?: {
            id: string;
            code: string;
            name: string;
            frequency: string;
        } | null;
        grade_level?: {
            id: string;
            name: string;
        } | null;
    };
    academic_year_id: string;
    academic_year?: {
        id: string;
        name: string;
        is_active: boolean;
    };
    month: number | null;
    year: number | null;
    period_label: string;
    amount: number;
    amount_formatted: string;
    discount: number;
    discount_formatted: string;
    fine: number;
    fine_formatted: string;
    total_amount: number;
    total_amount_formatted: string;
    paid_amount: number;
    paid_amount_formatted: string;
    remaining_amount: number;
    remaining_amount_formatted: string;
    due_date: string | null;
    status: 'unpaid' | 'partial' | 'paid' | 'overdue' | 'waived';
    status_label: string;
    is_overdue: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface PaymentItem {
    id: string;
    student_fee_id: string;
    amount: number;
    amount_formatted: string;
    student_fee?: {
        id: string;
        period_label: string;
        fee_type?: {
            id: string;
            name: string;
        } | null;
    } | null;
}

export interface Payment {
    id: string;
    invoice_number: string;
    student_id: string;
    student?: {
        id: string;
        nis: string;
        name: string;
        classroom?: {
            id: string;
            name: string;
        } | null;
    };
    payment_method_id: string | null;
    payment_method?: {
        id: string;
        code: string;
        name: string;
        type: string;
    } | null;
    total_amount: number;
    total_amount_formatted: string;
    admin_fee: number;
    admin_fee_formatted: string;
    grand_total: number;
    grand_total_formatted: string;
    status: 'pending' | 'processing' | 'completed' | 'failed' | 'refunded' | 'cancelled';
    status_label: string;
    paid_at: string | null;
    transaction_id: string | null;
    payment_proof: string | null;
    notes: string | null;
    received_by: string | null;
    received_by_user?: {
        id: string;
        name: string;
    } | null;
    verified_by: string | null;
    verified_by_user?: {
        id: string;
        name: string;
    } | null;
    verified_at: string | null;
    payment_details: Record<string, unknown> | null;
    items?: PaymentItem[];
    items_count?: number;
    can_be_verified: boolean;
    can_be_cancelled: boolean;
    created_at: string;
    updated_at: string;
}

export interface FeeType {
    id: string;
    code: string;
    name: string;
    description: string | null;
    frequency: 'once' | 'monthly' | 'semester' | 'yearly';
    frequency_label: string;
    is_mandatory: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface FeeStructure {
    id: string;
    academic_year_id: string;
    academic_year?: {
        id: string;
        name: string;
        is_active: boolean;
    };
    fee_type_id: string;
    fee_type?: {
        id: string;
        code: string;
        name: string;
        frequency: string;
    };
    grade_level_id: string;
    grade_level?: {
        id: string;
        name: string;
        level: number;
    };
    major_id: string | null;
    major?: {
        id: string;
        code: string;
        name: string;
    } | null;
    amount: number;
    amount_formatted: string;
    discount_amount: number;
    discount_amount_formatted: string;
    effective_amount: number;
    effective_amount_formatted: string;
    due_date: string | null;
    due_day: number | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface PaymentMethod {
    id: string;
    code: string;
    name: string;
    type: 'cash' | 'bank_transfer' | 'virtual_account' | 'e_wallet' | 'credit_card' | 'other';
    type_label: string;
    provider: string | null;
    configuration: Record<string, unknown> | null;
    admin_fee: number;
    admin_fee_formatted: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Discount {
    id: string;
    code: string;
    name: string;
    description: string | null;
    type: 'percentage' | 'fixed';
    type_label: string;
    value: number;
    value_formatted: string;
    fee_type_id: string | null;
    fee_type?: {
        id: string;
        name: string;
        code: string;
    } | null;
    valid_from: string | null;
    valid_until: string | null;
    is_valid: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Payroll Types
export interface SalaryGrade {
    id: string;
    code: string;
    name: string;
    base_salary: number;
    base_salary_formatted: string;
    description: string | null;
    order: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface SalaryComponent {
    id: string;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    type_label: string;
    calculation_type: 'fixed' | 'percentage' | 'per_day' | 'per_hour' | 'formula';
    calculation_type_label: string;
    default_value: number;
    default_value_formatted: string;
    percentage_of: string | null;
    percentage_of_label?: string | null;
    percentage_component_id?: string | null;
    percentage_component?: {
        id: string;
        code: string;
        name: string;
    } | null;
    formula: Array<{
        type: 'component' | 'base_salary' | 'gross_salary' | 'number' | 'operator';
        id?: string;
        code?: string;
        name?: string;
        value?: number | string;
    }> | null;
    formula_display?: string | null;
    is_taxable: boolean;
    is_mandatory: boolean;
    is_active: boolean;
    order: number;
    description: string | null;
    created_at: string;
    updated_at: string;
}

export interface BpjsRate {
    id: string;
    type: 'kesehatan' | 'jht' | 'jkk' | 'jkm' | 'jp';
    type_label: string;
    name: string;
    employee_rate: number;
    employee_rate_formatted: string;
    employer_rate: number;
    employer_rate_formatted: string;
    total_rate: number;
    total_rate_formatted: string;
    min_salary: number | null;
    min_salary_formatted: string | null;
    max_salary: number | null;
    max_salary_formatted: string | null;
    effective_from: string;
    effective_until: string | null;
    is_effective: boolean;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface TaxBracket {
    id: string;
    min_amount: number;
    min_amount_formatted: string;
    max_amount: number | null;
    max_amount_formatted: string;
    range_label: string;
    rate: number;
    rate_formatted: string;
    effective_year: number;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface TaxSetting {
    id: string;
    setting_key: string;
    setting_name: string;
    setting_value: number;
    setting_value_formatted: string;
    category: 'ptkp' | 'biaya_jabatan' | 'ter' | 'other';
    category_label: string;
    description: string | null;
    effective_year: number;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface EmployeeSalaryComponent {
    id: string;
    salary_component_id: string;
    salary_component?: {
        id: string;
        code: string;
        name: string;
        type: 'earning' | 'deduction';
        type_label: string;
        calculation_type: string;
    };
    value: number;
    value_formatted: string;
    is_active: boolean;
}

export interface EmployeeSalary {
    id: string;
    employee_type: 'teacher' | 'staff';
    employee_type_label: string;
    employee_id: string;
    employee?: {
        id: string;
        nip?: string;
        employee_id?: string;
        name: string;
        email?: string;
        employment_status?: string;
    };
    salary_grade_id: string;
    salary_grade?: {
        id: string;
        code: string;
        name: string;
        base_salary: number;
        base_salary_formatted: string;
    };
    base_salary: number;
    base_salary_formatted: string;
    ptkp_status: string;
    effective_date: string;
    end_date?: string;
    is_current: boolean;
    notes?: string;
    components?: EmployeeSalaryComponent[];
    total_earnings?: number;
    total_deductions?: number;
    total_earnings_formatted?: string;
    total_deductions_formatted?: string;
    components_count?: number;
    created_at: string;
    updated_at: string;
}

export interface PayrollPeriod {
    id: string;
    name: string;
    year: number;
    month: number;
    period_label: string;
    start_date: string;
    end_date: string;
    payment_date?: string;
    status: 'draft' | 'processing' | 'pending_approval' | 'approved' | 'paid' | 'finalized';
    status_label: string;
    is_draft: boolean;
    is_editable: boolean;
    is_finalized: boolean;
    can_generate_slips: boolean;
    can_approve: boolean;
    can_finalize: boolean;
    approved_by?: { id: string; name: string };
    approved_at?: string;
    finalized_by?: { id: string; name: string };
    finalized_at?: string;
    notes?: string;
    total_gross: number;
    total_gross_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    total_net: number;
    total_net_formatted: string;
    employee_count: number;
    slips_count?: number;
    created_at: string;
    updated_at: string;
}

export interface PayrollSlipItem {
    id: string;
    salary_component_id?: string;
    component_code: string;
    component_name: string;
    type: 'earning' | 'deduction';
    type_label: string;
    category: string;
    category_label: string;
    amount: number;
    amount_formatted: string;
    quantity: number;
    rate?: number;
    rate_formatted?: string;
    is_taxable: boolean;
    is_auto_calculated: boolean;
    notes?: string;
}

export interface PayrollSlip {
    id: string;
    payroll_period_id: string;
    period?: {
        id: string;
        name: string;
        year: number;
        month: number;
        period_label: string;
        status: string;
    };
    employee_salary_id: string;
    employee_type: 'teacher' | 'staff';
    employee_type_label: string;
    employee_id: string;
    employee_name: string;
    employee_identifier?: string;
    salary_grade_code?: string;
    ptkp_status: string;
    base_salary: number;
    base_salary_formatted: string;
    total_allowances: number;
    total_allowances_formatted: string;
    total_overtime: number;
    total_overtime_formatted: string;
    total_other_income: number;
    total_other_income_formatted: string;
    gross_salary: number;
    gross_salary_formatted: string;
    bpjs_kesehatan: number;
    bpjs_kesehatan_formatted: string;
    bpjs_jht: number;
    bpjs_jht_formatted: string;
    bpjs_jp: number;
    bpjs_jp_formatted: string;
    pph21: number;
    pph21_formatted: string;
    total_other_deductions: number;
    total_other_deductions_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    net_salary: number;
    net_salary_formatted: string;
    working_days: number;
    days_present: number;
    days_absent: number;
    days_late: number;
    days_leave: number;
    attendance_deduction: number;
    attendance_deduction_formatted: string;
    status: 'draft' | 'calculated' | 'approved' | 'paid';
    status_label: string;
    is_editable: boolean;
    calculated_by?: { id: string; name: string };
    calculated_at?: string;
    notes?: string;
    items?: PayrollSlipItem[];
    earnings?: { id: string; code: string; name: string; amount: number; amount_formatted: string }[];
    deductions?: { id: string; code: string; name: string; amount: number; amount_formatted: string }[];
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

/**
 * Respons endpoint laporan (keuangan & penggajian).
 *
 * Berbeda dari [ApiResponse] biasa: selain `data`, endpoint laporan
 * mengembalikan agregat di level atas — `summary` (total keseluruhan),
 * `meta` (paginasi), dan pada beberapa laporan `monthly_breakdown`.
 * Digenerikkan agar pemanggil bisa menyebut bentuk ringkasannya sendiri.
 */
export interface ReportResponse<TRow = unknown> extends ApiResponse<TRow[]> {
    // `summary` & `monthly_breakdown` berbeda bentuk per laporan dan belum
    // ditipekan di sisi server, jadi dibiarkan `unknown` — pemanggil yang
    // menegaskan bentuknya. Baris datanya sendiri tetap bertipe lewat TRow.
    summary?: unknown;
    monthly_breakdown?: unknown;
    meta?: PaginationMeta;
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
