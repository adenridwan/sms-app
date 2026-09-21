<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

// ===========================================
// Public Routes (No Auth Required)
// ===========================================
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('login');

    Route::post('/login-otp', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'loginWithOtp'])
        ->middleware('throttle:auth')
        ->name('login-otp');

    Route::post('/register', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'register'])
        ->middleware('throttle:auth')
        ->name('register');

    // Aktivasi akun hasil /register memakai kode dari menu Keamanan Login.
    Route::post('/activate', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'activate'])
        ->middleware('throttle:auth')
        ->name('activate');

    // Redeem provision token (QR code login) — public karena user belum
    // punya token auth. Token provisioning sendiri di-generate admin lewat
    // endpoint protected di bawah.
    Route::post('/redeem-provision', [\App\Http\Controllers\Api\V1\Auth\ProvisionController::class, 'redeem'])
        ->middleware('throttle:auth')
        ->name('redeem-provision');

    // Tidak ada reset password via email — "lupa password" dipakaikan
    // login-otp di atas (kode akses sekali-pakai dari admin). Dua route
    // 'forgot-password'/'reset-password' yang dulu di sini menunjuk ke
    // method PasswordController yang tidak pernah diimplementasikan
    // (selalu 500 kalau kena hit) — dihapus, bukan didiamkan.
});

// Cek jangkauan server untuk aplikasi mobile: tanpa auth, tanpa sentuh
// database, dan balasannya sekecil mungkin. Aplikasi memanggilnya berkala
// selagi status backend belum `online`, supaya antrean absensi offline ikut
// tersinkron begitu server hidup lagi — tanpa itu status hanya berubah kalau
// pengguna kebetulan melakukan sesuatu yang memicu request.
Route::get('/ping', fn () => response()->json(['ok' => true]))->name('ping');

// ===========================================
// Protected Routes (Auth Required)
// ===========================================
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me'])->name('me');
        Route::get('/profile', [\App\Http\Controllers\Api\V1\Auth\ProfileController::class, 'show'])->name('profile.show');
        // Frontend mengirim multipart (avatar) lewat POST + `_method=PUT` —
        // PHP tidak mem-parse body multipart pada request PUT asli.
        Route::put('/profile', [\App\Http\Controllers\Api\V1\Auth\ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile/avatar', [\App\Http\Controllers\Api\V1\Auth\ProfileController::class, 'deleteAvatar'])->name('profile.avatar.destroy');
        // Nama method controller adalah `change` — route dulu salah rujuk ke `update` (selalu 500)
        Route::put('/password', [\App\Http\Controllers\Api\V1\Auth\PasswordController::class, 'change'])->name('password.update');
    });

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\V1\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/dashboard/class/{classroom}', [\App\Http\Controllers\Api\V1\DashboardController::class, 'classStats'])
        ->whereUuid('classroom')
        ->name('dashboard.class');

    // ===========================================
    // Academic Module
    // ===========================================
    Route::prefix('academic')->name('academic.')->group(function () {
        // Academic Years
        Route::apiResource('years', \App\Http\Controllers\Api\V1\Academic\AcademicYearController::class);
        Route::post('years/{year}/activate', [\App\Http\Controllers\Api\V1\Academic\AcademicYearController::class, 'activate'])->name('years.activate');

        // Semesters
        Route::apiResource('semesters', \App\Http\Controllers\Api\V1\Academic\SemesterController::class);
        Route::post('semesters/{semester}/activate', [\App\Http\Controllers\Api\V1\Academic\SemesterController::class, 'activate'])->name('semesters.activate');

        // Curricula
        Route::apiResource('curricula', \App\Http\Controllers\Api\V1\Academic\CurriculumController::class);

        // Grade Levels
        Route::apiResource('grade-levels', \App\Http\Controllers\Api\V1\Academic\GradeLevelController::class);

        // Majors (static routes must be registered before apiResource)
        Route::get('majors/template', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'template'])->name('majors.template');
        Route::get('majors/export', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'export'])->name('majors.export');
        Route::post('majors/import', [\App\Http\Controllers\Api\V1\Academic\MajorController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('majors.import');
        Route::apiResource('majors', \App\Http\Controllers\Api\V1\Academic\MajorController::class);

        // Classrooms (static routes must be registered before apiResource)
        Route::get('classrooms/template', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'template'])->name('classrooms.template');
        Route::get('classrooms/export', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'export'])->name('classrooms.export');
        Route::post('classrooms/import', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('classrooms.import');
        Route::apiResource('classrooms', \App\Http\Controllers\Api\V1\Academic\ClassroomController::class);
        Route::get('classrooms/{classroom}/students', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'students'])->name('classrooms.students');
        Route::get('classrooms/{classroom}/schedule', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'schedule'])->name('classrooms.schedule');
        // Guru pengampu (Fase G3, TEACHER-MODULE-PLAN.md) — penempatan manual per tahun ajaran
        Route::get('classrooms/{classroom}/teachers', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'teachers'])->name('classrooms.teachers');
        Route::put('classrooms/{classroom}/teachers', [\App\Http\Controllers\Api\V1\Academic\ClassroomController::class, 'syncTeachers'])->name('classrooms.teachers.sync');

        // Subjects
        Route::apiResource('subjects', \App\Http\Controllers\Api\V1\Academic\SubjectController::class);

        // Schedules (static routes must be registered before apiResource)
        Route::get('schedules/export-pdf', [\App\Http\Controllers\Api\V1\Academic\ScheduleController::class, 'exportPdf'])->name('schedules.export-pdf');
        Route::post('schedules/copy-from-classroom', [\App\Http\Controllers\Api\V1\Academic\ScheduleController::class, 'copyFromClassroom'])->name('schedules.copy-from-classroom');
        Route::post('schedules/copy-from-day', [\App\Http\Controllers\Api\V1\Academic\ScheduleController::class, 'copyFromDay'])->name('schedules.copy-from-day');
        Route::apiResource('schedules', \App\Http\Controllers\Api\V1\Academic\ScheduleController::class);

        // Time Slots
        Route::apiResource('time-slots', \App\Http\Controllers\Api\V1\Academic\TimeSlotController::class);
    });

    // ===========================================
    // Student Module
    // ===========================================
    Route::prefix('students')->name('students.')->group(function () {
        // Rute statis wajib didaftarkan sebelum apiResource, kalau tidak
        // "export"/"template"/"import" akan tertangkap sebagai {student}.
        Route::get('export', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'export'])->name('export');
        Route::get('template', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'template'])->name('template');
        Route::post('import', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('import');
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Student\StudentController::class)->parameter('', 'student');
        Route::get('{student}/guardians', [\App\Http\Controllers\Api\V1\Student\GuardianController::class, 'forStudent'])->name('guardians');
        Route::get('{student}/enrollments', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'studentEnrollments'])->name('enrollments');
        Route::get('{student}/grades', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'grades'])->name('grades');
        Route::get('{student}/attendance', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'attendance'])->name('attendance');
        Route::get('{student}/fees', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'fees'])->name('fees');
        Route::get('{student}/achievements', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'forStudent'])->name('achievements');
        Route::post('{student}/enroll', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'store'])->name('enroll');
        Route::post('{student}/photo', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'uploadPhoto'])->name('photo.upload');
        Route::delete('{student}/photo', [\App\Http\Controllers\Api\V1\Student\StudentController::class, 'deletePhoto'])->name('photo.delete');

        // Guardians
        Route::apiResource('guardians', \App\Http\Controllers\Api\V1\Student\GuardianController::class);

        // Enrollments
        Route::prefix('enrollments')->name('enrollments.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'index'])->name('index');
            Route::get('statistics', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'statistics'])->name('statistics');
            Route::post('bulk', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'bulkEnroll'])->name('bulk');
            Route::put('{enrollment}', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'update'])
                ->whereUuid('enrollment')
                ->name('update');
            Route::delete('{enrollment}', [\App\Http\Controllers\Api\V1\Student\EnrollmentController::class, 'destroy'])
                ->whereUuid('enrollment')
                ->name('destroy');
        });

        // Achievements
        Route::prefix('achievements')->name('achievements.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'index'])->name('index');
            Route::get('statistics', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'statistics'])->name('statistics');
            Route::post('/', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'store'])->name('store');
            Route::get('{achievement}', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'show'])
                ->whereUuid('achievement')
                ->name('show');
            Route::put('{achievement}', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'update'])
                ->whereUuid('achievement')
                ->name('update');
            Route::delete('{achievement}', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'destroy'])
                ->whereUuid('achievement')
                ->name('destroy');
            Route::delete('{achievement}/certificate', [\App\Http\Controllers\Api\V1\Student\AchievementController::class, 'deleteCertificate'])
                ->whereUuid('achievement')
                ->name('certificate.destroy');
        });
    });

    // ===========================================
    // Teacher Module
    // ===========================================
    Route::prefix('teachers')->name('teachers.')->group(function () {
        // Rute statis wajib didaftarkan sebelum apiResource, kalau tidak
        // "export"/"template"/"import" akan tertangkap sebagai {teacher}.
        Route::get('export', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'export'])->name('export');
        Route::get('template', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'template'])->name('template');
        Route::post('import', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'import'])
            ->middleware('throttle:uploads')
            ->name('import');
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Teacher\TeacherController::class)->parameter('', 'teacher');
        // Penempatan kelas (menu Kelas) & kompetensi mapel (menu Mata Pelajaran)
        // sengaja tidak dikelola dari sini — lihat TEACHER-MODULE-PLAN.md §2.
        // Ringkasan penugasan (read-only) untuk halaman Detail Guru — Fase G2.
        Route::get('{teacher}/assignment', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'assignment'])->name('assignment');
        Route::post('{teacher}/photo', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'uploadPhoto'])->name('photo.upload');
        Route::delete('{teacher}/photo', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'deletePhoto'])->name('photo.delete');
        Route::post('{teacher}/documents', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'uploadDocument'])->name('documents.upload');
        Route::delete('{teacher}/documents/{media}', [\App\Http\Controllers\Api\V1\Teacher\TeacherController::class, 'deleteDocument'])->name('documents.delete');
    });

    // ===========================================
    // Staff Module
    // ===========================================
    Route::prefix('staff')->name('staff.')->group(function () {
        // Static routes first
        Route::get('options', [\App\Http\Controllers\Api\V1\Staff\StaffController::class, 'options'])->name('options');
        Route::get('departments', [\App\Http\Controllers\Api\V1\Staff\StaffController::class, 'departments'])->name('departments');
        Route::get('positions', [\App\Http\Controllers\Api\V1\Staff\StaffController::class, 'positions'])->name('positions');

        Route::apiResource('/', \App\Http\Controllers\Api\V1\Staff\StaffController::class)->parameter('', 'staff');

        Route::post('{staff}/photo', [\App\Http\Controllers\Api\V1\Staff\StaffController::class, 'uploadPhoto'])->name('photo.upload');
        Route::delete('{staff}/photo', [\App\Http\Controllers\Api\V1\Staff\StaffController::class, 'deletePhoto'])->name('photo.delete');
    });

    // ===========================================
    // Attendance Module
    // ===========================================
    Route::prefix('attendance')->name('attendance.')->group(function () {
        // Student Attendance
        Route::get('students', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'index'])->name('students.index');
        Route::get('students/daily', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'daily'])->name('students.daily');
        Route::post('students/bulk', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'storeBulk'])->name('students.bulk');
        Route::post('students/notify-daily', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'notifyDaily'])->name('students.notify-daily');
        Route::put('students/{attendance}', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'update'])->name('students.update');
        Route::get('students/summary', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'summary'])->name('students.summary');
        Route::get('students/consecutive-absences', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'consecutiveAbsences'])->name('students.consecutive-absences');
        Route::get('students/top-late', [\App\Http\Controllers\Api\V1\Attendance\StudentAttendanceController::class, 'topLate'])->name('students.top-late');

        // Absensi Saya (self-service, guru & pegawai) — selalu discope ke
        // user login sendiri, harus didaftar SEBELUM 'teachers/{...}' di
        // bawah supaya 'me' tidak ketangkap sebagai parameter route.
        Route::get('me/today', [\App\Http\Controllers\Api\V1\Attendance\MyAttendanceController::class, 'today'])->name('me.today');
        Route::get('me/history', [\App\Http\Controllers\Api\V1\Attendance\MyAttendanceController::class, 'history'])->name('me.history');

        // Teacher/Employee Attendance
        Route::get('teachers', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'index'])->name('teachers.index');
        Route::get('teachers/daily', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'daily'])->name('teachers.daily');
        Route::put('teachers/{attendance}', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'update'])->name('teachers.update');
        Route::get('teachers/summary', [\App\Http\Controllers\Api\V1\Attendance\TeacherAttendanceController::class, 'summary'])->name('teachers.summary');

        // Leave Permissions
        Route::apiResource('permissions', \App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class);
        Route::post('permissions/{permission}/approve', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'approve'])->name('permissions.approve');
        Route::post('permissions/{permission}/reject', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'reject'])->name('permissions.reject');
        Route::get('permissions-pending-count', [\App\Http\Controllers\Api\V1\Attendance\LeavePermissionController::class, 'pendingCount'])->name('permissions.pending-count');

        // Holidays — halaman "Hari Libur" hanya admin/TU/super_admin.
        Route::middleware('permission:settings.attendance')->group(function () {
            Route::apiResource('holidays', \App\Http\Controllers\Api\V1\Attendance\HolidayController::class);
            Route::post('holidays/generate-weekends', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'generateWeekends'])->name('holidays.generate-weekends');
            Route::post('holidays/bulk-delete', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'bulkDelete'])->name('holidays.bulk-delete');
            Route::post('holidays/check', [\App\Http\Controllers\Api\V1\Attendance\HolidayController::class, 'check'])->name('holidays.check');
        });

        // QR Codes — lihat QR/RFID milik sendiri (dipakai halaman self-service
        // Absensi Saya, semua role) TETAP terbuka; regenerate/bulk/download/
        // export (halaman admin "QR Code") dibatasi admin/TU/super_admin —
        // KECUALI qr/students/bulk, yang juga dibuka untuk guru
        // (attendance.scan-students, tab "Siswa"), dengan classroom_id-nya
        // sendiri dibatasi ke kelas yang diampu di dalam controller.
        //
        // 'bulk' WAJIB didaftarkan sebelum '{student}'/'{teacher}' — kalau
        // tidak, GET qr/teachers/bulk ketangkap route wildcard {teacher} lebih
        // dulu ("bulk" dianggap id, lalu 500 saat dicocokkan sebagai UUID).
        // Ini bug lama yang baru ketahuan lewat test permission ini.
        Route::get('qr/students/bulk', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'bulkStudents'])
            ->middleware('permission:attendance.scan-students|settings.attendance')
            ->name('qr.students.bulk');

        Route::middleware('permission:settings.attendance')->group(function () {
            Route::get('qr/teachers/bulk', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'bulkTeachers'])->name('qr.teachers.bulk');
            Route::get('qr/staff/bulk', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'bulkStaff'])->name('qr.staff.bulk');
        });

        Route::get('qr/students/{student}', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'student'])->name('qr.student');
        Route::get('qr/teachers/{teacher}', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'teacher'])->name('qr.teacher');

        Route::middleware('permission:settings.attendance')->group(function () {
            Route::get('qr/staff/{staff}', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'staff'])->name('qr.staff');
        });

        Route::middleware('permission:settings.attendance')->group(function () {
            Route::post('qr/students/{student}/regenerate', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'regenerateStudent'])->name('qr.student.regenerate');
            Route::get('qr/students/{student}/download', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'downloadStudent'])->name('qr.student.download');
            Route::post('qr/teachers/{teacher}/regenerate', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'regenerateTeacher'])->name('qr.teacher.regenerate');
            Route::get('qr/teachers/{teacher}/download', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'downloadTeacher'])->name('qr.teacher.download');
            Route::post('qr/staff/{staff}/regenerate', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'regenerateStaff'])->name('qr.staff.regenerate');
            Route::get('qr/staff/{staff}/download', [\App\Http\Controllers\Api\V1\Attendance\QrCodeController::class, 'downloadStaff'])->name('qr.staff.download');

            // Export QR/RFID untuk pembuatan kartu (Excel / ZIP gambar / PDF)
            Route::get('qr/export', [\App\Http\Controllers\Api\V1\Attendance\QrExportController::class, 'export'])->name('qr.export');
            Route::post('qr/generate-rfid', [\App\Http\Controllers\Api\V1\Attendance\QrExportController::class, 'generateRfid'])->name('qr.generate-rfid');
        });

        // RFID
        Route::put('rfid/students/{student}', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'updateStudent'])->name('rfid.students.update');
        Route::put('rfid/teachers/{teacher}', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'updateTeacher'])->name('rfid.teachers.update');
        // Kode RFID terbitan sistem (kartu writable) — lihat RfidCodeGenerator
        Route::post('rfid/teachers/{teacher}/generate', [\App\Http\Controllers\Api\V1\Attendance\RfidController::class, 'generateTeacher'])->name('rfid.teachers.generate');

        // Card Templates (Fase 5 — editor kartu ID drag-and-drop) — halaman
        // "Template Kartu" hanya admin/TU/super_admin.
        Route::middleware('permission:settings.attendance')->group(function () {
            Route::get('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'show'])->name('card-templates.show');
            Route::put('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'update'])->name('card-templates.update');
            Route::delete('card-templates/{type}', [\App\Http\Controllers\Api\V1\Attendance\CardTemplateController::class, 'reset'])->name('card-templates.reset');
        });

        // Reports
        Route::get('reports/monthly', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/pdf', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'downloadPdf'])->name('reports.pdf');
        Route::get('reports/excel', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'downloadExcel'])->name('reports.excel');
        Route::get('reports/weekly-trend', [\App\Http\Controllers\Api\V1\Attendance\AttendanceReportController::class, 'weeklyTrend'])->name('reports.weekly-trend');

        // Settings — hanya admin/TU/super admin (permission settings.attendance).
        // Sebelumnya hanya di-guard auth:sanctum sehingga guru/siswa bisa ubah
        // jam absen & kredensial WA/Telegram lewat URL langsung (GAP-1).
        Route::middleware('permission:settings.attendance')->group(function () {
            Route::get('settings', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'show'])->name('settings.show');
            Route::put('settings', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'update'])->name('settings.update');
            Route::post('settings/test-whatsapp', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testWhatsApp'])->name('settings.test-whatsapp');
            Route::post('settings/test-telegram', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testTelegram'])->name('settings.test-telegram');
            Route::post('settings/test-email', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'testEmail'])->name('settings.test-email');
            Route::get('settings/telegram-bot-info', [\App\Http\Controllers\Api\V1\Attendance\AttendanceSettingController::class, 'telegramBotInfo'])->name('settings.telegram-bot-info');
        });
    });

    // ===========================================
    // Scanner Module (for QR/RFID scanning)
    // Mengoperasikan mesin scan untuk memproses absen ORANG LAIN (bukan
    // attendance.check-in punya diri sendiri). Route ini hanya menyaring
    // "boleh scan sesuatu" (siswa ATAU staf); jenis yang boleh discan
    // (attendance.scan-students vs attendance.scan-staff) dicek per-kode di
    // AttendanceScanService::processScan() — guru cuma punya scan-students,
    // jadi ditolak kalau kode yang discan ternyata milik guru/pegawai.
    // ===========================================
    Route::prefix('scan')->name('scan.')->middleware('permission:attendance.scan-students|attendance.scan-staff')->group(function () {
        Route::get('bootstrap', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'bootstrap'])->name('bootstrap');
        Route::post('/', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'scan'])->name('process');
        Route::post('sync-offline', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'syncOffline'])->name('sync-offline');
        Route::post('lookup', [\App\Http\Controllers\Api\V1\Attendance\ScannerController::class, 'lookup'])->name('lookup');
    });

    // ===========================================
    // Device Monitor (heartbeat dari mobile app)
    // Semua user yang bisa scan (operator) bisa mengirim heartbeat.
    // ===========================================
    Route::prefix('devices')->name('devices.')->group(function () {
        Route::post('heartbeat', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'heartbeat'])->name('heartbeat');
    });

    // ===========================================
    // Exam & Grade Module
    // ===========================================
    Route::prefix('exams')->name('exams.')->group(function () {
        Route::apiResource('types', \App\Http\Controllers\Api\V1\Exam\ExamTypeController::class);
        Route::get('statistics', [\App\Http\Controllers\Api\V1\Exam\ExamController::class, 'statistics'])->name('statistics');
        Route::apiResource('/', \App\Http\Controllers\Api\V1\Exam\ExamController::class)->parameter('', 'exam');
        Route::get('{exam}/scores', [\App\Http\Controllers\Api\V1\Exam\ExamController::class, 'scores'])->name('scores');
        Route::post('{exam}/scores', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'store'])->name('scores.store');
        Route::post('{exam}/scores/bulk', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'storeBulk'])->name('scores.bulk');
        Route::put('scores/{score}', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'update'])->name('scores.update');
        Route::delete('scores/{score}', [\App\Http\Controllers\Api\V1\Exam\ScoreController::class, 'destroy'])->name('scores.destroy');
    });

    Route::prefix('grades')->name('grades.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'index'])->name('index');
        Route::get('student/{student}', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'byStudent'])->name('by-student');
        Route::get('classroom/{classroom}', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'byClassroom'])->name('by-classroom');
        Route::post('finalize', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'finalize'])->name('finalize');
        Route::post('approve', [\App\Http\Controllers\Api\V1\Exam\GradeController::class, 'approve'])->name('approve');
    });

    // ===========================================
    // Finance Module
    // ===========================================
    Route::prefix('finance')->name('finance.')->group(function () {
        // Fee Types
        Route::apiResource('fee-types', \App\Http\Controllers\Api\V1\Finance\FeeTypeController::class);

        // Fee Structures (static routes first)
        Route::post('fee-structures/bulk', [\App\Http\Controllers\Api\V1\Finance\FeeStructureController::class, 'bulkStore'])->name('fee-structures.bulk');
        Route::apiResource('fee-structures', \App\Http\Controllers\Api\V1\Finance\FeeStructureController::class);

        // Student Fees (static routes first)
        Route::get('fees/statuses', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'statuses'])->name('fees.statuses');
        Route::get('fees/summary', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'summary'])->name('fees.summary');
        Route::post('fees/generate', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'generate'])->name('fees.generate');
        Route::get('fees/student/{student}', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'studentHistory'])->name('fees.student-history');
        Route::post('fees/{studentFee}/waive', [\App\Http\Controllers\Api\V1\Finance\StudentFeeController::class, 'waive'])->name('fees.waive');
        Route::apiResource('fees', \App\Http\Controllers\Api\V1\Finance\StudentFeeController::class)->parameter('fees', 'studentFee');

        // Payments (static routes first)
        Route::get('payments/statuses', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'statuses'])->name('payments.statuses');
        Route::get('payments/summary', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'summary'])->name('payments.summary');
        Route::post('payments/{payment}/complete', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'complete'])->name('payments.complete');
        Route::post('payments/{payment}/upload-proof', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'uploadProof'])->name('payments.upload-proof');
        Route::post('payments/{payment}/verify', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/cancel', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'cancel'])->name('payments.cancel');
        Route::get('payments/{payment}/receipt', [\App\Http\Controllers\Api\V1\Finance\PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\Finance\PaymentController::class);

        // Payment Methods (static routes first)
        Route::get('payment-methods/types', [\App\Http\Controllers\Api\V1\Finance\PaymentMethodController::class, 'types'])->name('payment-methods.types');
        Route::apiResource('payment-methods', \App\Http\Controllers\Api\V1\Finance\PaymentMethodController::class);

        // Discounts (static routes first)
        Route::get('discounts/types', [\App\Http\Controllers\Api\V1\Finance\DiscountController::class, 'types'])->name('discounts.types');
        Route::apiResource('discounts', \App\Http\Controllers\Api\V1\Finance\DiscountController::class);

        // Reports
        Route::get('reports/dashboard', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/outstanding', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'outstanding'])->name('reports.outstanding');
        Route::get('reports/by-classroom', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'byClassroom'])->name('reports.by-classroom');
        Route::get('reports/monthly', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/student/{student}/history', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'studentHistory'])->name('reports.student-history');
        Route::get('reports/export/outstanding', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'exportOutstanding'])->name('reports.export.outstanding');
        Route::get('reports/export/by-classroom', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'exportByClassroom'])->name('reports.export.by-classroom');
        Route::get('reports/export/monthly', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'exportMonthly'])->name('reports.export.monthly');

        // Laporan Pengeluaran (integrasi dengan Payroll)
        Route::get('reports/monthly-expense', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'monthlyExpense'])->name('reports.monthly-expense');
        Route::get('reports/export/monthly-expense', [\App\Http\Controllers\Api\V1\Finance\ReportController::class, 'exportMonthlyExpense'])->name('reports.export.monthly-expense');
    });

    // ===========================================
    // Payroll Module
    // ===========================================
    Route::prefix('payroll')->name('payroll.')->group(function () {
        // Salary Grades
        Route::apiResource('salary-grades', \App\Http\Controllers\Api\V1\Payroll\SalaryGradeController::class);

        // Salary Components (static routes first)
        Route::get('salary-components/types', [\App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class, 'types'])->name('salary-components.types');
        Route::get('salary-components/calculation-types', [\App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class, 'calculationTypes'])->name('salary-components.calculation-types');
        Route::get('salary-components/percentage-references', [\App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class, 'percentageReferences'])->name('salary-components.percentage-references');
        Route::get('salary-components/available-for-reference', [\App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class, 'availableForReference'])->name('salary-components.available-for-reference');
        Route::post('salary-components/validate-formula', [\App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class, 'validateFormula'])->name('salary-components.validate-formula');
        Route::apiResource('salary-components', \App\Http\Controllers\Api\V1\Payroll\SalaryComponentController::class);

        // BPJS Rates (static routes first)
        Route::get('bpjs-rates/types', [\App\Http\Controllers\Api\V1\Payroll\BpjsRateController::class, 'types'])->name('bpjs-rates.types');
        Route::get('bpjs-rates/current', [\App\Http\Controllers\Api\V1\Payroll\BpjsRateController::class, 'currentRates'])->name('bpjs-rates.current');
        Route::apiResource('bpjs-rates', \App\Http\Controllers\Api\V1\Payroll\BpjsRateController::class);

        // Tax Brackets (static routes first)
        Route::get('tax-brackets/year/{year}', [\App\Http\Controllers\Api\V1\Payroll\TaxBracketController::class, 'forYear'])->name('tax-brackets.year');
        Route::post('tax-brackets/calculate', [\App\Http\Controllers\Api\V1\Payroll\TaxBracketController::class, 'calculate'])->name('tax-brackets.calculate');
        Route::apiResource('tax-brackets', \App\Http\Controllers\Api\V1\Payroll\TaxBracketController::class);

        // Tax Settings (static routes first)
        Route::get('tax-settings/categories', [\App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class, 'categories'])->name('tax-settings.categories');
        Route::get('tax-settings/ptkp-labels', [\App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class, 'ptkpLabels'])->name('tax-settings.ptkp-labels');
        Route::get('tax-settings/ptkp/year/{year}', [\App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class, 'ptkpForYear'])->name('tax-settings.ptkp.year');
        Route::post('tax-settings/ptkp/value', [\App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class, 'getPtkpValue'])->name('tax-settings.ptkp.value');
        Route::get('tax-settings/biaya-jabatan/year/{year}', [\App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class, 'biayaJabatanForYear'])->name('tax-settings.biaya-jabatan.year');
        Route::apiResource('tax-settings', \App\Http\Controllers\Api\V1\Payroll\TaxSettingController::class);

        // Employee Salaries (static routes first)
        Route::get('employee-salaries/available-employees', [\App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class, 'availableEmployees'])->name('employee-salaries.available-employees');
        Route::get('employee-salaries/ptkp-statuses', [\App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class, 'ptkpStatuses'])->name('employee-salaries.ptkp-statuses');
        Route::get('employee-salaries/summary', [\App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class, 'summary'])->name('employee-salaries.summary');
        Route::get('employee-salaries/{employeeSalary}/history', [\App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class, 'history'])->name('employee-salaries.history');
        Route::put('employee-salaries/{employeeSalary}/components', [\App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class, 'syncComponents'])->name('employee-salaries.sync-components');
        Route::apiResource('employee-salaries', \App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController::class);

        // Payroll Periods (static routes first)
        Route::get('periods/statuses', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'statuses'])->name('periods.statuses');
        Route::post('periods/{payrollPeriod}/generate-slips', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'generateSlips'])->name('periods.generate-slips');
        Route::get('periods/{payrollPeriod}/generate-progress', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'generateProgress'])->name('periods.generate-progress');
        Route::post('periods/{payrollPeriod}/generate-slips-with-attendance', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'generateSlipsWithAttendance'])->name('periods.generate-slips-with-attendance');
        Route::post('periods/{payrollPeriod}/calculate', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'calculate'])->name('periods.calculate');
        Route::post('periods/{payrollPeriod}/calculate-attendance', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'calculateAttendance'])->name('periods.calculate-attendance');
        Route::post('periods/{payrollPeriod}/submit-for-approval', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'submitForApproval'])->name('periods.submit-for-approval');
        Route::post('periods/{payrollPeriod}/approve', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'approve'])->name('periods.approve');
        Route::post('periods/{payrollPeriod}/mark-as-paid', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'markAsPaid'])->name('periods.mark-as-paid');
        Route::post('periods/{payrollPeriod}/finalize', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'finalize'])->name('periods.finalize');
        Route::post('periods/{payrollPeriod}/unfinalize', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'unfinalize'])->name('periods.unfinalize');
        Route::post('periods/{payrollPeriod}/sync-employees', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'syncNewEmployees'])->name('periods.sync-employees');
        Route::get('periods/{payrollPeriod}/summary', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'summary'])->name('periods.summary');
        Route::apiResource('periods', \App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class);

        // Payroll Slips
        Route::get('slips/components', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'getComponents'])->name('slips.components');
        Route::get('periods/{payrollPeriod}/slips', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'index'])->name('slips.index');
        Route::get('slips/{payrollSlip}', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'show'])->name('slips.show');
        Route::put('slips/{payrollSlip}/items', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'updateItems'])->name('slips.update-items');
        Route::post('slips/{payrollSlip}/items', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'addItem'])->name('slips.add-item');
        Route::delete('slips/{payrollSlip}/items/{item}', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'removeItem'])->name('slips.remove-item');
        Route::put('slips/{payrollSlip}/items/{item}', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'updateItem'])->name('slips.update-item');
        Route::put('slips/{payrollSlip}/notes', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'updateNotes'])->name('slips.update-notes');
        Route::get('slips/{payrollSlip}/audits', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'getAudits'])->name('slips.audits');
        Route::get('slips/{payrollSlip}/print', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'printData'])->name('slips.print');

        // PDF Export
        Route::get('slips/{payrollSlip}/pdf', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'downloadPdf'])->name('slips.pdf');
        Route::get('slips/{payrollSlip}/pdf/preview', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'previewPdf'])->name('slips.pdf.preview');
        Route::get('periods/{payrollPeriod}/export-pdf', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'exportPdfZip'])->name('periods.export-pdf');

        // WhatsApp
        Route::get('slips/{payrollSlip}/phone', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'getEmployeePhone'])->name('slips.phone');
        Route::post('slips/{payrollSlip}/send-whatsapp', [\App\Http\Controllers\Api\V1\Payroll\PayrollSlipController::class, 'sendWhatsApp'])->name('slips.send-whatsapp');
        Route::get('periods/{payrollPeriod}/whatsapp-preview', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'previewWhatsAppRecipients'])->name('periods.whatsapp-preview');
        Route::post('periods/{payrollPeriod}/send-whatsapp', [\App\Http\Controllers\Api\V1\Payroll\PayrollPeriodController::class, 'sendWhatsAppBulk'])->name('periods.send-whatsapp');

        // Reports
        Route::get('reports/dashboard', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'dashboard'])->name('reports.dashboard');
        Route::get('reports/monthly-recap', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'monthlyRecap'])->name('reports.monthly-recap');
        Route::get('reports/pph21', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'pph21'])->name('reports.pph21');
        Route::get('reports/bpjs', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'bpjs'])->name('reports.bpjs');
        Route::get('reports/employee/{employee}/history', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'employeeHistory'])->name('reports.employee-history');
        Route::get('reports/export/monthly-recap', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'exportMonthlyRecap'])->name('reports.export.monthly-recap');
        Route::get('reports/export/pph21', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'exportPph21'])->name('reports.export.pph21');
        Route::get('reports/export/bpjs', [\App\Http\Controllers\Api\V1\Payroll\ReportController::class, 'exportBpjs'])->name('reports.export.bpjs');
    });

    // ===========================================
    // Library Module
    // ===========================================
    Route::prefix('library')->name('library.')->group(function () {
        Route::apiResource('categories', \App\Http\Controllers\Api\V1\Library\BookCategoryController::class);
        Route::apiResource('books', \App\Http\Controllers\Api\V1\Library\BookController::class);
        Route::get('books/{book}/copies', [\App\Http\Controllers\Api\V1\Library\BookController::class, 'copies'])->name('books.copies');

        Route::apiResource('members', \App\Http\Controllers\Api\V1\Library\MemberController::class);
        Route::get('members/{member}/loans', [\App\Http\Controllers\Api\V1\Library\MemberController::class, 'loans'])->name('members.loans');
        Route::get('members-available-users', [\App\Http\Controllers\Api\V1\Library\MemberController::class, 'availableUsers'])->name('members.available-users');

        Route::get('loans/statistics', [\App\Http\Controllers\Api\V1\Library\LoanController::class, 'statistics'])->name('loans.statistics');
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\Library\LoanController::class);
        Route::post('loans/{loan}/return', [\App\Http\Controllers\Api\V1\Library\LoanController::class, 'returnBook'])->name('loans.return');
        Route::post('loans/{loan}/extend', [\App\Http\Controllers\Api\V1\Library\LoanController::class, 'extend'])->name('loans.extend');

        Route::apiResource('reservations', \App\Http\Controllers\Api\V1\Library\ReservationController::class);

        Route::get('settings', [\App\Http\Controllers\Api\V1\Library\SettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [\App\Http\Controllers\Api\V1\Library\SettingController::class, 'update'])->name('settings.update');
    });

    // ===========================================
    // Report Module
    // ===========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        // Report Cards - static routes first
        Route::get('report-cards/statistics', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'statistics'])->name('report-cards.statistics');
        Route::post('report-cards/generate', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'generate'])->name('report-cards.generate');
        Route::post('report-cards/bulk-approve', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'bulkApprove'])->name('report-cards.bulk-approve');
        Route::post('report-cards/bulk-publish', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'bulkPublish'])->name('report-cards.bulk-publish');

        Route::get('report-cards', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'index'])->name('report-cards.index');
        Route::get('report-cards/{reportCard}', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'show'])->name('report-cards.show');
        Route::put('report-cards/{reportCard}', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'update'])->name('report-cards.update');
        Route::post('report-cards/{reportCard}/submit-review', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'submitForReview'])->name('report-cards.submit-review');
        Route::post('report-cards/{reportCard}/approve', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'approve'])->name('report-cards.approve');
        Route::post('report-cards/{reportCard}/publish', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'publish'])->name('report-cards.publish');
        Route::get('report-cards/{reportCard}/pdf', [\App\Http\Controllers\Api\V1\Report\ReportCardController::class, 'pdf'])->name('report-cards.pdf');

        // Generated Reports
        Route::get('generated', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'index'])->name('generated.index');
        Route::post('generate', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'generate'])->name('generate');
        Route::get('download/{report}', [\App\Http\Controllers\Api\V1\Report\GeneratedReportController::class, 'download'])->name('download');
    });

    // ===========================================
    // Notification Module
    // ===========================================
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('{notification}/read', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('{notification}', [\App\Http\Controllers\Api\V1\Notification\NotificationController::class, 'destroy'])->name('destroy');

        // Announcements
        Route::get('announcements/statistics', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'statistics'])->name('announcements.statistics');
        // Umpan ikon lonceng — terbuka untuk semua pengguna login, isinya hanya
        // pengumuman yang sudah tayang. WAJIB sebelum apiResource: kalau tidak,
        // 'feed' ketangkap wildcard {announcement} lebih dulu (jebakan yang sama
        // pernah membuat qr/teachers/bulk 500).
        Route::get('announcements/feed', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'feed'])->name('announcements.feed');
        Route::apiResource('announcements', \App\Http\Controllers\Api\V1\Notification\AnnouncementController::class);
        Route::post('announcements/{announcement}/publish', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::post('announcements/{announcement}/read', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'markAsRead'])->name('announcements.read');
        Route::delete('announcements/{announcement}/image', [\App\Http\Controllers\Api\V1\Notification\AnnouncementController::class, 'deleteImage'])->name('announcements.image.destroy');
    });

    // ===========================================
    // Setting Module
    // ===========================================
    Route::prefix('settings')->name('settings.')->group(function () {
        // Catatan: SettingController generik (index/show/update {group}) dulu
        // dirujuk di sini tapi FILE-nya tidak pernah ada — menyebabkan
        // `php artisan route:list` crash & endpoint /settings 500. Rute mati itu
        // dihapus; yang tersisa hanya Pengaturan Menu di bawah.

        // Pengaturan Menu (visibilitas per-role) — admin only.
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('menu', [\App\Http\Controllers\Api\V1\Setting\MenuSettingController::class, 'show'])->name('menu.show');
            Route::put('menu', [\App\Http\Controllers\Api\V1\Setting\MenuSettingController::class, 'update'])->name('menu.update');
        });

        // Profil Sekolah (nama, NPSN, jenjang, kontak, logo). Update memakai POST
        // karena membawa berkas (multipart), samakan pola dengan upload avatar.
        Route::middleware('permission:settings.school')->group(function () {
            Route::get('school', [\App\Http\Controllers\Api\V1\Setting\SchoolProfileController::class, 'show'])->name('school.show');
            Route::post('school', [\App\Http\Controllers\Api\V1\Setting\SchoolProfileController::class, 'update'])->name('school.update');
        });

    });

    // ===========================================
    // Admin Only Routes
    // ===========================================
    Route::middleware(['role:super_admin|admin'])->prefix('admin')->name('admin.')->group(function () {
        // Akun yang belum punya data master — dipakai opsi "tautkan ke akun yang
        // sudah ada" pada form Tambah Guru/Staf/Siswa. SENGAJA di luar subgrup
        // super_admin di bawah: form-form itu dipakai admin sekolah juga, bukan
        // hanya super admin. Dibatasi izin membuat data masternya.
        Route::get('users/linkable', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'linkableUsers'])
            ->middleware('permission:teachers.create|staff.create|students.create')
            ->name('users.linkable');

        // Users Management (super admin only)
        Route::middleware(['role:super_admin'])->group(function () {
            Route::get('users/roles', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'roles'])->name('users.roles');
            Route::get('users/student-options', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'studentOptions'])->name('users.student-options');
            Route::get('users/staff-options', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'staffOptions'])->name('users.staff-options');
            Route::apiResource('users', \App\Http\Controllers\Api\V1\Admin\UserController::class);
            Route::post('users/{user}/activate', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'activate'])->name('users.activate');
            Route::post('users/{user}/deactivate', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\V1\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');

            // Manajemen sekolah (tenant) tingkat sistem.
            Route::get('schools', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'index'])->name('schools.index');
            Route::post('schools', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'store'])->name('schools.store');
            Route::post('schools/{id}/activate', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'activate'])->name('schools.activate');
            Route::post('schools/{id}/deactivate', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'deactivate'])->name('schools.deactivate');
            Route::delete('schools/{id}', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'destroy'])->name('schools.destroy');

            // Branding halaman login (logo & nama sekolah yang ditampilkan di /login).
            Route::get('login-brand', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'getLoginBrand'])->name('login-brand.show');
            Route::put('login-brand', [\App\Http\Controllers\Api\V1\Admin\SchoolController::class, 'setLoginBrand'])->name('login-brand.update');
        });

        // Roles & Permissions
        Route::apiResource('roles', \App\Http\Controllers\Api\V1\Admin\RoleController::class);
        Route::get('permissions', [\App\Http\Controllers\Api\V1\Admin\PermissionController::class, 'index'])->name('permissions.index');

        // Audit Logs
        Route::get('audit-logs', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

        // Keamanan Login: riwayat login, kode akses sekali-pakai, cabut sesi.
        Route::prefix('login-security')->name('login-security.')->group(function () {
            Route::get('logs', [\App\Http\Controllers\Api\V1\Admin\LoginSecurityController::class, 'logs'])->name('logs');
            Route::get('users', [\App\Http\Controllers\Api\V1\Admin\LoginSecurityController::class, 'users'])->name('users');
            Route::post('users/{user}/otp', [\App\Http\Controllers\Api\V1\Admin\LoginSecurityController::class, 'generateOtp'])->name('otp.generate');
            Route::delete('users/{user}/otp', [\App\Http\Controllers\Api\V1\Admin\LoginSecurityController::class, 'revokeOtp'])->name('otp.revoke');
            Route::post('users/{user}/revoke-sessions', [\App\Http\Controllers\Api\V1\Admin\LoginSecurityController::class, 'revokeSessions'])->name('sessions.revoke');
        });

        // Provisioning via QR Code: admin generate token, user scan di mobile.
        Route::prefix('provision')->name('provision.')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\V1\Auth\ProvisionController::class, 'generate'])->name('generate');
            Route::get('users/{user}', [\App\Http\Controllers\Api\V1\Auth\ProvisionController::class, 'history'])->name('history');
        });
        Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\V1\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');

        // Device Monitor: kelola perangkat scanner, monitoring koneksi.
        Route::prefix('devices')->name('devices.')->group(function () {
            Route::get('server-info', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'serverInfo'])->name('server-info');
            Route::get('summary', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'summary'])->name('summary');
            Route::get('/', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'store'])->name('store');
            Route::post('provision-qr', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'generateProvisionQr'])->name('provision-qr');
            Route::get('{device}', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'show'])->name('show');
            Route::put('{device}', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'update'])->name('update');
            Route::delete('{device}', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'destroy'])->name('destroy');
            Route::post('{device}/regenerate-token', [\App\Http\Controllers\Api\V1\Attendance\DeviceMonitorController::class, 'regenerateToken'])->name('regenerate-token');
        });

        // Activity Logs
        Route::get('activity-logs', [\App\Http\Controllers\Api\V1\Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
    });

    // ===========================================
    // Super Admin Only Routes
    // ===========================================
    Route::middleware(['role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
        // Tenant Management
        Route::apiResource('tenants', \App\Http\Controllers\Api\V1\Tenant\TenantController::class);
        Route::post('tenants/{tenant}/activate', [\App\Http\Controllers\Api\V1\Tenant\TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('tenants/{tenant}/suspend', [\App\Http\Controllers\Api\V1\Tenant\TenantController::class, 'suspend'])->name('tenants.suspend');

        // System Health
        Route::get('health', [\App\Http\Controllers\Api\V1\Admin\SystemController::class, 'health'])->name('health');
        Route::get('metrics', [\App\Http\Controllers\Api\V1\Admin\SystemController::class, 'metrics'])->name('metrics');

        // Database Backups
        Route::get('backups/active-database', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'activeDatabase'])->name('backups.active-database');
        Route::get('backups', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{filename}/download', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{filename}', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'destroy'])->name('backups.destroy');
        Route::middleware('throttle:sensitive')->group(function () {
            Route::post('backups/{filename}/restore', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'restore'])->name('backups.restore');
            Route::post('backups/import', [\App\Http\Controllers\Api\V1\System\BackupController::class, 'import'])->name('backups.import');
        });

        // Koneksi Database Aplikasi (.env DB_*) — digabung ke menu Backup Database.
        // Password akses terpisah dari login, lihat DatabaseConnectionController.
        Route::prefix('db-connection')->name('db-connection.')->group(function () {
            Route::get('access-status', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'accessStatus'])->name('access-status');
            Route::middleware('throttle:sensitive')->group(function () {
                Route::post('access-password', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'setAccessPassword'])->name('access-password');
                Route::post('reveal', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'reveal'])->name('reveal');
                Route::post('test', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'test'])->name('test');
                Route::put('/', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'update'])->name('update');
            });
        });

        // Versi aplikasi (ditampilkan di footer) — dikelola dari menu yang
        // sama dengan Koneksi Database Aplikasi. Bukan data sensitif, jadi
        // tidak digerbangi access_password/throttle:sensitive.
        Route::put('app-version', [\App\Http\Controllers\Api\V1\System\DatabaseConnectionController::class, 'updateAppVersion'])->name('app-version.update');
    });
});

// ===========================================
// Public Routes (No Auth Required) - Throttled
// ===========================================
Route::prefix('public')->name('public.')->middleware('throttle:20,1')->group(function () {
    // Leave Permission Portal
    Route::post('izin/lookup', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'lookup'])->name('izin.lookup');
    Route::post('izin/submit', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'submit'])->name('izin.submit');
    Route::post('izin/status', [\App\Http\Controllers\Api\V1\Attendance\PublicLeaveController::class, 'status'])->name('izin.status');

    // Attendance Check Portal
    Route::post('cek-kehadiran', [\App\Http\Controllers\Api\V1\Attendance\PublicAttendanceController::class, 'check'])->name('cek-kehadiran');
    Route::post('riwayat-kehadiran', [\App\Http\Controllers\Api\V1\Attendance\PublicAttendanceController::class, 'history'])->name('riwayat-kehadiran');
});
