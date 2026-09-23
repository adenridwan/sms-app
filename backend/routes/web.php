<?php

use App\Http\Controllers\Web\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::get('/', function () {
    return redirect('/login');
})->name('home');

// Auth Routes (Guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [PageController::class, 'login'])->name('login');
    Route::get('/register', [PageController::class, 'register'])->name('register');
    Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('forgot-password');
});

// Logout — sebelumnya hanya didefinisikan di routes/auth.php, sebuah file
// yang TIDAK PERNAH dimuat oleh bootstrap/app.php (tidak masuk withRouting()
// dan tidak ada file lain yang me-require-nya). Tombol Keluar di sidebar
// (MainLayout.tsx, href="/logout") sudah lama menuju rute yang tidak
// terdaftar, jatuh ke Route::fallback dan menampilkan halaman 404.
Route::middleware('auth')->post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

// Wajib ganti password: HARUS di luar grup 'password.current' di bawah
// (kalau tidak, terjadi redirect loop bagi pengguna yang justru dikirim ke sini).
Route::middleware(['auth'])->group(function () {
    Route::get('/change-password', [PageController::class, 'changePassword'])->name('change-password');
});

// Authenticated Routes
Route::middleware(['auth', 'password.current'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');

    // Students Module
    // {student} dibatasi ke format UUID: tanpa ini, segmen non-UUID (mis.
    // link sidebar /students/enrollment yang belum punya halaman sendiri)
    // ikut tertangkap wildcard ini dan menyebabkan 500 SQLSTATE[22P02] di
    // Postgres (bukan 404) karena query langsung mencoba cast string ke uuid.
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [PageController::class, 'students'])->name('index');
        // Halaman form ikut digerbangi izin, bukan hanya endpoint API-nya.
        // Tanpa ini guru masih bisa membuka form Tambah/Edit Siswa dan baru
        // ditolak 403 setelah menekan Simpan — terbaca seolah ia berwenang.
        Route::get('/create', [PageController::class, 'createStudent'])
            ->middleware('permission:students.create')
            ->name('create');
        Route::get('/enrollment', [PageController::class, 'studentEnrollment'])
            ->middleware('permission:students.enroll')
            ->name('enrollment');
        Route::get('/achievements', [PageController::class, 'studentAchievements'])
            ->name('achievements');
        Route::get('/{student}', [PageController::class, 'showStudent'])->whereUuid('student')->name('show');
        Route::get('/{student}/edit', [PageController::class, 'editStudent'])
            ->whereUuid('student')
            ->middleware('permission:students.update')
            ->name('edit');
    });

    // Teachers Module
    Route::prefix('teachers')->name('teachers.')->group(function () {
        Route::get('/', [PageController::class, 'teachers'])->name('index');
        Route::get('/create', [PageController::class, 'createTeacher'])->name('create');
        Route::get('/{teacher}', [PageController::class, 'showTeacher'])->whereUuid('teacher')->name('show');
        Route::get('/{teacher}/edit', [PageController::class, 'editTeacher'])->whereUuid('teacher')->name('edit');
    });

    // Staff Module
    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [PageController::class, 'staff'])->name('index');
        Route::get('/create', [PageController::class, 'createStaff'])->name('create');
        Route::get('/{staff}', [PageController::class, 'showStaff'])->whereUuid('staff')->name('show');
        Route::get('/{staff}/edit', [PageController::class, 'editStaff'])->whereUuid('staff')->name('edit');
    });

    // Academic Module
    Route::prefix('academic')->name('academic.')->group(function () {
        Route::get('/years', [PageController::class, 'academicYears'])->name('years');
        Route::get('/curricula', [PageController::class, 'academicCurricula'])->name('curricula');
        Route::get('/subjects', [PageController::class, 'academicSubjects'])->name('subjects');
        Route::get('/schedules', [PageController::class, 'schedules'])->name('schedules');
        // Kelas & Jurusan dipindah kesini dari menu Pengaturan supaya tidak
        // dobel (lihat redirect /settings/class-rooms dan /settings/majors
        // di bawah agar tautan/bookmark lama tidak mati).
        Route::get('/classrooms', [PageController::class, 'academicClassRooms'])->name('classrooms');
        Route::get('/majors', [PageController::class, 'academicMajors'])->name('majors');
        Route::get('/grade-levels', [PageController::class, 'academicGradeLevels'])->name('grade-levels');
    });

    // Master Data Module
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('/class-rooms', [PageController::class, 'classRooms'])->name('class-rooms');
        // Mata Pelajaran sekarang di menu Akademik (`/academic/subjects`);
        // redirect dipertahankan supaya tautan/bookmark lama tidak 404.
        Route::redirect('/subjects', '/academic/subjects')->name('subjects');
    });

    // Finance Module
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/', [PageController::class, 'finance'])->name('index');
        Route::get('/fee-types', [PageController::class, 'feeTypes'])->name('fee-types');
        Route::get('/fee-structures', [PageController::class, 'feeStructures'])->name('fee-structures');
        Route::get('/payment-methods', [PageController::class, 'paymentMethods'])->name('payment-methods');
        Route::get('/discounts', [PageController::class, 'discounts'])->name('discounts');
        Route::get('/fees', [PageController::class, 'studentFees'])->name('fees');
        Route::get('/payments', [PageController::class, 'payments'])->name('payments');
        Route::get('/reports', [PageController::class, 'financeReports'])->name('reports');
    });

    // Payroll Module
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/salary-grades', [PageController::class, 'salaryGrades'])->name('salary-grades');
        Route::get('/salary-components', [PageController::class, 'salaryComponents'])->name('salary-components');
        Route::get('/bpjs-rates', [PageController::class, 'bpjsRates'])->name('bpjs-rates');
        Route::get('/tax-brackets', [PageController::class, 'taxBrackets'])->name('tax-brackets');
        Route::get('/tax-settings', [PageController::class, 'taxSettings'])->name('tax-settings');
        Route::get('/employee-salaries', [PageController::class, 'employeeSalaries'])->name('employee-salaries');
        Route::get('/periods', [PageController::class, 'payrollPeriods'])->name('periods');
        Route::get('/slips/{periodId}', [PageController::class, 'payrollSlips'])->name('slips');
        Route::get('/reports', [PageController::class, 'payrollReports'])->name('reports');
    });

    // Attendance Module
    Route::get('/attendance', [PageController::class, 'attendance'])->name('attendance');
    Route::get('/attendance/me', [PageController::class, 'attendanceMe'])->name('attendance.me');
    Route::get('/attendance/students', [PageController::class, 'attendanceStudents'])->name('attendance.students');
    Route::get('/attendance/teachers', [PageController::class, 'attendanceTeachers'])->name('attendance.teachers');
    Route::get('/attendance/permissions/create', [PageController::class, 'attendancePermissionsCreate'])->name('attendance.permissions.create');
    Route::get('/attendance/permissions', [PageController::class, 'attendancePermissions'])->name('attendance.permissions');
    // Perkakas back-office absensi (kalender libur, desain kartu,
    // pengaturan) — hanya admin/tata_usaha/super_admin, sama seperti
    // settings.attendance yang sudah menjaga endpoint PUT-nya.
    Route::middleware('permission:settings.attendance')->group(function () {
        Route::get('/attendance/holidays', [PageController::class, 'attendanceHolidays'])->name('attendance.holidays');
        Route::get('/attendance/settings', [PageController::class, 'attendanceSettings'])->name('attendance.settings');
        Route::get('/attendance/card-templates', [PageController::class, 'attendanceCardTemplates'])->name('attendance.card-templates');
    });
    // QR Code: tab "Siswa" (lihat/cetak QR kelas sendiri) dibuka juga untuk
    // guru (attendance.scan-students) — tab "Guru" dan aksi admin (export,
    // regenerate) tetap dibatasi settings.attendance di dalam halaman &
    // route API-nya sendiri (lihat api_v1.php).
    Route::get('/attendance/qr-codes', [PageController::class, 'attendanceQrCodes'])
        ->middleware('permission:attendance.scan-students|settings.attendance')
        ->name('attendance.qr-codes');
    Route::get('/attendance/reports', [PageController::class, 'attendanceReports'])->name('attendance.reports');
    // Halaman scanner dibuka untuk siapa saja yang boleh memindai SESUATU
    // (siswa ATAU staf) — pembatasan per jenis yang discan (guru cuma
    // scan-students) ditegakkan di dalam AttendanceScanService::processScan().
    Route::get('/scanner', [PageController::class, 'scanner'])
        ->middleware('permission:attendance.scan-students|attendance.scan-staff')
        ->name('scanner');

    // Settings Module
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [PageController::class, 'settings'])->name('index');
        Route::get('/menu', [PageController::class, 'menuSettings'])
            ->middleware('permission:settings.manage')
            ->name('menu');
        Route::get('/users', [PageController::class, 'users'])
            ->middleware('role:super_admin')
            ->name('users');
        Route::get('/login-security', [PageController::class, 'loginSecurity'])
            ->middleware('permission:settings.manage')
            ->name('login-security');
        Route::get('/backups', [PageController::class, 'backups'])
            ->middleware('role:super_admin')
            ->name('backups');
        Route::get('/devices', [PageController::class, 'devices'])
            ->middleware('role:super_admin|admin')
            ->name('devices');
        // Kelas & Jurusan dipindah ke menu Akademik (biar tidak dobel);
        // route lama dipertahankan sebagai redirect saja supaya tautan atau
        // bookmark yang sudah ada tidak berujung 404.
        Route::redirect('/class-rooms', '/academic/classrooms')->name('class-rooms');
        Route::redirect('/majors', '/academic/majors')->name('majors');
    });

    // Reports Module (Laporan Terpisah)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/expense', [PageController::class, 'expenseReport'])->name('expense');
        Route::get('/report-cards', [PageController::class, 'reportCards'])->name('report-cards');
    });

    // Notifications Module
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/announcements', [PageController::class, 'announcements'])->name('announcements');
    });

    // Grades & Exams Module
    Route::prefix('grades')->name('grades.')->group(function () {
        Route::get('/exams', [PageController::class, 'gradeExams'])->name('exams');
        Route::get('/input', [PageController::class, 'gradeInput'])->name('input');
        Route::get('/recap', [PageController::class, 'gradeRecap'])->name('recap');
    });

    // Library Module
    Route::prefix('library')->name('library.')->group(function () {
        Route::get('/books', [PageController::class, 'libraryBooks'])->name('books');
        Route::get('/loans', [PageController::class, 'libraryLoans'])->name('loans');
        Route::get('/members', [PageController::class, 'libraryMembers'])->name('members');
    });

    // Profile Routes
    Route::get('/profile', [PageController::class, 'profile'])->name('profile.edit');

    // Help Page (super admin only)
    Route::get('/help', [PageController::class, 'help'])
        ->middleware('role:super_admin')
        ->name('help');
});

// Fallback: elegant 404 page for unknown routes
Route::fallback(function (Request $request) {
    return Inertia::render('Error', ['status' => 404])
        ->toResponse($request)
        ->setStatusCode(404);
});
