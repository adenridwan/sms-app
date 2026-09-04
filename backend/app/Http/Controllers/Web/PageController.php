<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Http\Resources\TeacherResource;
use App\Infrastructure\Persistence\Eloquent\Academic\AcademicYear;
use App\Infrastructure\Persistence\Eloquent\Academic\Classroom;
use App\Infrastructure\Persistence\Eloquent\Student\Student;
use App\Infrastructure\Persistence\Eloquent\Teacher\Teacher;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Display the dashboard with real per-role stats (R5).
     */
    public function dashboard(\App\Services\DashboardStatsService $stats): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $stats->statsFor(request()->user()),
        ]);
    }

    /**
     * Display students list.
     */
    public function students(): Response
    {
        $filters = request()->only(['search', 'status', 'gender', 'classroom_id']);

        $students = Student::visibleTo(request()->user())
            ->with(['user.profile', 'currentClass'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nis', 'ilike', "%{$search}%")
                        ->orWhere('nisn', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('username', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['gender'] ?? null, fn ($q, $gender) => $q->whereHas(
                'user.profile',
                fn ($p) => $p->where('gender', $gender)
            ))
            // Di-AND dengan visibleTo() di atas, jadi guru yang mengarang
            // classroom_id kelas lain tetap tidak mendapat baris apa pun.
            ->when($filters['classroom_id'] ?? null, fn ($q, $classroomId) => $q->whereHas(
                'enrollments',
                fn ($e) => $e->where('status', 'active')->where('classroom_id', $classroomId)
            ))
            ->orderBy('nis')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('students/Index', [
            'students' => [
                'data' => StudentResource::collection($students->items())->resolve(),
                'meta' => [
                    'current_page' => $students->currentPage(),
                    'from' => $students->firstItem() ?? 0,
                    'last_page' => $students->lastPage(),
                    'per_page' => $students->perPage(),
                    'to' => $students->lastItem() ?? 0,
                    'total' => $students->total(),
                ],
                'links' => [
                    'prev' => $students->previousPageUrl(),
                    'next' => $students->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'classrooms' => $this->filterableClassrooms(request()->user()),
        ]);
    }

    /**
     * Pilihan kelas untuk filter di halaman Data Siswa.
     *
     * Guru/wali kelas hanya boleh melihat kelas yang ia ampu atau ia walikan —
     * daftarnya diambil dari `teachingClassroomIds()` yang juga dipakai
     * `Student::scopeVisibleTo()`, sehingga isi dropdown selalu sejalan dengan
     * siswa yang memang boleh ia lihat.
     *
     * Peran tanpa akses lintas kelas (siswa, orang tua) mendapat daftar kosong;
     * halaman menyembunyikan dropdown-nya, karena memfilter satu-satunya kelas
     * yang terlihat tidak ada gunanya.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private function filterableClassrooms(?\App\Infrastructure\Persistence\Eloquent\Auth\User $user): array
    {
        if (! $user) {
            return [];
        }

        $activeYearId = AcademicYear::query()
            ->where('is_active', true)
            ->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->value('id');

        if (! $activeYearId) {
            return [];
        }

        $query = Classroom::query()
            ->where('academic_year_id', $activeYearId)
            ->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id));

        $roles = $user->getRoleNames();
        $hasAllAccess = $user->user_type === 'super_admin'
            || $roles->intersect(Student::ALL_ACCESS_ROLES)->isNotEmpty();

        if (! $hasAllAccess) {
            if ($roles->intersect(['guru', 'wali_kelas'])->isEmpty()) {
                return [];
            }

            $classroomIds = $user->teachingClassroomIds();

            if ($classroomIds === []) {
                return [];
            }

            $query->whereIn('id', $classroomIds);
        }

        return $query->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Classroom $c) => ['id' => $c->id, 'name' => $c->name])
            ->all();
    }

    /**
     * Display create student form.
     */
    public function createStudent(): Response
    {
        return Inertia::render('students/Create');
    }

    /**
     * Display student detail: identitas, data pendidikan, kredensial
     * presensi (QR/RFID), dan daftar orang tua/wali.
     */
    public function showStudent(Student $student): Response
    {
        $student->load(['user.profile', 'currentClass', 'parents.user']);

        return Inertia::render('students/Show', [
            // ->resolve() sengaja dipakai (bukan objek Resource langsung):
            // Inertia membungkus Responsable/JsonResource dalam {"data":{...}},
            // yang bikin form React gagal terisi (field selalu undefined) —
            // lihat catatan yang sama di editTeacher().
            'student' => (new StudentResource($student))->resolve(),
        ]);
    }

    /**
     * Display edit student form, prefilled server-side (Inertia props).
     */
    public function editStudent(Student $student): Response
    {
        $student->load(['user.profile']);

        return Inertia::render('students/Edit', [
            'student' => (new StudentResource($student))->resolve(),
        ]);
    }

    /**
     * Display the "wajib ganti password" page (initial/reset password).
     */
    public function changePassword(): Response
    {
        return Inertia::render('auth/ChangePasswordRequired');
    }

    /**
     * Display create teacher form.
     */
    public function createTeacher(): Response
    {
        return Inertia::render('teachers/Create');
    }

    /**
     * Display edit teacher form, prefilled server-side (Inertia props).
     */
    public function editTeacher(Teacher $teacher): Response
    {
        $teacher->load(['user.profile', 'media']);

        return Inertia::render('teachers/Edit', [
            // ->resolve() sengaja dipakai, bukan meneruskan objek Resource
            // langsung: Inertia memperlakukan Responsable (termasuk
            // JsonResource) via toResponse(), yang membungkusnya dalam
            // {"data": {...}} — prop 'teacher' jadi {data:{...}} dan form
            // React gagal terisi (field selalu undefined). ->resolve()
            // memberi array polos tanpa pembungkus.
            'teacher' => (new TeacherResource($teacher))->resolve(),
        ]);
    }

    /**
     * Display teacher detail: identitas, kepegawaian, dokumen, dan
     * ringkasan penugasan read-only (Fase G2, TEACHER-MODULE-PLAN.md §4).
     */
    public function showTeacher(Teacher $teacher, \App\Services\TeacherAssignmentService $assignments): Response
    {
        $teacher->load(['user.profile', 'media']);

        return Inertia::render('teachers/Show', [
            'teacher' => (new TeacherResource($teacher))->resolve(),
            'assignment' => $assignments->overview($teacher),
        ]);
    }

    /**
     * Display teachers list.
     */
    public function teachers(): Response
    {
        $filters = request()->only(['search', 'status', 'employment_status']);

        $teachers = Teacher::with(['user.profile'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('nip', 'ilike', "%{$search}%")
                        ->orWhere('nuptk', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('username', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['employment_status'] ?? null, fn ($q, $es) => $q->where('employment_status', $es))
            ->orderBy('nip')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('teachers/Index', [
            'teachers' => [
                'data' => TeacherResource::collection($teachers->items())->resolve(),
                'meta' => [
                    'current_page' => $teachers->currentPage(),
                    'from' => $teachers->firstItem() ?? 0,
                    'last_page' => $teachers->lastPage(),
                    'per_page' => $teachers->perPage(),
                    'to' => $teachers->lastItem() ?? 0,
                    'total' => $teachers->total(),
                ],
                'links' => [
                    'prev' => $teachers->previousPageUrl(),
                    'next' => $teachers->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Display staff list.
     */
    public function staff(): Response
    {
        $filters = request()->only(['search', 'status', 'employment_status', 'department_id']);

        $staff = \App\Infrastructure\Persistence\Eloquent\Staff\Staff::with(['user.profile', 'department', 'position'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('employee_id', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('username', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('user.profile', fn ($p) => $p
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['employment_status'] ?? null, fn ($q, $es) => $q->where('employment_status', $es))
            ->when($filters['department_id'] ?? null, fn ($q, $deptId) => $q->where('department_id', $deptId))
            ->orderBy('employee_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('staff/Index', [
            'staff' => [
                'data' => \App\Http\Resources\StaffResource::collection($staff->items())->resolve(),
                'meta' => [
                    'current_page' => $staff->currentPage(),
                    'from' => $staff->firstItem() ?? 0,
                    'last_page' => $staff->lastPage(),
                    'per_page' => $staff->perPage(),
                    'to' => $staff->lastItem() ?? 0,
                    'total' => $staff->total(),
                ],
                'links' => [
                    'prev' => $staff->previousPageUrl(),
                    'next' => $staff->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Display create staff form.
     */
    public function createStaff(): Response
    {
        return Inertia::render('staff/Create');
    }

    /**
     * Display edit staff form.
     */
    public function editStaff(\App\Infrastructure\Persistence\Eloquent\Staff\Staff $staff): Response
    {
        $staff->load(['user.profile', 'department', 'position']);

        return Inertia::render('staff/Edit', [
            'staff' => (new \App\Http\Resources\StaffResource($staff))->resolve(),
        ]);
    }

    /**
     * Display staff detail.
     */
    public function showStaff(\App\Infrastructure\Persistence\Eloquent\Staff\Staff $staff): Response
    {
        $staff->load(['user.profile', 'department', 'position']);

        return Inertia::render('staff/Show', [
            'staff' => (new \App\Http\Resources\StaffResource($staff))->resolve(),
        ]);
    }

    /**
     * Display login page.
     */
    public function login(): Response
    {
        return Inertia::render('auth/Login');
    }

    /**
     * Display register page.
     */
    public function register(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Display "Lupa Password" page. Aplikasi ini tidak punya alur reset
     * password via email (lihat AuthController::loginWithOtp) — halaman ini
     * memandu pengguna login pakai kode akses sekali-pakai yang dibuatkan
     * admin lewat menu Pengaturan > Keamanan Login.
     */
    public function forgotPassword(): Response
    {
        return Inertia::render('auth/ForgotPassword');
    }

    /**
     * Display academic years list.
     */
    public function academicYears(): Response
    {
        return Inertia::render('academic/Years');
    }

    /**
     * Display class rooms list.
     */
    public function classRooms(): Response
    {
        return Inertia::render('master/ClassRooms');
    }

    /**
     * Display attendance page.
     */
    public function attendance(): Response
    {
        return Inertia::render('attendance/Index');
    }

    /**
     * Daftar kelas aktif (tahun ajaran aktif) untuk dropdown di halaman
     * absensi siswa/QR code/laporan — tenant sudah dibatasi otomatis lewat
     * BelongsToTenant pada model Classroom.
     *
     * Bila `$scopeToTeacher` true: role admin-tier (Student::ALL_ACCESS_ROLES)
     * tetap lihat semua kelas; guru/wali_kelas dibatasi ke kelas yang diampu
     * (teachingClassroomIds(), R3); role lain (tidak berhak) dapat array
     * kosong — bukan error, halaman terkait memang bukan untuk mereka.
     */
    private function activeClassroomsForAttendance(bool $scopeToTeacher = false)
    {
        $query = \App\Infrastructure\Persistence\Eloquent\Academic\Classroom::whereHas(
            'academicYear',
            fn ($q) => $q->where('is_active', true)
        )
            ->where('is_active', true)
            ->with(['gradeLevel', 'major'])
            ->orderBy('name');

        if ($scopeToTeacher) {
            $user = request()->user();
            $roles = $user->getRoleNames();

            if ($roles->intersect(\App\Infrastructure\Persistence\Eloquent\Student\Student::ALL_ACCESS_ROLES)->isEmpty()) {
                if ($roles->intersect(['guru', 'wali_kelas'])->isNotEmpty()) {
                    $query->whereIn('id', $user->teachingClassroomIds());
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        return $query->get();
    }

    /**
     * Display daily student attendance page.
     */
    /**
     * Display self-service "Absensi Saya" page (guru & pegawai).
     */
    public function attendanceMe(): Response
    {
        return Inertia::render('attendance/me/Index');
    }

    public function attendanceStudents(): Response
    {
        $classrooms = $this->activeClassroomsForAttendance(scopeToTeacher: true);

        return Inertia::render('attendance/students/Index', [
            'classrooms' => $classrooms,
            'initialClassroom' => $classrooms->count() === 1 ? $classrooms->first()->id : null,
        ]);
    }

    /**
     * Display daily teacher attendance page.
     */
    public function attendanceTeachers(): Response
    {
        return Inertia::render('attendance/teachers/Index');
    }

    /**
     * Display leave permissions (izin/sakit) list.
     */
    public function attendancePermissions(): Response
    {
        return Inertia::render('attendance/permissions/Index');
    }

    /**
     * Display the create-leave-permission form.
     */
    public function attendancePermissionsCreate(): Response
    {
        $user = request()->user();
        // Guru/siswa (dan role tanpa akses penuh lainnya) mengajukan izin
        // untuk dirinya sendiri saja — frontend tidak menampilkan pemilih
        // siswa/guru untuk mereka, jadi daftar lengkap ini tidak perlu (dan
        // sebaiknya tidak) ikut dikirim. Lihat
        // LeavePermissionController::isFullAccess() untuk kanon yang sama.
        $isFullAccess = $user->getRoleNames()->intersect(Student::ALL_ACCESS_ROLES)->isNotEmpty();

        $students = $isFullAccess
            ? Student::visibleTo($user)->with('user.profile')->orderBy('nis')->get(['id', 'user_id', 'nis'])
            : [];

        $teachers = $isFullAccess
            ? Teacher::with('user.profile')->get()->map(fn ($teacher) => [
                'id' => $teacher->id,
                'full_name' => $teacher->user?->full_name,
            ])->values()
            : [];

        return Inertia::render('attendance/permissions/Create', [
            'students' => $students,
            'teachers' => $teachers,
        ]);
    }

    /**
     * Display holiday calendar management.
     */
    public function attendanceHolidays(): Response
    {
        return Inertia::render('attendance/holidays/Index');
    }

    /**
     * Display QR code management page.
     */
    public function attendanceQrCodes(): Response
    {
        // Guru sekarang boleh buka halaman ini (tab Siswa saja) — daftar
        // kelasnya WAJIB dibatasi ke kelas yang diampu sendiri, sama seperti
        // halaman Absensi Siswa. scopeToTeacher:true tidak memengaruhi
        // admin/TU/super_admin (mereka ada di Student::ALL_ACCESS_ROLES,
        // tetap dapat semua kelas).
        return Inertia::render('attendance/qr-codes/Index', [
            'classrooms' => $this->activeClassroomsForAttendance(scopeToTeacher: true),
        ]);
    }

    /**
     * Display attendance reports page.
     */
    public function attendanceReports(): Response
    {
        return Inertia::render('attendance/reports/Index', [
            'classrooms' => $this->activeClassroomsForAttendance(),
        ]);
    }

    /**
     * Display attendance & notification settings page.
     */
    public function attendanceSettings(): Response
    {
        return Inertia::render('attendance/settings/Index');
    }

    /**
     * Display the card ID template editor (Fase 5, drag-and-drop).
     */
    public function attendanceCardTemplates(): Response
    {
        return Inertia::render('attendance/card-templates/Index');
    }

    /**
     * Display the QR/RFID scanner kiosk page.
     */
    public function scanner(): Response
    {
        return Inertia::render('scanner/Index');
    }

    /**
     * Display schedules page.
     */
    public function schedules(): Response
    {
        return Inertia::render('academic/Schedules');
    }

    /**
     * Display users list.
     */
    public function users(): Response
    {
        return Inertia::render('settings/Users');
    }

    /**
     * Display database backups page (super admin only).
     */
    public function backups(): Response
    {
        return Inertia::render('settings/Backups');
    }

    /**
     * Display device monitoring page (monitor device scanner).
     */
    public function devices(): Response
    {
        return Inertia::render('settings/Devices');
    }

    /**
     * Display login security page (riwayat login, kode akses, cabut sesi).
     */
    public function loginSecurity(): Response
    {
        return Inertia::render('settings/LoginSecurity');
    }

    /**
     * Display settings page.
     */
    public function settings(): Response
    {
        return Inertia::render('settings/General');
    }

    /**
     * Display the signed-in user's own profile page (data diri, avatar, password).
     * Datanya diambil halaman lewat API (GET /api/v1/auth/profile).
     */
    public function profile(): Response
    {
        return Inertia::render('Profile/Edit');
    }

    /**
     * Display menu visibility settings page (Pengaturan Menu).
     * Data matriks diambil halaman via API (GET /api/v1/settings/menu).
     */
    public function menuSettings(): Response
    {
        return Inertia::render('settings/MenuSettings');
    }

    /**
     * Display class rooms page (Akademik) — sebelumnya dobel di menu
     * Pengaturan (`/settings/class-rooms`), sekarang satu-satunya lokasi
     * (lihat catatan redirect di routes/web.php).
     */
    public function academicClassRooms(): Response
    {
        return Inertia::render('academic/ClassRooms');
    }

    /**
     * Display majors page (Akademik) — sebelumnya dobel di menu Pengaturan
     * (`/settings/majors`), sekarang satu-satunya lokasi.
     */
    public function academicMajors(): Response
    {
        return Inertia::render('academic/Majors');
    }

    /**
     * Display grade levels (tingkat kelas) master data page — sebelumnya
     * hanya API tanpa antarmuka pengelolaan sama sekali.
     */
    public function academicGradeLevels(): Response
    {
        return Inertia::render('academic/GradeLevels');
    }

    /**
     * Display curricula (kurikulum) page (Akademik) — sebelumnya hanya
     * skema+seed tanpa antarmuka pengelolaan sama sekali.
     */
    public function academicCurricula(): Response
    {
        return Inertia::render('academic/Curricula');
    }

    /**
     * Display subjects (mata pelajaran) page (Akademik) — sebelumnya hanya
     * skema+seed tanpa antarmuka pengelolaan sama sekali.
     */
    public function academicSubjects(): Response
    {
        return Inertia::render('academic/Subjects');
    }

    // =========================================================================
    // Finance Module
    // =========================================================================

    /**
     * Display fee types (jenis biaya) master data page.
     */
    public function feeTypes(): Response
    {
        return Inertia::render('finance/FeeTypes');
    }

    /**
     * Display payment methods (metode pembayaran) master data page.
     */
    public function paymentMethods(): Response
    {
        return Inertia::render('finance/PaymentMethods');
    }

    /**
     * Display discounts (potongan) master data page.
     */
    public function discounts(): Response
    {
        return Inertia::render('finance/Discounts');
    }

    /**
     * Display fee structures (struktur biaya) master data page.
     */
    public function feeStructures(): Response
    {
        return Inertia::render('finance/FeeStructures');
    }

    /**
     * Display student fees (tagihan) page.
     */
    public function studentFees(): Response
    {
        return Inertia::render('finance/StudentFees');
    }

    /**
     * Display payments (pembayaran) page.
     */
    public function payments(): Response
    {
        return Inertia::render('finance/Payments');
    }

    // =========================================================================
    // Payroll Module
    // =========================================================================

    /**
     * Display salary grades (golongan gaji) master data page.
     */
    public function salaryGrades(): Response
    {
        return Inertia::render('payroll/SalaryGrades');
    }

    /**
     * Display salary components (komponen gaji) master data page.
     */
    public function salaryComponents(): Response
    {
        return Inertia::render('payroll/SalaryComponents');
    }

    /**
     * Display BPJS rates (tarif BPJS) master data page.
     */
    public function bpjsRates(): Response
    {
        return Inertia::render('payroll/BpjsRates');
    }

    /**
     * Display tax brackets (tarif pajak PPh 21) master data page.
     */
    public function taxBrackets(): Response
    {
        return Inertia::render('payroll/TaxBrackets');
    }

    /**
     * Display tax settings (pengaturan pajak / PTKP) master data page.
     */
    public function taxSettings(): Response
    {
        return Inertia::render('payroll/TaxSettings');
    }

    /**
     * Display employee salaries (gaji karyawan) management page.
     */
    public function employeeSalaries(): Response
    {
        return Inertia::render('payroll/EmployeeSalaries');
    }

    /**
     * Display payroll periods management page.
     */
    public function payrollPeriods(): Response
    {
        return Inertia::render('payroll/PayrollPeriods');
    }

    /**
     * Display payroll slips for a period.
     */
    public function payrollSlips(string $periodId): Response
    {
        return Inertia::render('payroll/PayrollSlips', [
            'periodId' => $periodId,
        ]);
    }

    /**
     * Display payroll reports page.
     */
    public function payrollReports(): Response
    {
        return Inertia::render('payroll/Reports');
    }

    /**
     * Display finance reports page.
     */
    public function financeReports(): Response
    {
        return Inertia::render('finance/Reports');
    }

    /**
     * Display expense report page (Laporan Pengeluaran).
     */
    public function expenseReport(): Response
    {
        return Inertia::render('reports/ExpenseReport');
    }
}
